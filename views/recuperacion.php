<?php
require_once '../includes/auth.php'; // Incluye la conexión y auth functions

$message = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request'; // request | reset

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // =========================================================
    // LÓGICA DE SOLICITUD DE RECUPERACIÓN (Paso 1)
    // =========================================================
    if ($step == 'request' && isset($_POST['username'])) {
        $username = trim($_POST['username']);
        
        // 1. Buscar al usuario y su rol de menor jerarquía (el principal)
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, r.jerarquia
            FROM usuarios u
            LEFT JOIN usuarios_restaurantes ur ON u.id = ur.usuario_id
            JOIN roles r ON ur.rol_id = r.id
            WHERE u.nombre_usuario = :username
            ORDER BY r.jerarquia ASC LIMIT 1
        ");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $user_id = $user_data['id'];
            $jerarquia = $user_data['jerarquia'];
            $email = $user_data['email'];

            // Lógica basada en Jerarquía:
            
            // J4 en adelante (Meseros, Cocineros, etc.) y J1 (Super Admin)
            if ($jerarquia >= 4 || $jerarquia == 1) { 
                // Flujo de auto-recuperación (simulamos envío de código al email)
                
                // Generar un token temporal para el enlace de restablecimiento (diferente al de autorización)
                $temp_token = bin2hex(random_bytes(16)); 
                $token_expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Actualizar la tabla usuarios con el token (solo para J1 y J4+)
                $update_stmt = $pdo->prepare("UPDATE usuarios SET token_recuperacion = :token, token_expira = :expira WHERE id = :id");
                $update_stmt->bindParam(':token', $temp_token);
                $update_stmt->bindParam(':expira', $token_expira);
                $update_stmt->bindParam(':id', $user_id);
                $update_stmt->execute();

                // Simulamos el envío de correo. En la vida real, se envía el email.
                logActivity($user_id, 'RECOVERY REQUEST', 'Token de auto-recuperación generado y enviado.');
                $message = "Se ha enviado un código de verificación a tu correo ({$email}). Revisa tu bandeja de entrada.";
                // En el email real: link_restablecimiento.php?token={$temp_token}

            } 
            
            // J2 y J3 (Requieren autorización de superior)
            else { 
                // Se registra la solicitud y se informa al usuario.
                // Aquí usamos la tabla solicitudes_recuperacion para la trazabilidad
                
                $message_admin = ($jerarquia == 2) ? "Super Administrador (J1)" : "Gerente de Restaurante (J2)";
                
                // NO GENERAMOS EL TOKEN AQUÍ, SOLO REGISTRAMOS LA NECESIDAD
                logActivity($user_id, 'RECOVERY REQUEST PENDING', "Usuario J{$jerarquia} solicitó recuperación. Requiere token de {$message_admin}.");
                
                $message = "Tu cuenta requiere autorización de un superior ({$message_admin}) para restablecer la contraseña. Se les ha notificado para que generen tu token.";
            }

        } else {
            $message = "Usuario no encontrado.";
        }
    } 
    
    // =========================================================
    // LÓGICA DE RESTABLECIMIENTO (Paso 3)
    // =========================================================
    else if ($step == 'reset' && isset($_POST['token']) && isset($_POST['password'])) {
        $token = trim($_POST['token']);
        $new_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $message = "Las contraseñas no coinciden.";
        } else if (strlen($new_password) < 8) {
             $message = "La contraseña debe tener al menos 8 caracteres.";
        } else {
            
            // 1. Buscar en la tabla solicitudes_recuperacion (Tokens generados por superior para J2/J3)
            $stmt = $pdo->prepare("
                SELECT usuario_id FROM solicitudes_recuperacion
                WHERE token = :token AND usado = FALSE AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            $user_id = $stmt->fetchColumn();

            // 2. Si no se encuentra en solicitudes_recuperacion (Tokens de superior), buscar en usuarios (Tokens de auto-recuperación J1/J4+)
            if (!$user_id) {
                 $stmt = $pdo->prepare("
                    SELECT id FROM usuarios
                    WHERE token_recuperacion = :token AND token_expira > NOW()
                ");
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                $user_id = $stmt->fetchColumn();
            }

            if ($user_id) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                
                // Iniciar Transacción SQL para asegurar la limpieza del token
                $pdo->beginTransaction();
                
                try {
                    // Actualizar contraseña y limpiar token en la tabla usuarios
                    $update_user = $pdo->prepare("UPDATE usuarios SET contrasena = :hash, token_recuperacion = NULL, token_expira = NULL WHERE id = :id");
                    $update_user->bindParam(':hash', $new_hash);
                    $update_user->bindParam(':id', $user_id);
                    $update_user->execute();
                    
                    // Marcar el token como usado en la tabla solicitudes_recuperacion (si aplica)
                    $update_solicitud = $pdo->prepare("UPDATE solicitudes_recuperacion SET usado = TRUE WHERE token = :token");
                    $update_solicitud->bindParam(':token', $token);
                    $update_solicitud->execute();

                    $pdo->commit();

                    logActivity($user_id, 'PASSWORD RESET SUCCESS', 'Contraseña restablecida correctamente.');
                    $message = "✅ ¡Contraseña restablecida con éxito! Serás redirigido al inicio de sesión.";
                    header("Refresh: 3; url=../index.php"); // Redirigir al login
                    exit;

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Ocurrió un error al intentar restablecer la contraseña.";
                    error_log("Error de restablecimiento: " . $e->getMessage());
                }
            } else {
                $message = "❌ Token inválido o expirado. Por favor, solicita una nueva recuperación.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de Contraseña</title>
    <link rel="stylesheet" href="../style/login_style.css"> 
</head>
<body>
    <div class="login-container">
        <h1>Recuperar Acceso</h1>
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($step == 'request'): ?>
            <p>Ingresa tu nombre de usuario para iniciar el proceso de recuperación.</p>
            <form action="recuperacion.php?step=request" method="POST">
                <input type="text" name="username" placeholder="Nombre de Usuario" required>
                <button type="submit">Solicitar Recuperación</button>
            </form>
            <p><a href="../index.php">Volver al Login</a></p>

        <?php elseif ($step == 'reset'): ?>
            <p>Ingresa el token de recuperación y tu nueva contraseña.</p>
            <form action="recuperacion.php?step=reset" method="POST">
                <input type="text" name="token" placeholder="Token de Recuperación" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>" required>
                <input type="password" name="password" placeholder="Nueva Contraseña" required>
                <input type="password" name="confirm_password" placeholder="Confirmar Contraseña" required>
                <button type="submit">Restablecer Contraseña</button>
            </form>
            <p><a href="../index.php">Volver al Login</a></p>
            
        <?php endif; ?>
    </div>
</body>
</html>