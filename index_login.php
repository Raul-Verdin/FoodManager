<?php
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

            // Redireccionar según rol
            if ($usuario_data['rol'] == 'manager') {
                header("Location: ViewManage/dashboard.php");
            } else {
                header("Location: ViewEmp/dashboard.php");
            }
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
</head>
<body>

<h2>Iniciar Sesión</h2>

<?php
if ($error != "") {
    echo "<p style='color:red;'>$error</p>";
}
?>

<form method="post" action="">
    Usuario: <input type="text" name="usuario" required><br><br>
    Contraseña: <input type="password" name="contrasena" required><br><br>
    <input type="submit" value="Iniciar Sesión">
</form>
<button onclick="window.location.href='registro.php';">Registrarse</button>

</body>
</html>
