<?php
// Incluimos la lógica de autenticación y conexión
require_once 'includes/auth.php'; 

// Si el usuario ya está logueado, lo redirigimos al dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: views/dashboard.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Por favor, ingresa usuario y contraseña.";
    } else {
        try {
            // 1. Obtener los datos base del usuario (Contraseña, ID)
            $stmt = $pdo->prepare("
                SELECT id, contrasena
                FROM usuarios
                WHERE nombre_usuario = :username AND activo = TRUE
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user_base = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_base && password_verify($password, $user_base['contrasena'])) {
                
                // 2. Si la contraseña es válida, obtener sus asignaciones y el rol de mayor jerarquía.
                $stmt_roles = $pdo->prepare("
                    SELECT ur.rol_id, ur.restaurante_id, r.jerarquia
                    FROM usuarios_restaurantes ur
                    JOIN roles r ON ur.rol_id = r.id
                    WHERE ur.usuario_id = :user_id
                    ORDER BY r.jerarquia ASC
                ");
                $stmt_roles->bindParam(':user_id', $user_base['id'], PDO::PARAM_INT);
                $stmt_roles->execute();
                $roles_data = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

                // Lógica para Super Administrador (ID 101, Jerarquía 1) que no tiene asignación en ur
                if (empty($roles_data) && $user_base['id'] == 101) {
                    // Asignar datos del Super Administrador manualmente
                    $super_admin_rol = $pdo->query("SELECT id, jerarquia FROM roles WHERE nombre = 'super_administrador'")->fetch(PDO::FETCH_ASSOC);
                    
                    $_SESSION['user_id'] = $user_base['id'];
                    $_SESSION['user_rol_id'] = $super_admin_rol['id'];
                    $_SESSION['user_jerarquia'] = $super_admin_rol['jerarquia'];
                    $_SESSION['restaurante_actual_id'] = null; // No tiene un restaurante inicial
                    // (Añadir nombre completo y rol para el header.php)
                    $_SESSION['user_full_name'] = 'Global Admin';
                    $_SESSION['user_rol_name'] = 'Super Administrador';
                    
                    header("Location: views/dashboard.php");
                    exit;
                }
                
                // Lógica para usuarios normales (tomar el rol de menor jerarquía para el login inicial)
                if (!empty($roles_data)) {
                    $primary_role = $roles_data[0]; // La primera fila es la de menor jerarquía (mayor privilegio)
                    
                    $_SESSION['user_id'] = $user_base['id'];
                    $_SESSION['user_rol_id'] = $primary_role['rol_id'];
                    $_SESSION['user_jerarquia'] = $primary_role['jerarquia'];
                    $_SESSION['restaurante_actual_id'] = $primary_role['restaurante_id']; 
                    // (Añadir nombre completo y rol para el header.php)
                    // Necesitarás una consulta adicional para obtener el nombre completo y el nombre del rol.
                    
                    header("Location: views/dashboard.php");
                    exit;
                }
                
                // Si la contraseña es correcta, pero el usuario no tiene ninguna asignación válida
                $error_message = "Usuario sin rol asignado o inactivo.";

            } else {
                $error_message = "Usuario o contraseña incorrectos.";
            }

        } catch (PDOException $e) {
            $error_message = "Error en el sistema. Inténtalo de nuevo más tarde.";
            error_log("Error de login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FoodManager - Inicio de Sesión</title>
    <link rel="stylesheet" href="style/login.css"> </head>
<body>
    <div class="login-container">
        <h1>FoodManager</h1>
        <p>Gestión de Restaurantes</p>
        
        <?php if ($error_message): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <input type="text" name="username" placeholder="Nombre de Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <p class="forgot-password">
            <a href="views/recuperacion.php">¿Olvidaste tu contraseña?</a>
        </p>
    </div>
</body>
</html>