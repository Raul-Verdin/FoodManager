<?php
include '../includes/header.php'; 

// Permiso requerido
$permission_needed = 'ver_reportes_financieros'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a reportes financieros.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede acceder a los reportes financieros.</p>';
    include '../includes/footer.php'; 
    exit;
}

$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$nombre_restaurante = getRestaurantName($restaurante_id);

if (!$restaurante_id) {
    echo '<h1>Reportes Financieros</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para generar sus reportes.</p>';
    include '../includes/footer.php';
    exit;
}

// Variables de filtro de fecha
$fecha_actual = date('Y-m-d');
$fecha_hace_30_dias = date('Y-m-d', strtotime('-30 days'));

$fecha_inicio = $_GET['fecha_inicio'] ?? $fecha_hace_30_dias;
$fecha_fin = $_GET['fecha_fin'] ?? $fecha_actual;

// ----------------------------------------------------------------------
// Lógica de Lectura (READ AGREGADO)
// ----------------------------------------------------------------------

$report_data = [
    'costo_nomina' => 0,
    'valor_inventario' => 0,
    'platos_rentables' => [],
    'platos_menos_rentables' => [],
];

try {
    // 1. COSTO TOTAL DE NÓMINA en el período seleccionado
    $stmt_nomina = $pdo->prepare("
        SELECT SUM(total_pagado) AS total_nomina
        FROM nomina
        WHERE restaurante_id = :rid AND fecha_pago BETWEEN :fecha_inicio AND :fecha_fin
    ");
    $stmt_nomina->bindParam(':rid', $restaurante_id);
    $stmt_nomina->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_nomina->bindParam(':fecha_fin', $fecha_fin);
    $stmt_nomina->execute();
    $report_data['costo_nomina'] = $stmt_nomina->fetchColumn() ?: 0;

    // 2. VALOR TOTAL DEL INVENTARIO ACTUAL
    $stmt_inventario = $pdo->prepare("
        SELECT SUM(stock_actual * costo_unitario) AS valor_total
        FROM inventario
        WHERE restaurante_id = :rid
    ");
    $stmt_inventario->bindParam(':rid', $restaurante_id);
    $stmt_inventario->execute();
    $report_data['valor_inventario'] = $stmt_inventario->fetchColumn() ?: 0;

    // 3. RENTABILIDAD DEL MENÚ
    $stmt_menu = $pdo->prepare("
        SELECT 
            nombre, 
            precio_venta, 
            costo_produccion,
            (precio_venta - costo_produccion) AS margen_absoluto,
            ((precio_venta - costo_produccion) / precio_venta) * 100 AS margen_porcentual
        FROM platos_menu
        WHERE restaurante_id = :rid AND activo = TRUE
    ");
    $stmt_menu->bindParam(':rid', $restaurante_id);
    $stmt_menu->execute();
    $menu_rentabilidad = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);

    // Ordenar y limitar para top 5
    usort($menu_rentabilidad, function($a, $b) {
        return $b['margen_absoluto'] <=> $a['margen_absoluto']; // De mayor a menor
    });

    $report_data['platos_rentables'] = array_slice($menu_rentabilidad, 0, 5);
    
    // Invertir el orden para obtener los 5 menos rentables
    usort($menu_rentabilidad, function($a, $b) {
        return $a['margen_absoluto'] <=> $b['margen_absoluto']; // De menor a mayor
    });
    $report_data['platos_menos_rentables'] = array_slice($menu_rentabilidad, 0, 5);


} catch (PDOException $e) {
    $error_message = "Error al generar reportes: " . $e->getMessage();
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/reportes_financieros.css">
</head>

<h1>Reportes Financieros - <?php echo htmlspecialchars($nombre_restaurante); ?></h1><br>

<div class="messages">
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<form action="" method="GET" class="form-filter">
    <label for="fecha_inicio">Desde:</label>
    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
    
    <label for="fecha_fin">Hasta:</label>
    <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
    
    <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
</form>

<hr><br>

<h2>Métricas de Costo</h2><br>

<div class="kpi-container">
    <div class="kpi-card">
        <h3>Costo Total de Nómina</h3>
        <p class="kpi-value kpi-costo">$<?php echo number_format($report_data['costo_nomina'], 2); ?></p>
        <p class="kpi-periodo">Período: <?php echo date('M j', strtotime($fecha_inicio)); ?> - <?php echo date('M j', strtotime($fecha_fin)); ?></p>
    </div>
    
    <div class="kpi-card">
        <h3>Valor Total de Inventario</h3>
        <p class="kpi-value kpi-inventario">$<?php echo number_format($report_data['valor_inventario'], 2); ?></p>
        <p class="kpi-periodo">Costo de reposición estimado actual.</p>
    </div>
</div><br>

<hr><br>

<h2>Análisis de Rentabilidad del Menú</h2><br>

<div class="menu-analysis-container">
    <div class="analysis-card">
        <h3>Top 5 Más Rentables (Margen Absoluto)</h3>
        <table class="data-table">
            <thead>
                <tr><th>Plato</th><th>Precio Venta</th><th>Costo</th><th>Margen (%)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['platos_rentables'] as $plato): ?>
                <tr>
                    <td><?php echo htmlspecialchars($plato['nombre']); ?></td>
                    <td>$<?php echo number_format($plato['precio_venta'], 2); ?></td>
                    <td>$<?php echo number_format($plato['costo_produccion'], 2); ?></td>
                    <td><strong style="color: green;"><?php echo number_format($plato['margen_porcentual'], 1); ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="analysis-card">
        <h3>Top 5 Menos Rentables (Margen Absoluto)</h3>
        <table class="data-table">
            <thead>
                <tr><th>Plato</th><th>Precio Venta</th><th>Costo</th><th>Margen (%)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['platos_menos_rentables'] as $plato): ?>
                <tr>
                    <td><?php echo htmlspecialchars($plato['nombre']); ?></td>
                    <td>$<?php echo number_format($plato['precio_venta'], 2); ?></td>
                    <td>$<?php echo number_format($plato['costo_produccion'], 2); ?></td>
                    <td><strong style="color: <?php echo ($plato['margen_porcentual'] < 15) ? 'red' : 'orange'; ?>;"><?php echo number_format($plato['margen_porcentual'], 1); ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>