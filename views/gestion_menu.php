<?php
require_once '../includes/auth.php';
requireLogin();
include('../includes/header.php');
?>

<h1>Gestión del Menú</h1>

<?php 
if (userHasRole('manager')) {
    // Manager puede ver y usar el formulario
    ?>
    <form action="guardar_menu.php" method="post">
        <label for="nombre">Nombre del Platillo:</label>
        <input type="text" name="nombre" required>

        <label for="precio">Precio:</label>
        <input type="number" name="precio" step="0.01" required>

        <input type="submit" value="Guardar">
    </form>
    <?php
} else {
    // Mostrar aviso para empleados que no tienen permiso
    showAccessDenied();
}
?>

<?php include('../includes/footer.php'); ?>
