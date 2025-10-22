<?php
include '../includes/header.php'; 

// Permiso requerido
$permission_needed = 'gestion_nomina'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a gestión de nómina.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede gestionar la nómina.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$user_logged_id = $_SESSION['user_id'];

if (!$restaurante_id) {
    echo '<h1>Gestión de Nómina</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para gestionar su nómina.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_pago') {
    try {
        $usuario_pago_id = (int)$_POST['usuario_pago_id'];
        $fecha_pago = trim($_POST['fecha_pago']);
        $periodo_inicio = trim($_POST['periodo_inicio']);
        $periodo_fin = trim($_POST['periodo_fin']);
        $horas_trabajadas = (float)$_POST['horas_trabajadas'];
        $salario_base = (float)$_POST['salario_base'];
        $deducciones = (float)$_POST['deducciones'];
        $bonificaciones = (float)$_POST['bonificaciones'];

        if (empty($fecha_pago) || empty($periodo_inicio) || empty($periodo_fin) || $usuario_pago_id <= 0) {
            throw new Exception('Faltan fechas o el ID del empleado.');
        }

        // Cálculo del Total Pagado
        $total_pagado = $salario_base - $deducciones + $bonificaciones;

        $stmt = $pdo->prepare("
            INSERT INTO nomina (restaurante_id, usuario_id, fecha_pago, periodo_inicio, periodo_fin, horas_trabajadas, salario_base, deducciones, bonificaciones, total_pagado, registrado_por_id) 
            VALUES (:rid, :uid, :fpago, :pinicio, :pfin, :horas, :base, :ded, :boni, :total, :reg_id)
        ");
        $stmt->execute([
            ':rid' => $restaurante_id,
            ':uid' => $usuario_pago_id,
            ':fpago' => $fecha_pago,
            ':pinicio' => $periodo_inicio,
            ':pfin' => $periodo_fin,
            ':horas' => $horas_trabajadas,
            ':base' => $salario_base,
            ':ded' => $deducciones,
            ':boni' => $bonificaciones,
            ':total' => $total_pagado,
            ':reg_id' => $user_logged_id
        ]);

        logActivity($user_logged_id, 'CREATE NOMINA', "Pago de nómina registrado para Usuario ID: {$usuario_pago_id}. Total: {$total_pagado}");
        $message = "✅ Pago de nómina de $" . number_format($total_pagado, 2) . " registrado con éxito.";

    } catch (Exception $e) {
        $error_message = "Error al registrar nómina: " . $e->getMessage();
    }
}


// ----------------------------------------------------------------------
// Lógica de Lectura (READ) y Obtención de datos para formularios
// ----------------------------------------------------------------------

try {
    // 1. Obtener la lista de empleados asignados al restaurante actual (para el select de pago)
    $stmt_personal = $pdo->prepare("
        SELECT u.id, u.nombre_completo, r.nombre AS rol_nombre
        FROM usuarios_restaurantes ur
        JOIN usuarios u ON ur.usuario_id = u.id
        JOIN roles r ON ur.rol_id = r.id
        WHERE ur.restaurante_id = :rid AND ur.activo = 1
        ORDER BY u.nombre_completo ASC
    ");
    $stmt_personal->bindParam(':rid', $restaurante_id);
    $stmt_personal->execute();
    $personal_list = $stmt_personal->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener el historial de pagos de nómina
    $stmt_nomina = $pdo->prepare("
        SELECT n.*, u.nombre_completo AS empleado, reg.nombre_completo AS registrado_por
        FROM nomina n
        JOIN usuarios u ON n.usuario_id = u.id
        JOIN usuarios reg ON n.registrado_por_id = reg.id
        WHERE n.restaurante_id = :rid
        ORDER BY n.fecha_pago DESC, n.id DESC
        LIMIT 50
    ");
    $stmt_nomina->bindParam(':rid', $restaurante_id);
    $stmt_nomina->execute();
    $historial_nomina = $stmt_nomina->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar datos de nómina.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_nomina.css">
</head>

<h1>Gestión de Nómina - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<h2>Registrar Nuevo Pago de Nómina</h2><br>

<button type="button" class="btn btn-primary toggle-form-btn" onclick="toggleForm()">
    + Registrar Pago de Nómina
</button>

<div id="form-nomina" style="display: none; margin-top: 20px;">
    <form action="" method="POST" class="form-crud">
        <input type="hidden" name="action" value="create_pago">
        
        <div class="form-group-triple">
            <div class="form-group">
                <label for="usuario_pago_id">Empleado:</label>
                <select name="usuario_pago_id" required>
                    <option value="">-- Seleccionar Empleado --</option>
                    <?php foreach ($personal_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre_completo']); ?> (<?php echo htmlspecialchars($p['rol_nombre']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha_pago">Fecha de Pago:</label>
                <input type="date" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="form-group-triple">
            <div class="form-group">
                <label for="periodo_inicio">Periodo Inicio:</label>
                <input type="date" name="periodo_inicio" required>
            </div>
            <div class="form-group">
                <label for="periodo_fin">Periodo Fin:</label>
                <input type="date" name="periodo_fin" required>
            </div>
            <div class="form-group">
                <label for="horas_trabajadas">Horas Trabajadas:</label>
                <input type="number" step="0.01" name="horas_trabajadas" value="0.00">
            </div>
        </div>

        <div class="form-group-triple">
            <div class="form-group">
                <label for="salario_base">Salario Base (Neto/Período):</label>
                <input type="number" step="0.01" name="salario_base" value="0.00" required>
            </div>
            <div class="form-group">
                <label for="bonificaciones">Bonificaciones (+):</label>
                <input type="number" step="0.01" name="bonificaciones" value="0.00">
            </div>
            <div class="form-group">
                <label for="deducciones">Deducciones (-):</label>
                <input type="number" step="0.01" name="deducciones" value="0.00">
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Registrar Pago</button>
    </form>
</div><br><br>

<hr>

<h2>Historial de Pagos Recientes</h2>
<div class="table-scroll-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha Pago</th>
                <th>Empleado</th>
                <th>Período</th>
                <th>Horas</th>
                <th>Salario Base</th>
                <th>Boni. (+)</th>
                <th>Deducc. (-)</th>
                <th>Total Pagado</th>
                <th>Registrado Por</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($historial_nomina)): ?>
                <tr><td colspan="9">No hay registros de nómina para este restaurante.</td></tr>
            <?php else: ?>
                <?php foreach ($historial_nomina as $pago): ?>
                    <tr>
                        <td><?php echo date('Y-m-d', strtotime($pago['fecha_pago'])); ?></td>
                        <td><?php echo htmlspecialchars($pago['empleado']); ?></td>
                        <td><?php echo date('m/d', strtotime($pago['periodo_inicio'])) . ' - ' . date('m/d', strtotime($pago['periodo_fin'])); ?></td>
                        <td><?php echo $pago['horas_trabajadas']; ?></td>
                        <td>$<?php echo number_format($pago['salario_base'], 2); ?></td>
                        <td style="color: green;">$<?php echo number_format($pago['bonificaciones'], 2); ?></td>
                        <td style="color: red;">$<?php echo number_format($pago['deducciones'], 2); ?></td>
                        <td><strong>$<?php echo number_format($pago['total_pagado'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($pago['registrado_por']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="../script/gestion_nomina.js"></script>

<?php include '../includes/footer.php'; ?>