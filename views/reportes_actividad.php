<?php
include '../includes/header.php'; 

// Permiso requerido: Típicamente, los que ven reportes financieros también ven actividad.
$permission_needed = 'ver_reportes_financieros'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a reportes de actividad.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede acceder a los reportes de actividad.</p>';
    include '../includes/footer.php'; 
    exit;
}

$error_message = '';
$usuario_actual_id = $_SESSION['user_id'];
$jerarquia_actual = $_SESSION['user_jerarquia'];
$restaurante_actual_id = $_SESSION['restaurante_actual_id'] ?? null; // obtines el id del resstaurante para filtrar contenido 

// ----------------------------------------------------------------------
// Lógica de Lectura (READ) con Filtro de Jerarquía
// ----------------------------------------------------------------------

try {
    $sql = "
        SELECT 
            ra.id, ra.accion, ra.detalles, ra.fecha_hora, ra.ip_origen,
            u.nombre_completo AS usuario_nombre, 
            r.nombre AS restaurante_nombre 
        FROM registro_actividad ra
        JOIN usuarios u ON ra.usuario_id = u.id
        LEFT JOIN usuarios_restaurantes ur ON ra.usuario_id = ur.usuario_id -- Para obtener el contexto del restaurante
        LEFT JOIN restaurantes r ON ur.restaurante_id = r.id
    ";
    
    $where = [];
    $params = [];
    
    // Filtro por Jerarquía
    if ($jerarquia_actual == 1) {
        // Super Administrador (J1)
        if ($restaurante_actual_id) {
            $where[] = "r.id = :restaurante_id";
            $params[':restaurante_id'] = $restaurante_actual_id;
        } else {
        $where[] = "1=1"; // sin filtro si no se ha seleccionado restaurante
        }
    } elseif ($jerarquia_actual == 2) {
        // Gerente de Restaurante (J2)
        if ($restaurante_actual_id) {
            $where[] = "(r.gerenteres_id = :gerente_id OR ra.usuario_id = :usuario_id)";
            $where[] = "r.id = :restaurante_id";
            $params[':gerente_id'] = $usuario_actual_id;
            $params[':usuario_id'] = $usuario_actual_id;
            $params[':restaurante_id'] = $restaurante_actual_id;
        } else {
            // Sin restaurante seleccionado, mostrar solo los suyos
            $where[] = "r.gerenteres_id = :gerente_id OR ra.usuario_id = :usuario_id";
            $params[':gerente_id'] = $usuario_actual_id;
            $params[':usuario_id'] = $usuario_actual_id;
        }
    } elseif ($jerarquia_actual >= 3) {
        // Otros roles (J3+): Solo ven su propia actividad.
        $where[] = "ra.usuario_id = :current_user_id";
        $params[':current_user_id'] = $usuario_actual_id;
    }
    
    // Si hay clausulas WHERE, las agregamos
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    // La actividad se puede duplicar si el usuario tiene múltiples roles/restaurantes.
    // Usamos GROUP BY para simplificar la vista, pero esto es una simplificación.
    $sql .= " GROUP BY ra.id ORDER BY ra.fecha_hora DESC LIMIT 100"; 
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $actividad_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar el log de actividad: " . $e->getMessage();
}

// Determinar el título basado en la jerarquía
$report_title = "Reporte de Actividad";
if ($jerarquia_actual == 1) {
    $report_title .= " (Plataforma Global)";
} elseif ($jerarquia_actual == 2) {
    $report_title .= " (Restaurantes Administrados)";
} else {
    $report_title .= " (Mi Actividad)";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/reportes_actividad.css">
</head>

<h1><?php echo $report_title; ?></h1>

<?php if ($restaurante_actual_id): ?>
    <p><strong>Restaurante seleccionado:</strong> <?php echo htmlspecialchars(getRestaurantName($restaurante_actual_id)); ?></p>
<?php endif; ?>

<div class="messages">
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<p>Mostrando las últimas 100 entradas de actividad. El alcance de los datos visibles está limitado por tu rol.</p>

<div class="table-scroll-container">
    <table class="data-table log-table">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Detalles</th>
                <th>IP Origen</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($actividad_list)): ?>
                <tr><td colspan="5">No hay actividad registrada bajo tu nivel de acceso.</td></tr>
            <?php else: ?>
                <?php foreach ($actividad_list as $log): ?>
                    <tr class="log-<?php echo strtolower(explode(' ', $log['accion'])[0]); ?>">
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['fecha_hora'])); ?></td>
                        <td><?php echo htmlspecialchars($log['usuario_nombre']); ?> (ID: <?php echo $log['usuario_id']; ?>)</td>
                        <td><strong class="log-action"><?php echo htmlspecialchars($log['accion']); ?></strong></td>
                        <td><?php echo htmlspecialchars($log['detalles'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_origen']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .log-table .log-access { color: #007bff; }
    .log-table .log-create, .log-table .log-update, .log-table .log-generate { color: #28a745; }
    .log-table .log-delete, .log-table .log-denied { color: #dc3545; font-weight: bold; }
    .log-table .log-action { text-transform: uppercase; }
</style>

<?php include '../includes/footer.php'; ?>
