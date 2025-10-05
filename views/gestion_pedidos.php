<?php
require_once '../includes/auth.php';
requireLogin();
include('../includes/header.php');
?>

<h1>Gestión de Pedidos</h1>

<?php 
if (userHasRole('manager')) {
    // Mostrar formulario o contenido editable solo para managers
    ?>
    <!-- Aqui va el contenido de Gestión de Pedidos -->
    <?php
} else {
    // Mostrar aviso para empleados que no tienen permiso
    showAccessDenied();
}
?>

<?php include('../includes/footer.php'); ?>
