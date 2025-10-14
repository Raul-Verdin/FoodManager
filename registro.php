<?php
include("BD/conexion.php");
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];

    $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $usuario, $contrasena, $rol);

    if ($stmt->execute()) {
        $success = "Usuario registrado correctamente. <a href='index.php'>Iniciar sesión</a>";
    } else {
        $error = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro - FoodManager</title>
  <link rel="stylesheet" href="style/auth.css" />
</head>
<body>
  <div class="auth-container">
    <h2>Registro</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="success" style="color: #4CAF50; text-align: center;"><?= $success ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="text" name="usuario" placeholder="Usuario" required>
      <input type="password" name="contrasena" placeholder="Contraseña" required>
      <select name="rol" required>
        <option value="manager">Manager</option>
        <option value="empleado">Empleado</option>
      </select>
      <input type="submit" value="Registrar">
    </form>

    <div class="switch-link">
      ¿Ya tienes cuenta? <a href="index.php">Iniciar Sesión</a>
    </div>
  </div>
</body>
</html>
