<?php
require_once '../includes/auth.php';
requireLogin(); // Solo pedimos que esté logueado
include('../includes/header.php');
?>

<h1>Menu</h1>

<!-- Contenido específico para empleados -->
<p>Aquí puedes consultar el Menu.</p>

<?php include('../includes/footer.php'); ?>
