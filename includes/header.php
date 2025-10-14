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
    <link rel="icon" type="image/x-icon" href="../library/foodmanager_logo.ico" />
    <link rel="stylesheet" href="../style/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

<!-- Botón toggle para móviles -->
<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="overlay" id="overlay"></div>
    <div class="logo-container">
        <img src="../library/foodmanager_logo.ico" alt="Logo" class="logo" />
        <h2>Food Manager</h2>
    </div>

    <div class="user-info">
        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['usuario']); ?></p>
        <p><i class="fas fa-user-tag"></i> <?php echo ucfirst($_SESSION['rol']); ?></p>
    </div>

    <nav>
        <ul class="nav-list">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="contacto.php"><i class="fas fa-envelope"></i> Contacto</a></li>
            <li><a href="mesas.php"><i class="fas fa-chair"></i> Mesas</a></li>
            <li><a href="menu.php"><i class="fas fa-utensils"></i> Menú</a></li>
            <li><a href="registrar_pedidos.php"><i class="fas fa-receipt"></i> Registrar Pedidos</a></li>

            <?php if (userHasRole('manager')): ?>
                <li><a href="gestion_empleados.php"><i class="fas fa-users-cog"></i> Gestión de Empleados</a></li>
                <li><a href="gestion_pedidos.php"><i class="fas fa-tasks"></i> Gestión de Pedidos</a></li>
                <li><a href="gestion_suministros.php"><i class="fas fa-boxes"></i> Gestión de Suministros</a></li>
                <li><a href="gestion_menu.php"><i class="fas fa-hamburger"></i> Gestión de Menú</a></li>
            <?php endif; ?>

            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
        </ul>
    </nav>
</aside>

<!-- Contenido principal -->
<div class="main-content">
