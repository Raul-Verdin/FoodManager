<?php
require_once '../includes/auth.php';
requireLogin(); // Solo pedimos que esté logueado
include('../includes/header.php');
?>

<h1>Mesas</h1>

<!-- Contenido específico para empleados -->
<p>Aquí puedes ver mesas disponibles.</p>

<?php include('../includes/footer.php'); ?>
