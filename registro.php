<?php
include("BD/conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];

    $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $usuario, $contrasena, $rol);

    if ($stmt->execute()) {
        echo "Usuario registrado correctamente";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!-- HTML simple para registrar -->
<form method="post">
    Usuario: <input type="text" name="usuario" required><br>
    Contraseña: <input type="password" name="contrasena" required><br>
    Rol:
    <select name="rol">
        <option value="manager">Manager</option>
        <option value="empleado">Empleado</option>
    </select><br>
    <input type="submit" value="Registrar">
</form>
<button onclick="window.location.href='index.php';">Iniciar Sesion</button>