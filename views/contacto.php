<?php
require_once '../includes/auth.php';
requireLogin(); // Solo pedimos que esté logueado
include('../includes/header.php');
?>

<h1>Contactos</h1>

<!-- Contenido específico para empleados -->
<p>Aquí puedes mandar un comentario o comunicarte con nosotros.</p>

<?php include('../includes/footer.php'); ?>
