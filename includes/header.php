<?php
// Asegura que el usuario esté autenticado antes de cargar cualquier vista
require_once 'auth.php';
checkAuth();

// --- VARIABLES DE SESIÓN CLAVE ---
$user_name = $_SESSION['user_full_name'] ?? 'Usuario';
$user_rol = $_SESSION['user_rol_name'] ?? 'Rol Desconocido';
$restaurante_actual_id = $_SESSION['restaurante_actual_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodManager - Dashboard</title>
    <link rel="stylesheet" href="../style/main.css">
    <link rel="stylesheet" href="../style/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div id="wrapper">
    <aside id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-utensils"></i> FoodManager</h3>
            <p class="user-info">
                <?php echo htmlspecialchars($user_name); ?><br>
                <small><?php echo htmlspecialchars($user_rol); ?></small>
            </p>
        </div>

        <nav class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

                <?php if (checkPermission('gestion_plataforma')): ?>
                    <li class="menu-heading">Administración Global</li>
                    <li><a href="gestion_restaurantes.php"><i class="fas fa-store"></i> Restaurantes</a></li>
                    <li><a href="gestion_roles_permisos.php"><i class="fas fa-book-open"></i> Roles y Permisos </a></li>
                    <li><a href="gestion_gerentes.php"><i class="fas fa-user-tie"></i> Gerentes (R2)</a></li>
                <?php endif; ?>

                <?php if (checkPermission('gestion_personal') || checkPermission('gestion_inventario_total')): ?>
                    <li class="menu-heading">Gestión Operativa</li>
                    
                    <?php if (checkPermission('gestion_personal')): ?>
                        <li><a href="gestion_asignar_personal.php"><i class="fas fa-users"></i> Asignar Personal</a></li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('gestion_personal')): ?>
                        <li><a href="gestion_usuarios.php"><i class="fas fa-users"></i> Personal</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('gestion_inventario_total')): ?>
                        <li><a href="gestion_inventario.php"><i class="fas fa-boxes"></i> Inventario</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('gestion_menu')): ?>
                        <li><a href="gestion_menu.php"><i class="fas fa-book-open"></i> Menú/Recetas</a></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (checkPermission('ver_reportes_financieros') || checkPermission('gestion_nomina')): ?>
                    <li class="menu-heading">Finanzas y RRHH</li>
                    
                    <?php if (checkPermission('ver_reportes_financieros')): ?>
                        <li><a href="reportes_actividad.php"><i class="fas fa-chart-line"></i> Reporte Actividad</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('ver_reportes_financieros')): ?>
                        <li><a href="reportes_financieros.php"><i class="fas fa-chart-line"></i> Reportes Financieros</a></li>
                    <?php endif; ?>
                    
                    <?php if (checkPermission('gestion_nomina')): ?>
                        <li><a href="gestion_nomina.php"><i class="fas fa-money-check-alt"></i> Nómina</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (checkPermission('acceso_pos') || checkPermission('gestion_mesas_reservas') || checkPermission('ver_kds')): ?>
                    <li class="menu-heading">Operaciones Diarias</li>
                    
                    <?php if (checkPermission('gestion_mesas_reservas')): ?>
                        <li><a href="gestion_mesas.php"><i class="fas fa-chair"></i> Mesas</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('gestion_mesas_reservas')): ?>
                        <li><a href="gestion_reservas.php"><i class="fas fa-chair"></i> Reservas</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('acceso_pos')): ?>
                        <li><a href="pos.php"><i class="fas fa-cash-register"></i> Punto de Venta (POS)</a></li>
                    <?php endif; ?>

                    <?php if (checkPermission('ver_kds')): ?>
                        <li><a href="kds.php"><i class="fas fa-tablet-alt"></i> KDS (Cocina)</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
       
    </aside>
    <main id="content">
        
        <header id="main-header">
            <div class="header-left">
                <?php if ($_SESSION['user_jerarquia'] <= 2): ?>
                    <?php include 'restaurant_switcher.php'; ?>
                <?php else: ?>
                    <span class="current-res-name"><i class="fas fa-store"></i> <?php echo getRestaurantName($restaurante_actual_id); ?></span>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <a href="#"><i class="fas fa-bell"></i></a>
                <div class="user-menu">
                    <i class="fas fa-user-circle user-icon" id="userMenuBtn"></i>

                    <div class="user-popup" id="userPopup">
                        <p><strong><?php echo htmlspecialchars($user_name); ?></strong></p>
                        <p><?php echo htmlspecialchars($user_rol); ?></p>
                        <hr>
                        <a href="../views/perfil.php"><i class="fas fa-user"></i> Ver perfil</a>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                    </div>
                </div>
            </div>

        </header>

        <div class="page-content">

