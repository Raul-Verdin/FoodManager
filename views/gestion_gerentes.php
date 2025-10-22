<?php
// Desactivamos el switcher de restaurante para esta vista
$disable_restaurant_switcher = true;

include '../includes/header.php'; 

// Permiso requerido: EXCLUSIVO del Super Administrador (J1)
$permission_needed = 'gestion_plataforma'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a gestión de gerentes.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Esta vista es exclusiva del Super Administrador.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';

// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE: Asignación de Restaurantes)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_asignacion') {
    $gerente_id = (int)$_POST['gerente_id'];
    $restaurantes_seleccionados = $_POST['restaurantes'] ?? []; // Array de IDs de restaurantes

    // Obtener el ID del Rol de Gerente de Restaurante (J2)
    $rol_gerente_id = $pdo->query("SELECT id FROM roles WHERE nombre = 'gerente_restaurante'")->fetchColumn();

    try {
        $pdo->beginTransaction();

        // 1. Eliminar todas las asignaciones existentes del gerente en la tabla 'usuarios_restaurantes'
        $stmt_delete = $pdo->prepare("
            DELETE FROM usuarios_restaurantes 
            WHERE usuario_id = :uid AND rol_id = :rid
        ");
        $stmt_delete->bindParam(':uid', $gerente_id);
        $stmt_delete->bindParam(':rid', $rol_gerente_id);
        $stmt_delete->execute();
        
        // Opcional: Si el gerente es el *gerenteres_id* principal de un restaurante, reasignarlo a NULL o a otro, pero por simplicidad solo manejaremos la tabla UR.
        
        // 2. Reinsertar las asignaciones seleccionadas
        if (!empty($restaurantes_seleccionados)) {
            $stmt_insert = $pdo->prepare("
                INSERT INTO usuarios_restaurantes (usuario_id, restaurante_id, rol_id, fecha_ingreso, activo) 
                VALUES (:uid, :res_id, :rol_id, CURDATE(), TRUE)
            ");
            $stmt_insert->bindParam(':uid', $gerente_id);
            $stmt_insert->bindParam(':rol_id', $rol_gerente_id);
            
            foreach ($restaurantes_seleccionados as $res_id) {
                $stmt_insert->bindParam(':res_id', $res_id);
                $stmt_insert->execute();
            }
        }

        $pdo->commit();
        logActivity($_SESSION['user_id'], 'UPDATE MANAGER ASSIGNMENT', "Asignaciones actualizadas para Gerente ID: {$gerente_id}");
        $message = "✅ Asignaciones de restaurantes para el Gerente ID {$gerente_id} actualizadas correctamente.";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error al actualizar asignaciones: " . $e->getMessage();
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

try {
    // 1. Obtener el ID del Rol de Gerente de Restaurante (J2)
    $rol_gerente_id = $pdo->query("SELECT id FROM roles WHERE nombre = 'gerente_restaurante'")->fetchColumn();

    // 2. Obtener todos los Gerentes de Restaurante (Usuarios con ese rol)
    $stmt_gerentes = $pdo->prepare("
        SELECT DISTINCT u.id, u.nombre_completo, u.email, u.activo
        FROM usuarios_restaurantes ur
        JOIN usuarios u ON ur.usuario_id = u.id
        WHERE ur.rol_id = :rid AND u.activo = 1
        ORDER BY u.nombre_completo ASC
    ");
    $stmt_gerentes->bindParam(':rid', $rol_gerente_id);
    $stmt_gerentes->execute();
    $gerentes_list = $stmt_gerentes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener todos los Restaurantes
    $stmt_restaurantes = $pdo->query("SELECT id, nombre FROM restaurantes ORDER BY nombre ASC");
    $restaurantes_list = $stmt_restaurantes->fetchAll(PDO::FETCH_ASSOC);

    // 4. Obtener las asignaciones actuales de restaurantes por gerente
    $asignaciones_actuales = [];
    $stmt_asignaciones = $pdo->prepare("
        SELECT usuario_id, restaurante_id 
        FROM usuarios_restaurantes 
        WHERE rol_id = :rid
    ");
    $stmt_asignaciones->bindParam(':rid', $rol_gerente_id);
    $stmt_asignaciones->execute();
    while ($row = $stmt_asignaciones->fetch(PDO::FETCH_ASSOC)) {
        $asignaciones_actuales[$row['usuario_id']][] = $row['restaurante_id'];
    }

} catch (PDOException $e) {
    $error_message = "Error al cargar la gestión de gerentes.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_gerentes.css">
</head>

<h1>Gestión Centralizada de Gerentes de Restaurante (J2)</h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<p>Esta vista permite asignar uno o varios restaurantes a cada Gerente de Restaurante (J2). Solo se muestran usuarios activos que ya tienen el rol de Gerente.</p>

<div class="roles-container">
    <?php foreach ($gerentes_list as $gerente): ?>
        <div class="card card-role">
            <form action="gestion_gerentes.php" method="POST">
                <input type="hidden" name="action" value="update_asignacion">
                <input type="hidden" name="gerente_id" value="<?php echo $gerente['id']; ?>">
                
                <h3><?php echo htmlspecialchars($gerente['nombre_completo']); ?> (ID: <?php echo $gerente['id']; ?>)</h3>
                <p><?php echo htmlspecialchars($gerente['email']); ?></p>
                
                <div class="permisos-list">
                    <?php foreach ($restaurantes_list as $restaurante): ?>
                        <?php 
                        $asignado = in_array($restaurante['id'], $asignaciones_actuales[$gerente['id']] ?? []);
                        ?>
                        <div class="checkbox-item">
                            <input 
                                type="checkbox" 
                                name="restaurantes[]" 
                                value="<?php echo $restaurante['id']; ?>" 
                                id="gerente_<?php echo $gerente['id']; ?>_res_<?php echo $restaurante['id']; ?>"
                                <?php echo $asignado ? 'checked' : ''; ?>
                            >
                            <label for="gerente_<?php echo $gerente['id']; ?>_res_<?php echo $restaurante['id']; ?>">
                                <?php echo htmlspecialchars($restaurante['nombre']); ?> (ID: <?php echo $restaurante['id']; ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block mt-3">Guardar Asignaciones</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>