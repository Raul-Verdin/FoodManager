<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("BD/conexion.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $usuario_data = $resultado->fetch_assoc();

        if (password_verify($contrasena, $usuario_data['contrasena'])) {
            $_SESSION['usuario'] = $usuario_data['nombre_usuario'];
            $_SESSION['rol'] = $usuario_data['rol'];
            header("Location: views/dashboard.php");
            exit();
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Login - FoodManager</title>
  <link rel="stylesheet" href="style/auth.css" />
</head>
<body>
  <div class="auth-container">
    <h2>Iniciar Sesión</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="text" name="usuario" placeholder="Usuario" required>
      <input type="password" name="contrasena" placeholder="Contraseña" required>
      <input type="submit" value="Iniciar Sesión">
    </form>

    <div class="switch-link">
      ¿No tienes cuenta? <a href="registro.php">Registrarse</a>
    </div>
  </div>
</body>
</html>
