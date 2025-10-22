<?php
include '../includes/header.php'; 

// El header ya hizo checkAuth()
$permission_needed = 'gestion_personal'; 

if (!checkPermission($permission_needed)) {
    // Si no tiene permiso, bloqueamos la vista.
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a {$permission_needed}");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede gestionar personal.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;

if (!$restaurante_id) {
    echo '<h1>Gestión de Personal</h1><p class="alert alert-warning">Por favor, selecciona un restaurante en el conmutador para gestionar su personal.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE / UPDATE / DELETE)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $user_id = (int)$_POST['usuario_id'] ?? 0;

    try {
        // A. Lógica de CREACIÓN / ASIGNACIÓN
        if ($action == 'create_assignment') {
            $rol_id = (int)$_POST['rol_id'];
            
            $stmt = $pdo->prepare("
                INSERT INTO usuarios_restaurantes (usuario_id, restaurante_id, rol_id, fecha_ingreso) 
                VALUES (:uid, :rid, :rolid, CURDATE())
            ");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->bindParam(':rolid', $rol_id);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'ASSIGN USER', "Asignó Usuario ID: {$user_id} al Restaurante ID: {$restaurante_id}");
            $message = "✅ Usuario asignado al restaurante con éxito.";
        }

        // B. Lógica de ACTUALIZACIÓN (Cambio de Rol o Estado Activo)
        if ($action == 'update_assignment') {
            $new_rol_id = (int)$_POST['new_rol_id'];
            $new_activo = isset($_POST['activo']) ? 1 : 0;
            
            $sql = "UPDATE usuarios_restaurantes SET rol_id = :rolid, activo = :activo";
            $params = [
                ':rolid' => $new_rol_id,
                ':activo' => $new_activo,
                ':uid' => $user_id,
                ':rid' => $restaurante_id
            ];
            
            // Agregar la fecha de salida si se está desactivando al usuario
            if ($new_activo == 0 && empty($_POST['fecha_salida'])) {
                 $sql .= ", fecha_salida = CURDATE()";
            } elseif ($new_activo == 1) {
                 $sql .= ", fecha_salida = NULL"; // Limpiar si se reactiva
            }
            
            $sql .= " WHERE usuario_id = :uid AND restaurante_id = :rid";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            logActivity($_SESSION['user_id'], 'UPDATE ASSIGNMENT', "Actualizó asignación de Usuario ID: {$user_id}. Nuevo Rol: {$new_rol_id}, Activo: {$new_activo}");
            $message = "✅ Asignación de usuario actualizada con éxito.";
        }

        // C. Lógica de ELIMINACIÓN (Quitar asignación del restaurante)
        if ($action == 'delete_assignment') {
             $pdo->beginTransaction();
             
             // NOTA: Usamos ON DELETE CASCADE para asegurar que si el usuario o restaurante desaparece, la FK se limpia.
             // Aquí solo eliminamos la relación.
            $stmt = $pdo->prepare("
                DELETE FROM usuarios_restaurantes 
                WHERE usuario_id = :uid AND restaurante_id = :rid
            ");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->execute();
            
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'DELETE ASSIGNMENT', "Eliminó asignación de Usuario ID: {$user_id} del restaurante.");
            $message = "✅ Usuario desvinculado del restaurante con éxito.";
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // UNIQUE constraint error (ya existe la asignación)
        if ($e->getCode() == '23000') {
            $error_message = 'Este usuario ya tiene un rol asignado en este restaurante.';
        } else {
            $error_message = "Error en la operación de personal: " . $e->getMessage();
            error_log("DB Error GESTION PERSONAL: " . $e->getMessage());
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ y Obtención de datos para formularios)
// ----------------------------------------------------------------------

try {
    // 1. Obtener la lista de personal ASIGNADO al restaurante actual
    $stmt_personal = $pdo->prepare("
        SELECT u.id AS usuario_id, u.nombre_completo, r.nombre AS rol_nombre, r.id AS rol_id, ur.activo, ur.fecha_ingreso, ur.fecha_salida, r.jerarquia
        FROM usuarios_restaurantes ur
        JOIN usuarios u ON ur.usuario_id = u.id
        JOIN roles r ON ur.rol_id = r.id
        WHERE ur.restaurante_id = :rid
        ORDER BY r.jerarquia ASC
    ");
    $stmt_personal->bindParam(':rid', $restaurante_id);
    $stmt_personal->execute();
    $personal_list = $stmt_personal->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la jerarquía del usuario logueado
    $stmt_personal = $pdo->prepare("
        SELECT u.id AS usuario_id, u.nombre_completo, r.nombre AS rol_nombre, r.id AS rol_id, ur.activo, ur.fecha_ingreso, ur.fecha_salida, r.jerarquia
        FROM usuarios_restaurantes ur
        JOIN usuarios u ON ur.usuario_id = u.id
        JOIN roles r ON ur.rol_id = r.id
        WHERE ur.restaurante_id = :rid
        ORDER BY r.jerarquia ASC
    ");
    $stmt_personal->bindParam(':rid', $restaurante_id);
    $stmt_personal->execute();
    $personal_list = $stmt_personal->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener roles filtrados por jerarquía
    $stmt_roles = $pdo->query("SELECT id, nombre, jerarquia FROM roles ORDER BY jerarquia ASC");
    $roles_list = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener la lista de Usuarios NO ASIGNADOS al restaurante actual (para el formulario CREATE)
    $stmt_unassigned = $pdo->prepare("
        SELECT id, nombre_completo 
        FROM usuarios 
        WHERE id NOT IN (
            SELECT usuario_id FROM usuarios_restaurantes WHERE restaurante_id = :rid
        ) 
        AND activo = TRUE AND id != 101 -- Excluir Super Admin
        ORDER BY nombre_completo
    ");
    $stmt_unassigned->bindParam(':rid', $restaurante_id);
    $stmt_unassigned->execute();
    $unassigned_users = $stmt_unassigned->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar datos del sistema: " . $e->getMessage();
}

?>

<h1>Asignación de Personal</h1>
<p class="current-context">Restaurante Activo: 
    <strong><?php echo getRestaurantName($restaurante_id); ?></strong>
</p>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<h2>Asignar Nuevo Empleado</h2>
<form action="" method="POST" class="form-crud">
    <input type="hidden" name="action" value="create_assignment">
    
    <label for="usuario_id">Seleccionar Usuario:</label>
    <select name="usuario_id" id="usuario_id" required>
        <option value="">-- Seleccionar --</option>
        <?php foreach ($unassigned_users as $user): ?>
            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['nombre_completo']); ?> (ID: <?php echo $user['id']; ?>)</option>
        <?php endforeach; ?>
    </select>

    <label for="rol_id_create">Rol a Asignar:</label>
    <select name="rol_id" id="rol_id_create" required>
        <option value="">-- Seleccionar Rol --</option>
        <?php foreach ($roles_list as $rol): ?>
                 <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?> (J<?php echo $rol['jerarquia']; ?>)</option>
        <?php endforeach; ?>
    </select>
    
    <button type="submit" class="btn btn-primary">Asignar Empleado</button>
</form>

<hr>

<h2>Personal Asignado</h2>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Rol Actual</th>
            <th>Jerarquía</th>
            <th>Ingreso</th>
            <th>Estado</th>
            <th>Acciones / Editar</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($personal_list as $p): ?>
            <tr>
                <td><?php echo $p['usuario_id']; ?></td>
                <td><?php echo htmlspecialchars($p['nombre_completo']); ?></td>
                
                <td colspan="4">
                    <form action="" method="POST" style="display:inline-flex; gap: 10px; align-items:center;">
                        <input type="hidden" name="action" value="update_assignment">
                        <input type="hidden" name="usuario_id" value="<?php echo $p['usuario_id']; ?>">
                        
                        <label for="rol_<?php echo $p['usuario_id']; ?>">Rol:</label>
                        <select name="new_rol_id" id="rol_<?php echo $p['usuario_id']; ?>" required>
                            <?php foreach ($roles_list as $rol): ?>
                                    <option value="<?php echo $rol['id']; ?>" <?php echo ($rol['id'] == $p['rol_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol['nombre']); ?> (J<?php echo $rol['jerarquia']; ?>)
                                    </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Activo:</label>
                        <input type="checkbox" name="activo" value="1" <?php echo ($p['activo']) ? 'checked' : ''; ?>>
                        
                        <?php if (!$p['activo'] && $p['fecha_salida']): ?>
                            <small>Salida: <?php echo $p['fecha_salida']; ?></small>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                    </form>
                </td>
                
                <td>
                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de desvincular a <?php echo htmlspecialchars($p['nombre_completo']); ?> de este restaurante? Esto no elimina su cuenta, solo la asignación.')">
                        <input type="hidden" name="action" value="delete_assignment">
                        <input type="hidden" name="usuario_id" value="<?php echo $p['usuario_id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Desvincular</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>