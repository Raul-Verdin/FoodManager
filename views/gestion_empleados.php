<?php
require_once '../includes/auth.php';
requireLogin();
include('../includes/header.php');
?>

<h1>Gestión de Empleados</h1>

<?php 
if (userHasRole('manager')) {
    // Mostrar formulario o contenido editable solo para managers
    ?>
    <form action="guardar_empleado.php" method="post">
        <label for="nombre">Nombre del Empleado:</label>
        <input type="text" name="nombre" required>
        
        <label for="puesto">Puesto:</label>
        <input type="text" name="puesto" required>

        <input type="submit" value="Guardar">
    </form>
    <?php
} else {
    // Mostrar aviso para empleados que no tienen permiso
    showAccessDenied();
}
?>

<?php include('../includes/footer.php'); ?>
