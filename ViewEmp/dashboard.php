<?php
session_start();
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['empleado', 'manager'])) {
    header("Location: ../index_login.php");
    exit();
}
include("header.php");

?>

<h1>Bienvenido Empleado: <?php echo $_SESSION['usuario']; ?></h1>
<p>Esta es la página principal del Empleado.</p>

<?php include("footer.php"); ?>