<?php
require_once 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FoodManager - Panel</title>
    <link rel="icon" type="image/x-icon" href="logo/favicon.ico" />
    <link rel="stylesheet" href="../style/style.css" />
</head>
<body>

<!-- Barra de navegación lateral -->
<aside class="sidebar">
    <div class="logo-container">
        <img src="logo/logo.png" alt="Logo del Restaurante" class="logo" />
        <h2>Food Manager</h2>
    </div>

    <!-- 👤 Usuario logueado -->
    <div class="user-info">
        <p>👤 Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario']); ?></strong></p>
        <p>Rol: <strong><?php echo ucfirst($_SESSION['rol']); ?></strong></p>
    </div>

    <!-- Navegación -->
    <nav>
        <ul>
            <li><a href="dashboard.php">Inicio</a></li>
            <li><a href="contacto.php">Contacto</a></li>
            <li><a href="mesas.php">Mesas</a></li>
            <li><a href="menu.php">Menú</a></li>
            <li><a href="registrar_pedidos.php">Registrar Pedidos</a></li>
            
            <?php if (userHasRole('manager')): ?>
                <li><a href="gestion_empleados.php">Gestión de Empleados</a></li>
                <li><a href="gestion_pedidos.php">Gestión de Pedidos</a></li>
                <li><a href="gestion_suministros.php">Gestión de Suministros</a></li>
                <li><a href="gestion_menu.php">Gestión de Menú</a></li>
            <?php endif; ?>

            <li><a href="../logout.php">Cerrar sesión</a></li>
        </ul>
    </nav>
</aside>

<!-- Contenido principal -->
<div class="main-content">
