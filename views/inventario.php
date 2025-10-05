<?php
require_once '../includes/auth.php';
requireRole('manager');
?>
<!-- Contenido exclusivo para el manager -->

<h1>Vista inventario</h1>

<a href="pedidos.php">Ver pedidos</a>
<a href="logout.php">Cerrar sesión</a>