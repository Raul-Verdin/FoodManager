<?php
// header.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FoodManager - Panel</title>
    <link rel="icon" type="image/x-icon" href="logo/favicon.ico" />
    <link rel="stylesheet" href="style/style.css" /> <!-- CSS general -->
</head>
<body>

<!-- Barra de navegación lateral -->
<aside class="sidebar">
    <div class="logo-container">
        <img src="logo/logo.png" alt="Logo del Restaurante" class="logo" />
        <h2>El Nopalito</h2>
    </div>
    <nav>
        <ul>
            <li><a href="../ViewEmp/dashboard.php">Inicio</a></li>
            <li><a href="../ViewManage/reservas.php">Reservas</a></li>
            <li><a href="../ViewManage/menu.php">Menú</a></li>
            <li><a href="../ViewManage/quienes-somos.php">Quiénes Somos</a></li>
            <li><a href="../ViewManage/contacto.php">Contacto</a></li>
            <li><a href="../ViewManage/contacto.php">Gestion</a></li>
            <li><a href="../ViewManage/contacto.php">cuentas</a></li>
            <li><a href="../logout.php">Cerrar sesión</a></li>
        </ul>
    </nav>
</aside>

<!-- Contenido Principal -->
<div class="main-content">
