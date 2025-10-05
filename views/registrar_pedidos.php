<?php
require_once '../includes/auth.php';
requireLogin(); // Solo pedimos que esté logueado
include('../includes/header.php');
?>

<h1>Gestión de Pedidos</h1>

<!-- Aquí puede interactuar tanto empleados como managers -->
<form action="procesar_pedido.php" method="post">
    <label for="mesa">Mesa:</label>
    <input type="text" name="mesa" required>
    <label for="pedido">Pedido:</label>
    <textarea name="pedido" required></textarea>
    <input type="submit" value="Enviar Pedido">
</form>

<?php include('../includes/footer.php'); ?>
