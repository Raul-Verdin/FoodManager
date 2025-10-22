<?php
// Desactivamos el switcher de restaurante para esta vista
$disable_restaurant_switcher = true;

include '../includes/header.php'; 

// Permiso requerido: EXCLUSIVO del Super Administrador (J1)
$permission_needed = 'gestion_plataforma'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a gestión de roles y permisos.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Esta vista es exclusiva del Super Administrador.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';

// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_permisos') {
    $rol_id = (int)$_POST['rol_id'];
    $permisos_seleccionados = $_POST['permisos'] ?? []; // Array de IDs de permisos

    try {
        $pdo->beginTransaction();

        // 1. Eliminar todos los permisos existentes para este rol
        $stmt_delete = $pdo->prepare("DELETE FROM roles_permisos WHERE rol_id = :rid");
        $stmt_delete->bindParam(':rid', $rol_id);
        $stmt_delete->execute();

        // 2. Reinsertar los permisos seleccionados
        if (!empty($permisos_seleccionados)) {
            $stmt_insert = $pdo->prepare("INSERT INTO roles_permisos (rol_id, permiso_id) VALUES (:rid, :pid)");
            $stmt_insert->bindParam(':rid', $rol_id);
            foreach ($permisos_seleccionados as $permiso_id) {
                $stmt_insert->bindParam(':pid', $permiso_id);
                $stmt_insert->execute();
            }
        }

        $pdo->commit();
        logActivity($_SESSION['user_id'], 'UPDATE ROLE PERMISSIONS', "Permisos actualizados para Rol ID: {$rol_id}");
        $message = "✅ Permisos del Rol ID {$rol_id} actualizados correctamente.";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error al actualizar permisos: " . $e->getMessage();
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

try {
    // 1. Obtener todos los roles
    $stmt_roles = $pdo->query("SELECT id, nombre, jerarquia FROM roles ORDER BY jerarquia ASC");
    $roles_list = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener todos los permisos disponibles
    $stmt_permisos = $pdo->query("SELECT id, nombre, descripcion FROM permisos ORDER BY nombre ASC");
    $permisos_list = $stmt_permisos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener los permisos ASIGNADOS a cada rol (Para pre-seleccionar los checkboxes)
    $permisos_asignados = [];
    $stmt_asignados = $pdo->query("SELECT rol_id, permiso_id FROM roles_permisos");
    while ($row = $stmt_asignados->fetch(PDO::FETCH_ASSOC)) {
        $permisos_asignados[$row['rol_id']][] = $row['permiso_id'];
    }

} catch (PDOException $e) {
    $error_message = "Error al cargar la gestión de roles y permisos.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_roles_permisos.css">
</head>

<h1>Gestión de Roles y Permisos (Administración Central)</h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<p>Esta interfaz permite al Super Administrador definir qué permisos están asignados a cada Rol en el sistema. **¡Proceda con precaución!**</p>

<div class="roles-container">
    <?php foreach ($roles_list as $rol): ?>
        <div class="card card-role">
            <form action="gestion_roles_permisos.php" method="POST">
                <input type="hidden" name="action" value="update_permisos">
                <input type="hidden" name="rol_id" value="<?php echo $rol['id']; ?>">
                
                <h3><?php echo htmlspecialchars($rol['nombre']); ?> (J<?php echo $rol['jerarquia']; ?>)</h3>
                <p>Marque los permisos que tendrá este rol:</p>
                
                <div class="permisos-list">
                    <?php foreach ($permisos_list as $permiso): ?>
                        <?php 
                        $is_checked = in_array($permiso['id'], $permisos_asignados[$rol['id']] ?? []);
                        ?>
                        <div class="checkbox-item">
                          
                            <input 
                                type="checkbox" 
                                name="permisos[]" 
                                value="<?php echo $permiso['id']; ?>" 
                                id="rol_<?php echo $rol['id']; ?>_permiso_<?php echo $permiso['id']; ?>"
                                <?php echo $is_checked ? 'checked' : ''; ?>
                            >
                            <label for="rol_<?php echo $rol['id']; ?>_permiso_<?php echo $permiso['id']; ?>" title="<?php echo htmlspecialchars($permiso['descripcion']); ?>">
                                <?php echo htmlspecialchars($permiso['nombre']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block mt-3">Guardar Permisos</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>