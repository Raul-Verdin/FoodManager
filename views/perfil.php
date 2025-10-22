<?php
// Desactivamos el switcher de restaurante para esta vista
$disable_restaurant_switcher = true;

include '../includes/header.php'; 

$message = '';
$error_message = '';
$usuario_id = $_SESSION['user_id'];

// ----------------------------------------------------------------------
// Lógica de Lectura (READ) - Obtener datos actuales del usuario
// ----------------------------------------------------------------------
try {
    $stmt = $pdo->prepare("SELECT nombre_completo, email FROM usuarios WHERE id = :uid");
    $stmt->bindParam(':uid', $usuario_id);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $error_message = "Error: Datos de usuario no encontrados.";
        // Si no se encuentran los datos, algo grave pasó.
        include '../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Error al cargar el perfil: " . $e->getMessage();
}


// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE: Datos Personales)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_info') {
    $nuevo_nombre = trim($_POST['nombre_completo']);
    $nuevo_email = trim($_POST['email']);

    if (empty($nuevo_nombre) || empty($nuevo_email) || !filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Por favor, ingresa un nombre y un email válidos.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre_completo = :nombre, email = :email WHERE id = :uid");
            $stmt->execute([
                ':nombre' => $nuevo_nombre,
                ':email' => $nuevo_email,
                ':uid' => $usuario_id
            ]);

            // Actualizar la sesión para que los cambios se reflejen inmediatamente
            $_SESSION['user_full_name'] = $nuevo_nombre;
            
            logActivity($usuario_id, 'PROFILE UPDATE', "Datos personales actualizados.");
            $message = "✅ Tu información personal ha sido actualizada correctamente.";
            
            // Recargar datos para el formulario
            $user_data['nombre_completo'] = $nuevo_nombre;
            $user_data['email'] = $nuevo_email;

        } catch (PDOException $e) {
            $error_message = "Error al actualizar información: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (UPDATE: Contraseña)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_password') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';

    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error_message = "Todos los campos de contraseña son obligatorios.";
    } elseif ($password_nueva !== $password_confirmar) {
        $error_message = "La nueva contraseña y su confirmación no coinciden.";
    } elseif (strlen($password_nueva) < 6) {
        $error_message = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // 1. Verificar la contraseña actual
            $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = :uid");
            $stmt->bindParam(':uid', $usuario_id);
            $stmt->execute();
            $hash_actual = $stmt->fetchColumn();

            if (!password_verify($password_actual, $hash_actual)) {
                $error_message = "La contraseña actual ingresada es incorrecta.";
            } else {
                // 2. Generar el nuevo hash y actualizar la base de datos
                $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE usuarios SET password_hash = :new_hash WHERE id = :uid");
                $stmt_update->execute([':new_hash' => $nuevo_hash, ':uid' => $usuario_id]);

                logActivity($usuario_id, 'PASSWORD CHANGE', "Contraseña cambiada exitosamente.");
                $message = "✅ Tu contraseña ha sido actualizada correctamente.";
            }

        } catch (PDOException $e) {
            $error_message = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/perfil.css">
</head>

<h1>Mi Perfil de Usuario</h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<div class="perfil-container">
    <div class="perfil-card card card-primary">
        <div class="card-header">
            <h2><i class="fas fa-user-edit"></i> Actualizar Información</h2>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_info">
                
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo:</label>
                    <input type="text" class="form-control" name="nombre_completo" 
                           value="<?php echo htmlspecialchars($user_data['nombre_completo'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" name="email" 
                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <div class="perfil-card card card-warning">
        <div class="card-header">
            <h2><i class="fas fa-lock"></i> Cambiar Contraseña</h2>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label for="password_actual">Contraseña Actual:</label>
                    <input type="password" class="form-control" name="password_actual" required>
                </div>
                
                <div class="form-group">
                    <label for="password_nueva">Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="password_nueva" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="password_confirmar">Confirmar Nueva Contraseña:</label>
                    <input type="password" class="form-control" name="password_confirmar" required>
                </div>
                
                <button type="submit" class="btn btn-warning btn-block">Cambiar Contraseña</button>
            </form>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>