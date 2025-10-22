<?php
include '../includes/header.php'; 

// Permiso requerido: Mismo que para la gestión de personal dentro de un restaurante.
$permission_needed = 'gestion_personal'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a {$permission_needed} (Usuarios Base)");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede gestionar usuarios base.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE / UPDATE / DELETE)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $usuario_id = (int)$_POST['usuario_id'] ?? 0;

    try {
        // A. Lógica de CREACIÓN de Usuario
        if ($action == 'create_user') {
            $nombre_usuario = trim($_POST['nombre_usuario']);
            $nombre_completo = trim($_POST['nombre_completo']);
            $email = trim($_POST['email']);
            $telefono = trim($_POST['telefono']);
            $password = $_POST['contrasena'];
            
            if (empty($nombre_usuario) || empty($nombre_completo) || empty($password)) {
                throw new Exception('Todos los campos obligatorios deben ser llenados.');
            }
            
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre_usuario, contrasena, nombre_completo, email, telefono, verificado, activo) 
                VALUES (:user, :hash, :full_name, :email, :phone, TRUE, TRUE)
            ");
            $stmt->bindParam(':user', $nombre_usuario);
            $stmt->bindParam(':hash', $password_hash);
            $stmt->bindParam(':full_name', $nombre_completo);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $telefono);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'CREATE USER', "Usuario base creado: " . $nombre_usuario);
            $message = "✅ Usuario '{$nombre_usuario}' creado con éxito. Ya puede ser asignado a un restaurante.";
        }
        
        // B. Lógica de ACTUALIZACIÓN de Usuario
        if ($action == 'update_user') {
            $nombre_completo = trim($_POST['nombre_completo']);
            $email = trim($_POST['email']);
            $telefono = trim($_POST['telefono']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if ($usuario_id === 101) { // Protege al Super Administrador de ser modificado por otros
                 throw new Exception("No puedes modificar al Super Administrador.");
            }
            
            $stmt = $pdo->prepare("
                UPDATE usuarios SET nombre_completo = :full_name, email = :email, telefono = :phone, activo = :activo 
                WHERE id = :id
            ");
            $stmt->bindParam(':full_name', $nombre_completo);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $telefono);
            $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'UPDATE USER', "Usuario ID: {$usuario_id} actualizado.");
            $message = "✅ Usuario base ID {$usuario_id} actualizado con éxito.";
        }

        // C. Lógica de ELIMINACIÓN de Usuario (TRANSACCIÓN SEGURA)
        if ($action == 'delete_user') {
            if ($usuario_id === 101) {
                 throw new Exception("El Super Administrador no puede ser eliminado.");
            }
            
            $pdo->beginTransaction();
            
            // 1. Eliminación de Asignaciones (usuarios_restaurantes)
            // Aunque las FK deberían manejar esto, es bueno ser explícito y registrar.
            $stmt_ur = $pdo->prepare("DELETE FROM usuarios_restaurantes WHERE usuario_id = :id");
            $stmt_ur->bindParam(':id', $usuario_id);
            $stmt_ur->execute();
            
            // 2. Eliminación del Usuario Base
            $stmt_u = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt_u->bindParam(':id', $usuario_id);
            $stmt_u->execute();
            
            // 3. Confirmar la Transacción
            $pdo->commit();
            
            logActivity($_SESSION['user_id'], 'DELETE USER', "Usuario ID: {$usuario_id} eliminado de la plataforma.");
            $message = "✅ Cuenta de usuario ID {$usuario_id} eliminada de forma segura.";

        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

try {
    // Obtener todos los usuarios, excluyendo el Super Administrador (ID 101 en nuestro seed)
    $stmt_users = $pdo->query("
        SELECT id, nombre_usuario, nombre_completo, email, telefono, activo 
        FROM usuarios 
        WHERE id != 101 
        ORDER BY nombre_completo ASC
    ");
    $users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de usuarios base.";
}
?>

<h1>Registrar Nuevo Empleado</h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<form action="gestion_usuarios.php" method="POST" class="form-crud">
    <input type="hidden" name="action" value="create_user">
    
    <div class="form-group">
        <label for="nombre_usuario">Usuario:</label>
        <input type="text" name="nombre_usuario" required>
    </div>
    <div class="form-group">
        <label for="contrasena">Contraseña:</label>
        <input type="password" name="contrasena" required minlength="8">
    </div>
    <div class="form-group">
        <label for="nombre_completo">Nombre Completo:</label>
        <input type="text" name="nombre_completo" required>
    </div>
    <div class="form-group">
        <label for="email">Email (Contacto):</label>
        <input type="email" name="email">
    </div>
    <div class="form-group">
        <label for="telefono">Teléfono:</label>
        <input type="text" name="telefono">
    </div>
    
    <button type="submit" class="btn btn-primary">Crear Cuenta Base</button>
</form>

<hr>

<h2>Cuentas Registradas</h2>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Nombre Completo</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users_list as $u): ?>
            <tr>
                <form action="gestion_usuarios.php" method="POST" style="display:contents;">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="usuario_id" value="<?php echo $u['id']; ?>">
                    
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['nombre_usuario']); ?></td>
                    <td><input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($u['nombre_completo']); ?>" required></td>
                    <td><input type="email" name="email" value="<?php echo htmlspecialchars($u['email']); ?>"></td>
                    <td><input type="text" name="telefono" value="<?php echo htmlspecialchars($u['telefono']); ?>"></td>
                    <td>
                        <select name="activo">
                            <option value="1" <?php echo ($u['activo'] == 1) ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($u['activo'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nombre_completo']); ?>')">Eliminar</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function confirmDelete(userId, userName) {
    if (confirm('ADVERTENCIA: ¿Está seguro de eliminar la cuenta base de ' + userName + '? Esto eliminará también todas sus asignaciones a restaurantes. Esta acción es irreversible.')) {
        // Crea un formulario dinámico para enviar la solicitud de eliminación POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gestion_usuarios.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'usuario_id';
        idInput.value = userId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>