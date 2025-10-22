<?php
include '../includes/header.php'; 

$permission_needed = 'gestion_mesas'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a Gestión de Mesas.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede acceder a la gestión de mesas.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$usuario_id = $_SESSION['user_id'];

if (!$restaurante_id) {
    echo '<h1>Gestión de Mesas</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para gestionar las mesas.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE ESTADO MESA)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_table_status') {
    $mesa_id = (int)$_POST['mesa_id'];
    $nuevo_estado = trim($_POST['nuevo_estado']);

    if (!in_array($nuevo_estado, ['LIBRE', 'OCUPADA', 'LIMPIEZA', 'FUERA_DE_SERVICIO'])) {
        $error_message = 'Estado de mesa inválido.';
    } else {
        try {
            $stmt_update = $pdo->prepare("
                UPDATE mesas 
                SET estado = :estado
                WHERE id = :mid AND restaurante_id = :rid
            ");
            $stmt_update->execute([
                ':estado' => $nuevo_estado,
                ':mid' => $mesa_id,
                ':rid' => $restaurante_id
            ]);

            logActivity($usuario_id, 'MESA UPDATE', "Mesa ID: {$mesa_id} cambiada a {$nuevo_estado}");
            $message = "✅ Mesa {$mesa_id} actualizada a {$nuevo_estado}.";

        } catch (PDOException $e) {
            $error_message = "Error al actualizar estado: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ: Mesas)
// ----------------------------------------------------------------------
$mesas = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, numero, capacidad, estado
        FROM mesas
        WHERE restaurante_id = :rid
        ORDER BY numero ASC
    ");
    $stmt->bindParam(':rid', $restaurante_id);
    $stmt->execute();
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de mesas: " . $e->getMessage();
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ: Reservas de hoy)
// ----------------------------------------------------------------------
$reservas_hoy = [];
try {
    $hoy = date('Y-m-d');
    $stmt_reservas = $pdo->prepare("
        SELECT id, nombre_cliente, hora_reserva, numero_personas, estado 
        FROM reservas 
        WHERE restaurante_id = :rid AND fecha_reserva = :hoy AND estado IN ('PENDIENTE', 'CONFIRMADA')
        ORDER BY hora_reserva ASC
    ");
    $stmt_reservas->execute([':rid' => $restaurante_id, ':hoy' => $hoy]);
    $reservas_hoy = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejo de error
}

?>

<meta http-equiv="refresh" content="60">

<h1>Gestión de Mesas y Sala - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<div class="row">
    <div class="col-md-9">
        <h2>Panel de Mesas</h2>
        <p>Última actualización: <?php echo date('H:i:s'); ?> | Refresco automático en 60 segundos.</p>
        <div class="table-grid">
            <?php if (empty($mesas)): ?>
                <div class="alert alert-info" style="grid-column: 1 / -1;">
                    No hay mesas configuradas para este restaurante.
                </div>
            <?php else: ?>
                <?php foreach ($mesas as $mesa): ?>
                    <div class="table-card table-status-<?php echo strtolower($mesa['estado']); ?>">
                        <h3>Mesa #<?php echo htmlspecialchars($mesa['numero']); ?></h3>
                        <p>Capacidad: **<?php echo htmlspecialchars($mesa['capacidad']); ?>** personas</p>
                        <p>Estado: **<?php echo str_replace('_', ' ', htmlspecialchars($mesa['estado'])); ?>**</p>

                        <div class="table-actions">
                            <?php 
                            // Opciones de cambio de estado
                            $next_states = [];
                            if ($mesa['estado'] === 'LIBRE') {
                                $next_states = ['OCUPADA' => 'Ocupar', 'LIMPIEZA' => 'A Limpiar', 'FUERA_DE_SERVICIO' => 'F. Servicio'];
                            } elseif ($mesa['estado'] === 'OCUPADA') {
                                $next_states = ['LIMPIEZA' => 'Desocupar'];
                            } elseif ($mesa['estado'] === 'LIMPIEZA') {
                                $next_states = ['LIBRE' => 'Liberar'];
                            } elseif ($mesa['estado'] === 'FUERA_DE_SERVICIO') {
                                $next_states = ['LIBRE' => 'Habilitar'];
                            }
                            ?>
                            <?php foreach ($next_states as $state_value => $state_text): ?>
                                <form action="" method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="update_table_status">
                                    <input type="hidden" name="mesa_id" value="<?php echo $mesa['id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $state_value; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo get_btn_class($state_value); ?>"><?php echo $state_text; ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-3">
        <h2>Reservas Hoy</h2>
        <a href="gestion_reservas.php" class="btn btn-primary btn-sm mb-3"><i class="fas fa-calendar-plus"></i> Nueva/Ver Reservas</a>
        
        <?php if (empty($reservas_hoy)): ?>
            <div class="alert alert-info">No hay reservas pendientes para hoy.</div>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($reservas_hoy as $reserva): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($reserva['nombre_cliente']); ?> (<?php echo htmlspecialchars($reserva['numero_personas']); ?>p)</strong>
                            <small class="d-block text-muted">@ <?php echo date('H:i', strtotime($reserva['hora_reserva'])); ?></small>
                        </div>
                        <span class="badge badge-<?php echo strtolower($reserva['estado']) === 'confirmada' ? 'success' : 'primary'; ?>">
                            <?php echo str_replace('_', ' ', $reserva['estado']); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php 
function get_btn_class($estado) {
    switch ($estado) {
        case 'LIBRE':
            return 'success';
        case 'OCUPADA':
            return 'danger';
        case 'LIMPIEZA':
            return 'warning';
        case 'FUERA_DE_SERVICIO':
            return 'secondary';
        default:
            return 'info';
    }
}
?>

<style>
.table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    padding: 20px 0;
}
.table-card {
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.table-card h3 {
    margin-top: 0;
    font-size: 1.5em;
}
.table-status-libre { background-color: #d4edda; border-color: #c3e6cb; }
.table-status-ocupada { background-color: #f8d7da; border-color: #f5c6cb; }
.table-status-limpieza { background-color: #fff3cd; border-color: #ffeeba; }
.table-status-fuera_de_servicio { background-color: #e2e3e5; border-color: #d6d8db; }
.table-actions button {
    margin: 3px 0;
}
</style>

<?php include '../includes/footer.php'; ?>