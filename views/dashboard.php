<?php
require_once '../includes/auth.php';
requireLogin();
include('../includes/header.php');
?>

<h1>Bienvenido a FoodManager</h1>

<?php if (userHasRole('manager')): ?>
    <p>Acceso como Manager. Puedes gestionar empleados y otros recursos.</p>
<?php else: ?>
    <p>Acceso como Empleado. Puedes ver tus pedidos y gestionarlos.</p>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
