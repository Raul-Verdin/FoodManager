<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'manager') {
    header("Location: ../index_login.php");
    exit();
}

include("header.php");

?>

<h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']); ?></h1>
<p>Esta es la página principal del Manager.</p>

<?php include("footer.php"); ?>
