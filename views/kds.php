<?php
include '../includes/header.php'; 

$permission_needed = 'gestion_cocina'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso al KDS.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede acceder a la pantalla de cocina.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$usuario_id = $_SESSION['user_id'];

if (!$restaurante_id) {
    echo '<h1>KDS (Kitchen Display System)</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para ver las órdenes de cocina.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE ESTADO)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_item_status') {
    $detalle_id = (int)$_POST['detalle_id'];
    $nuevo_estado = trim($_POST['nuevo_estado']);

    if (!in_array($nuevo_estado, ['EN_PREPARACION', 'LISTO'])) {
        $error_message = 'Estado inválido.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Actualizar el estado del detalle de la orden (el plato)
            $stmt_update = $pdo->prepare("
                UPDATE detalle_ordenes do 
                JOIN ordenes o ON do.orden_id = o.id
                SET do.estado = :estado
                WHERE do.id = :did AND o.restaurante_id = :rid
            ");
            $stmt_update->execute([
                ':estado' => $nuevo_estado,
                ':did' => $detalle_id,
                ':rid' => $restaurante_id
            ]);

            // 2. Comprobar si toda la orden está lista/en preparación (para actualizar la orden principal)
            $stmt_check = $pdo->prepare("
                SELECT DISTINCT estado FROM detalle_ordenes WHERE orden_id = (
                    SELECT orden_id FROM detalle_ordenes WHERE id = :did
                )
            ");
            $stmt_check->bindParam(':did', $detalle_id);
            $stmt_check->execute();
            $estados_orden = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

            $orden_id_to_update = $pdo->query("SELECT orden_id FROM detalle_ordenes WHERE id = $detalle_id")->fetchColumn();

            if (count($estados_orden) === 1 && $estados_orden[0] === 'LISTO') {
                // Si todos están listos, marcar la orden como COMPLETA
                $nuevo_estado_orden = 'COMPLETADA';
            } elseif (in_array('PENDIENTE', $estados_orden) && (in_array('EN_PREPARACION', $estados_orden) || in_array('LISTO', $estados_orden))) {
                // Si hay una mezcla, dejar en PROCESO
                $nuevo_estado_orden = 'EN_PROCESO';
            } else {
                // Si la acción es a EN_PREPARACION y era PENDIENTE, pasar a EN_PROCESO
                 $nuevo_estado_orden = 'EN_PROCESO';
            }
            
            // 3. Actualizar la orden principal
            $stmt_update_orden = $pdo->prepare("UPDATE ordenes SET estado = :estado WHERE id = :oid AND restaurante_id = :rid");
            $stmt_update_orden->execute([':estado' => $nuevo_estado_orden, ':oid' => $orden_id_to_update, ':rid' => $restaurante_id]);

            $pdo->commit();
            logActivity($usuario_id, 'KDS UPDATE', "Plato ID: {$detalle_id} cambiado a {$nuevo_estado}");
            $message = "✅ Plato actualizado a {$nuevo_estado}.";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error al actualizar estado: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ: Órdenes Pendientes/Proceso)
// ----------------------------------------------------------------------

$ordenes_a_cocinar = [];
try {
    // 1. Obtener todas las órdenes que no han sido pagadas/canceladas y tienen al menos 1 ítem no LISTO
    $stmt_ordenes = $pdo->prepare("
        SELECT 
            o.id AS orden_id, 
            o.fecha_orden, 
            o.mesa_id, 
            o.estado AS orden_estado,
            m.numero AS mesa_numero
        FROM ordenes o
        LEFT JOIN mesas m ON o.mesa_id = m.id
        WHERE o.restaurante_id = :rid 
          AND o.estado IN ('PENDIENTE', 'EN_PROCESO')
        ORDER BY o.fecha_orden ASC
    ");
    $stmt_ordenes->bindParam(':rid', $restaurante_id);
    $stmt_ordenes->execute();
    $ordenes_raw = $stmt_ordenes->fetchAll(PDO::FETCH_ASSOC);

    $orden_ids = array_column($ordenes_raw, 'orden_id');

    if (!empty($orden_ids)) {
        // 2. Obtener todos los detalles (platos) de esas órdenes
        $placeholders = implode(',', array_fill(0, count($orden_ids), '?'));
        $stmt_detalles = $pdo->prepare("
            SELECT 
                do.id AS detalle_id, do.orden_id, do.cantidad, do.notas, do.estado,
                pm.nombre AS plato_nombre
            FROM detalle_ordenes do
            JOIN platos_menu pm ON do.plato_id = pm.id
            WHERE do.orden_id IN ({$placeholders})
            ORDER BY do.id ASC
        ");
        $stmt_detalles->execute($orden_ids);
        $detalles_raw = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

        // 3. Organizar los datos
        $ordenes_map = array_column($ordenes_raw, null, 'orden_id');
        
        foreach ($detalles_raw as $detalle) {
            $orden_id = $detalle['orden_id'];
            if (!isset($ordenes_a_cocinar[$orden_id])) {
                $ordenes_a_cocinar[$orden_id] = [
                    'info' => $ordenes_map[$orden_id],
                    'detalles' => []
                ];
            }
            $ordenes_a_cocinar[$orden_id]['detalles'][] = $detalle;
        }
    }

} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de órdenes para KDS: " . $e->getMessage();
}

?>

<meta http-equiv="refresh" content="30">

<h1>KDS (Kitchen Display System) - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<p>Última actualización: <?php echo date('H:i:s'); ?> | Refresco automático en 30 segundos.</p>

<div class="kds-grid">
    <?php if (empty($ordenes_a_cocinar)): ?>
        <div class="alert alert-info" style="grid-column: 1 / -1;">
            ¡Excelente! No hay órdenes pendientes en este momento.
        </div>
    <?php else: ?>
        <?php foreach ($ordenes_a_cocinar as $orden_id => $orden): 
            $mesa_display = $orden['info']['mesa_numero'] ? 'MESA ' . htmlspecialchars($orden['info']['mesa_numero']) : 'LLEVAR';
            $age_minutes = round((time() - strtotime($orden['info']['fecha_orden'])) / 60);
        ?>
            <div class="kds-card kds-<?php echo strtolower($orden['info']['orden_estado']); ?>">
                <div class="kds-header">
                    <span class="kds-order-id">ORDEN #<?php echo $orden_id; ?></span>
                    <span class="kds-mesa"><?php echo $mesa_display; ?></span>
                    <span class="kds-time kds-age-<?php echo ($age_minutes > 15) ? 'high' : (($age_minutes > 5) ? 'medium' : 'low'); ?>">
                        Hace <?php echo $age_minutes; ?> min
                    </span>
                </div>
                
                <ul class="kds-list">
                    <?php foreach ($orden['detalles'] as $detalle): ?>
                        <li class="kds-item kds-item-<?php echo strtolower($detalle['estado']); ?>">
                            <span class="kds-cantidad"><?php echo $detalle['cantidad']; ?>x</span>
                            <span class="kds-plato"><?php echo htmlspecialchars($detalle['plato_nombre']); ?></span>
                            <?php if (!empty($detalle['notas'])): ?>
                                <span class="kds-notas">(<?php echo htmlspecialchars($detalle['notas']); ?>)</span>
                            <?php endif; ?>

                            <div class="kds-actions">
                                <?php if ($detalle['estado'] === 'PENDIENTE'): ?>
                                    <form action="" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_item_status">
                                        <input type="hidden" name="detalle_id" value="<?php echo $detalle['detalle_id']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="EN_PREPARACION">
                                        <button type="submit" class="btn btn-warning btn-sm">Preparar</button>
                                    </form>
                                <?php elseif ($detalle['estado'] === 'EN_PREPARACION'): ?>
                                    <form action="" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_item_status">
                                        <input type="hidden" name="detalle_id" value="<?php echo $detalle['detalle_id']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="LISTO">
                                        <button type="submit" class="btn btn-success btn-sm">Listo</button>
                                    </form>
                                <?php elseif ($detalle['estado'] === 'LISTO'): ?>
                                    <span class="badge badge-success">LISTO</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* Estilos básicos para el KDS */
.kds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 10px 0;
}
.kds-card {
    border: 1px solid #ccc;
    border-radius: 8px;
    box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
    background-color: #fff;
    display: flex;
    flex-direction: column;
}
.kds-header {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    font-weight: bold;
    color: #fff;
    border-bottom: 2px solid #fff;
}
.kds-pendiente .kds-header { background-color: #007bff; }
.kds-en_proceso .kds-header { background-color: orange; }
.kds-completada .kds-header { background-color: green; } /* Aunque estas no deberían mostrarse */

.kds-time { font-size: 0.9em; }
.kds-age-high { color: red; }
.kds-age-medium { color: yellow; }
.kds-age-low { color: #fff; }

.kds-list {
    list-style: none;
    padding: 0;
    margin: 0;
    flex-grow: 1;
}
.kds-item {
    display: flex;
    padding: 10px;
    border-bottom: 1px solid #eee;
    align-items: center;
    font-size: 1.1em;
}
.kds-cantidad {
    font-weight: bold;
    margin-right: 10px;
    color: #333;
}
.kds-plato {
    flex-grow: 1;
}
.kds-notas {
    font-style: italic;
    color: #999;
    margin-right: 10px;
}
.kds-item-pendiente { background-color: #f8d7da; }
.kds-item-en_preparacion { background-color: #fff3cd; }
.kds-item-listo { background-color: #d4edda; }
.kds-actions {
    min-width: 100px;
    text-align: right;
}
</style>

<?php include '../includes/footer.php'; ?>