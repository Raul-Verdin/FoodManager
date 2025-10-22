<?php
include '../includes/header.php'; 

$permission_needed = 'gestion_inventario_total'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a {$permission_needed} (Inventario)");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede gestionar el inventario.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;

if (!$restaurante_id) {
    echo '<h1>Gestión de Inventario</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para gestionar su inventario.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE / UPDATE / DELETE)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $item_id = (int)$_POST['item_id'] ?? 0;

    try {
        // A. Lógica de CREACIÓN de Ítem
        if ($action == 'create_item') {
            $nombre = trim($_POST['nombre']);
            $unidad_medida = trim($_POST['unidad_medida']);
            $stock_actual = (float)$_POST['stock_actual'];
            $stock_minimo = (float)$_POST['stock_minimo'];
            $costo_unitario = (float)$_POST['costo_unitario'];
            
            if (empty($nombre) || empty($unidad_medida)) {
                 throw new Exception('El nombre y la unidad de medida son obligatorios.');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO inventario (restaurante_id, nombre, unidad_medida, stock_actual, stock_minimo, costo_unitario) 
                VALUES (:rid, :nombre, :unidad, :stock, :min, :costo)
            ");
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':unidad', $unidad_medida);
            $stmt->bindParam(':stock', $stock_actual);
            $stmt->bindParam(':min', $stock_minimo);
            $stmt->bindParam(':costo', $costo_unitario);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'CREATE INVENTORY', "Ítem creado: {$nombre} en Restaurante ID: {$restaurante_id}");
            $message = "✅ Ítem '{$nombre}' añadido al inventario.";
        }

        // B. Lógica de ACTUALIZACIÓN de Ítem
        if ($action == 'update_item') {
            $nombre = trim($_POST['nombre']);
            $unidad_medida = trim($_POST['unidad_medida']);
            $stock_actual = (float)$_POST['stock_actual'];
            $stock_minimo = (float)$_POST['stock_minimo'];
            $costo_unitario = (float)$_POST['costo_unitario'];
            
            $stmt = $pdo->prepare("
                UPDATE inventario SET nombre = :nombre, unidad_medida = :unidad, 
                stock_actual = :stock, stock_minimo = :min, costo_unitario = :costo, fecha_ultimo_ajuste = NOW()
                WHERE id = :id AND restaurante_id = :rid
            ");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':unidad', $unidad_medida);
            $stmt->bindParam(':stock', $stock_actual);
            $stmt->bindParam(':min', $stock_minimo);
            $stmt->bindParam(':costo', $costo_unitario);
            $stmt->bindParam(':id', $item_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->execute();

            logActivity($_SESSION['user_id'], 'UPDATE INVENTORY', "Ítem ID: {$item_id} actualizado.");
            $message = "✅ Inventario del ítem ID {$item_id} actualizado con éxito.";
        }

        // C. Lógica de ELIMINACIÓN de Ítem
        if ($action == 'delete_item') {
            $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = :id AND restaurante_id = :rid");
            $stmt->bindParam(':id', $item_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'DELETE INVENTORY', "Ítem ID: {$item_id} eliminado.");
            $message = "✅ Ítem de inventario eliminado.";
        }

    } catch (PDOException $e) {
        $error_message = "Error de base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

try {
    $stmt_inv = $pdo->prepare("
        SELECT id, nombre, unidad_medida, stock_actual, stock_minimo, costo_unitario, fecha_ultimo_ajuste
        FROM inventario
        WHERE restaurante_id = :rid
        ORDER BY nombre ASC
    ");
    $stmt_inv->bindParam(':rid', $restaurante_id);
    $stmt_inv->execute();
    $inventario_list = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de inventario.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_inventario.css">
</head>

<h1>Gestión de Inventario - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<h2>Registrar Nuevo Ítem de Inventario</h2>
<button type="button" class="btn btn-primary toggle-form-btn" onclick="toggleFormInventario()">
    + Registrar Ítem de Inventario
</button>

<div id="form-inventario" class="from_invent" >
    <form action="" method="POST" class="form-crud">
        <input type="hidden" name="action" value="create_item">

        <div class="form-group">
            <label for="nombre">Nombre del Ítem:</label>
            <input type="text" name="nombre" required>
        </div>
        <div class="form-group">
            <label for="unidad_medida">Unidad de Medida (Ej: kg, L, unidad):</label>
            <input type="text" name="unidad_medida" required>
        </div>
        <div class="form-group">
            <label for="stock_actual">Stock Inicial:</label>
            <input type="number" step="0.01" name="stock_actual" value="0.00" required>
        </div>
        <div class="form-group">
            <label for="stock_minimo">Stock Mínimo (Alerta):</label>
            <input type="number" step="0.01" name="stock_minimo" value="0.00" required>
        </div>
        <div class="form-group">
            <label for="costo_unitario">Costo Unitario:</label>
            <input type="number" step="0.01" name="costo_unitario" value="0.00" required>
        </div>

        <button type="submit" class="btn btn-primary">Crear Ítem</button>
    </form>
</div>

<hr><br>

<h2>Existencias Actuales</h2>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>U. Medida</th>
            <th>Stock Actual</th>
            <th>Stock Mínimo</th>
            <th>Costo Unitario</th>
            <th>Último Ajuste</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($inventario_list as $item): ?>
            <tr class="<?php echo ($item['stock_actual'] < $item['stock_minimo']) ? 'alert-row-danger' : ''; ?>">
                <form action="" method="POST" style="display:contents;">
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    
                    <td><?php echo $item['id']; ?></td>
                    <td><input type="text" name="nombre" value="<?php echo htmlspecialchars($item['nombre']); ?>" required></td>
                    <td><input type="text" name="unidad_medida" value="<?php echo htmlspecialchars($item['unidad_medida']); ?>" required></td>
                    <td><input type="number" step="0.01" name="stock_actual" value="<?php echo $item['stock_actual']; ?>" required></td>
                    <td><input type="number" step="0.01" name="stock_minimo" value="<?php echo $item['stock_minimo']; ?>" required></td>
                    <td><input type="number" step="0.01" name="costo_unitario" value="<?php echo $item['costo_unitario']; ?>" required></td>
                    <td><?php echo $item['fecha_ultimo_ajuste']; ?></td>
                    <td>
                        <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nombre']); ?>')">X</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="../script/gestion_inventario.js"></script>

<?php include '../includes/footer.php'; ?>