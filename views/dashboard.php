<?php
include '../includes/header.php'; 

// El header ya hizo checkAuth() y cargó la sesión.

$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$nombre_restaurante = getRestaurantName($restaurante_id);
$rol_nombre = $_SESSION['user_rol_name'];

// ----------------------------------------------------------------------
// Lógica de Lectura (KPIs y Alertas)
// ----------------------------------------------------------------------

$kpis = [
    'alerta_inventario' => 0,
    'total_empleados' => 0,
    'platos_inactivos' => 0
];

if ($restaurante_id) {
    try {
        // 1. Alerta de Inventario (KPI 1: Ítems bajo stock mínimo)
        $stmt_inv = $pdo->prepare("
            SELECT COUNT(id) 
            FROM inventario 
            WHERE restaurante_id = :rid AND stock_actual < stock_minimo
        ");
        $stmt_inv->bindParam(':rid', $restaurante_id);
        $stmt_inv->execute();
        $kpis['alerta_inventario'] = $stmt_inv->fetchColumn();

        // 2. Total de Empleados Activos (KPI 2)
        $stmt_emp = $pdo->prepare("
            SELECT COUNT(usuario_id) 
            FROM usuarios_restaurantes 
            WHERE restaurante_id = :rid AND activo = 1
        ");
        $stmt_emp->bindParam(':rid', $restaurante_id);
        $stmt_emp->execute();
        $kpis['total_empleados'] = $stmt_emp->fetchColumn();
        
        // 3. Platos Inactivos (KPI 3)
        $stmt_platos = $pdo->prepare("
            SELECT COUNT(id) 
            FROM platos_menu 
            WHERE restaurante_id = :rid AND activo = 0
        ");
        $stmt_platos->bindParam(':rid', $restaurante_id);
        $stmt_platos->execute();
        $kpis['platos_inactivos'] = $stmt_platos->fetchColumn();

    } catch (PDOException $e) {
        $error_message = "Error al cargar las métricas del dashboard.";
    }
}
?>

<h1>Dashboard | ¡Bienvenido, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>!</h1>
<p class="current-context">
    **Rol Actual:** *<?php echo htmlspecialchars($rol_nombre); ?>* <?php if ($restaurante_id): ?>
        | **Restaurante Activo:** *<?php echo htmlspecialchars($nombre_restaurante); ?>*
    <?php else: ?>
        | **Estado:** *Plataforma Global (sin restaurante seleccionado)*
    <?php endif; ?>
</p>

<?php if ($restaurante_id): ?>

<hr>

<h2>Métricas de Operación</h2>

<div class="kpi-container">
    
    <div class="kpi-card <?php echo ($kpis['alerta_inventario'] > 0) ? 'kpi-danger' : 'kpi-success'; ?>">
        <h3>Ítems bajo Mínimo</h3>
        <p class="kpi-value"><?php echo $kpis['alerta_inventario']; ?></p>
        <p class="kpi-periodo">
            <?php if (checkPermission('gestion_inventario_total')): ?>
                <a href="gestion_inventario.php">Revisar Inventario</a>
            <?php else: ?>
                Revisar con Gerente de Cocina.
            <?php endif; ?>
        </p>
    </div>
    
    <div class="kpi-card kpi-info">
        <h3>Total de Empleados</h3>
        <p class="kpi-value"><?php echo $kpis['total_empleados']; ?></p>
        <p class="kpi-periodo">
            <?php if (checkPermission('gestion_personal')): ?>
                <a href="gestion_personal.php">Ver Personal Activo</a>
            <?php else: ?>
                Personal asignado a este restaurante.
            <?php endif; ?>
        </p>
    </div>
    
    <div class="kpi-card <?php echo ($kpis['platos_inactivos'] > 0) ? 'kpi-warning' : 'kpi-neutral'; ?>">
        <h3>Platos Inactivos</h3>
        <p class="kpi-value"><?php echo $kpis['platos_inactivos']; ?></p>
        <p class="kpi-periodo">
            <?php if (checkPermission('gestion_menu')): ?>
                <a href="gestion_menu.php">Revisar Menú</a>
            <?php else: ?>
                Ítems fuera de venta.
            <?php endif; ?>
        </p>
    </div>
</div>

<hr>

<h2>Accesos Directos</h2>

<div class="access-grid">
    <?php if (checkPermission('gestion_plataforma')): ?>
        <a href="gestion_restaurantes.php" class="access-card access-admin">
            <i class="fas fa-building"></i> Gestión de Restaurantes (J1)
        </a>
    <?php endif; ?>

    <?php if (checkPermission('gestion_personal')): ?>
        <a href="gestion_personal.php" class="access-card access-hr">
            <i class="fas fa-users"></i> Gestión de Personal
        </a>
        <a href="gestion_nomina.php" class="access-card access-finance">
            <i class="fas fa-money-check-alt"></i> Registro de Nómina
        </a>
    <?php endif; ?>

    <?php if (checkPermission('gestion_inventario_total')): ?>
        <a href="gestion_inventario.php" class="access-card access-inventory">
            <i class="fas fa-box"></i> Gestión de Inventario
        </a>
    <?php endif; ?>
    
    <?php if (checkPermission('gestion_menu')): ?>
        <a href="gestion_menu.php" class="access-card access-menu">
            <i class="fas fa-utensils"></i> Menú y Recetas
        </a>
    <?php endif; ?>
    
    <?php if (checkPermission('ver_reportes_financieros')): ?>
        <a href="reportes_financieros.php" class="access-card access-reports">
            <i class="fas fa-chart-line"></i> Reportes Financieros
        </a>
        <a href="reportes_actividad.php" class="access-card access-security">
            <i class="fas fa-shield-alt"></i> Log de Actividad
        </a>
    <?php endif; ?>

    <a href="cambiar_restaurante.php" class="access-card access-neutral">
        <i class="fas fa-sync-alt"></i> Cambiar Restaurante
    </a>
</div>

<?php else: ?>
    <p class="alert alert-info">Por favor, usa el conmutador de restaurante para seleccionar una unidad de negocio y ver las métricas operativas.</p>
    <a href="cambiar_restaurante.php" class="btn btn-primary">Seleccionar Restaurante Ahora</a>
<?php endif; ?>


<?php include '../includes/footer.php'; ?>