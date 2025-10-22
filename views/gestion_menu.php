<?php
include '../includes/header.php'; 

$permission_needed = 'gestion_menu'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a {$permission_needed} (Menú)");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede gestionar el menú y las recetas.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;

if (!$restaurante_id) {
    echo '<h1>Gestión de Menú y Recetas</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para gestionar su menú.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// FUNCIÓN AUXILIAR: CALCULAR Y ACTUALIZAR COSTO DE PRODUCCIÓN
// ----------------------------------------------------------------------
function calculateAndSaveCost($pdo, $plato_id) {
    // 1. Obtener la suma de los costos de los ingredientes requeridos
    $stmt_cost = $pdo->prepare("
        SELECT SUM(r.cantidad_requerida * i.costo_unitario) AS costo_total
        FROM recetas r
        JOIN inventario i ON r.inventario_id = i.id
        WHERE r.plato_id = :plato_id
    ");
    $stmt_cost->bindParam(':plato_id', $plato_id);
    $stmt_cost->execute();
    $costo_produccion = $stmt_cost->fetchColumn() ?: 0.00;

    // 2. Actualizar la tabla platos_menu
    $stmt_update = $pdo->prepare("
        UPDATE platos_menu SET costo_produccion = :costo 
        WHERE id = :plato_id
    ");
    $stmt_update->bindParam(':costo', $costo_produccion);
    $stmt_update->bindParam(':plato_id', $plato_id);
    $stmt_update->execute();
    
    return $costo_produccion;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CRUD PLATOS / RECETAS)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $plato_id = (int)$_POST['plato_id'] ?? 0;

    try {
        $pdo->beginTransaction();

        // A. Lógica de CREACIÓN de Plato
        if ($action == 'create_plato') {
    $nombre = trim($_POST['nombre']);
    $precio_venta = (float)$_POST['precio_venta'];
            
    if (empty($nombre) || $precio_venta <= 0) {
        throw new Exception('Nombre y Precio de Venta son obligatorios.');
    }
            
    $stmt = $pdo->prepare("
        INSERT INTO platos_menu (restaurante_id, nombre, precio_venta) 
        VALUES (:rid, :nombre, :precio)
    ");
    $stmt->bindParam(':rid', $restaurante_id);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':precio', $precio_venta);
    $stmt->execute();
            
    $plato_id = $pdo->lastInsertId();

    // ✅ FALTA ESTO
    $pdo->commit(); 

    logActivity($_SESSION['user_id'], 'CREATE PLATO', "Plato creado: {$nombre} (ID: {$plato_id})");
    $message = "✅ Plato '{$nombre}' creado con éxito. Añade los ingredientes de la receta.";
}

        // B. Lógica de ACTUALIZACIÓN de Plato y Receta
        if ($action == 'update_plato_receta') {
            $nombre = trim($_POST['nombre']);
            $precio_venta = (float)$_POST['precio_venta'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            $ingredientes = $_POST['ingrediente'] ?? [];
            
            // 1. Actualizar Plato
            $stmt = $pdo->prepare("
                UPDATE platos_menu SET nombre = :nombre, precio_venta = :precio, activo = :activo 
                WHERE id = :id AND restaurante_id = :rid
            ");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':precio', $precio_venta);
            $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
            $stmt->bindParam(':id', $plato_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->execute();

            // 2. Gestión de Recetas (Transacción anidada)
            
            // 2.1. Eliminar recetas viejas para reinsertar las nuevas (Método simple de CRUD)
            $stmt_delete_receta = $pdo->prepare("DELETE FROM recetas WHERE plato_id = :plato_id");
            $stmt_delete_receta->bindParam(':plato_id', $plato_id);
            $stmt_delete_receta->execute();
            
            // 2.2. Insertar Recetas Nuevas
            if (!empty($ingredientes)) {
                $stmt_insert_receta = $pdo->prepare("
                    INSERT INTO recetas (plato_id, inventario_id, cantidad_requerida) 
                    VALUES (:plato_id, :inventario_id, :cantidad)
                ");
                foreach ($ingredientes as $ingrediente) {
                    $inventario_id = (int)$ingrediente['id'];
                    $cantidad = (float)$ingrediente['cantidad'];
                    
                    if ($inventario_id > 0 && $cantidad > 0) {
                        $stmt_insert_receta->bindParam(':plato_id', $plato_id);
                        $stmt_insert_receta->bindParam(':inventario_id', $inventario_id);
                        $stmt_insert_receta->bindParam(':cantidad', $cantidad);
                        $stmt_insert_receta->execute();
                    }
                }
            }
            
            // 3. Recalcular el costo después de actualizar la receta
            $costo_calculado = calculateAndSaveCost($pdo, $plato_id);

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'UPDATE PLATO/RECETA', "Plato ID: {$plato_id} actualizado. Costo: {$costo_calculado}");
            $message = "✅ Plato y Receta actualizados. Nuevo costo de producción: $" . number_format($costo_calculado, 2);
        }

        // C. Lógica de ELIMINACIÓN de Plato
        if ($action == 'delete_plato') {
            // ON DELETE CASCADE en 'platos_menu' debería manejar la eliminación de 'recetas'
            $stmt = $pdo->prepare("DELETE FROM platos_menu WHERE id = :id AND restaurante_id = :rid");
            $stmt->bindParam(':id', $plato_id);
            $stmt->bindParam(':rid', $restaurante_id);
            $stmt->execute();

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'DELETE PLATO', "Plato ID: {$plato_id} eliminado.");
            $message = "✅ Plato ID {$plato_id} y su receta eliminados.";
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error de base de datos: " . $e->getMessage();
        error_log("DB Error GESTION MENU: " . $e->getMessage());
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Error: " . $e->getMessage();
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

try {
    // 1. Obtener todos los ítems de inventario para el formulario de recetas
    $stmt_inv_items = $pdo->prepare("
        SELECT id, nombre, unidad_medida, costo_unitario 
        FROM inventario 
        WHERE restaurante_id = :rid 
        ORDER BY nombre ASC
    ");
    $stmt_inv_items->bindParam(':rid', $restaurante_id);
    $stmt_inv_items->execute();
    $inventario_items = $stmt_inv_items->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener la lista de platos del menú
    $stmt_platos = $pdo->prepare("
        SELECT id, nombre, precio_venta, costo_produccion, activo
        FROM platos_menu
        WHERE restaurante_id = :rid
        ORDER BY nombre ASC
    ");
    $stmt_platos->bindParam(':rid', $restaurante_id);
    $stmt_platos->execute();
    $platos_list = $stmt_platos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener todas las recetas para mostrar en la edición
    $stmt_recetas = $pdo->prepare("
        SELECT r.plato_id, r.inventario_id, r.cantidad_requerida, i.nombre, i.unidad_medida, i.costo_unitario
        FROM recetas r
        JOIN inventario i ON r.inventario_id = i.id
        WHERE i.restaurante_id = :rid
    ");
    $stmt_recetas->bindParam(':rid', $restaurante_id);
    $stmt_recetas->execute();
    $recetas_raw = $stmt_recetas->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar las recetas por plato_id para fácil acceso
    $recetas_organized = [];
    foreach ($recetas_raw as $rec) {
        if (!isset($recetas_organized[$rec['plato_id']])) {
            $recetas_organized[$rec['plato_id']] = [];
        }
        $recetas_organized[$rec['plato_id']][] = $rec;
    }

} catch (PDOException $e) {
    $error_message = "Error al cargar datos de Menú/Inventario.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_menu.css">
</head>

<h1>Gestión de Menú y Recetas - <?php echo getRestaurantName($restaurante_id); ?></h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<!-- Botón de Mostrar/Ocultar formulario -->
<button type="button" class="btn btn-primary toggle-form-btn" onclick="toggleForm()">+ Añadir Nuevo Plato</button>

<!-- Formulario para añadir nuevo plato -->
<div id="form-container" style="display: none;">
    <form action="" method="POST" class="form-crud">
        <input type="hidden" name="action" value="create_plato">
        
        <div class="form-group">
            <label for="nombre">Nombre del Plato:</label>
            <input type="text" name="nombre" required>
        </div>
        <div class="form-group">
            <label for="precio_venta">Precio de Venta ($):</label>
            <input type="number" step="0.01" name="precio_venta" value="0.00" required>
        </div>
        
        <button type="submit" class="btn btn-success">Crear Plato</button>
    </form>
</div>

<hr>

<h2>Menú Activo (<?php echo count($platos_list); ?> Platos)</h2>

<?php foreach ($platos_list as $plato): ?>
<div class="card <?php echo ($plato['activo'] == 0) ? 'card-inactive' : ''; ?>">
    <form action="" method="POST">
        <input type="hidden" name="action" value="update_plato_receta">
        <input type="hidden" name="plato_id" value="<?php echo $plato['id']; ?>">
        
        <div class="card-header">
            <h3><?php echo htmlspecialchars($plato['nombre']); ?> (ID: <?php echo $plato['id']; ?>)</h3>
            <div class="controls">
                <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePlato(<?php echo $plato['id']; ?>, '<?php echo htmlspecialchars($plato['nombre']); ?>')">Eliminar</button>
            </div>
        </div>
        
        <div class="card-body">
            <div class="form-group-inline">
                <label>Nombre:</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($plato['nombre']); ?>" required>
            </div>
            <div class="form-group-inline">
                <label>Precio Venta ($):</label>
                <input type="number" step="0.01" name="precio_venta" value="<?php echo $plato['precio_venta']; ?>" required>
            </div>
            <div class="form-group-inline">
                <label>Costo Producción ($):</label>
                <strong style="color: red;"><?php echo number_format($plato['costo_produccion'], 2); ?></strong>
            </div>
            <div class="form-group-inline">
                <label>Activo:</label>
                <input type="checkbox" name="activo" value="1" <?php echo ($plato['activo']) ? 'checked' : ''; ?>>
            </div>
        </div>

        <div class="card-receta">
            <h4>Receta (Ingredientes)</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ingrediente</th>
                        <th>Cantidad</th>
                        <th>U. Medida</th>
                        <th>Costo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="receta-body-<?php echo $plato['id']; ?>">
                    <?php 
                    $current_receta = $recetas_organized[$plato['id']] ?? [];
                    foreach ($current_receta as $rec): 
                    ?>
                        <tr class="receta-row">
                            <td>
                                <select name="ingrediente[<?php echo $rec['inventario_id']; ?>][id]" disabled>
                                    <option value="<?php echo $rec['inventario_id']; ?>" selected><?php echo htmlspecialchars($rec['nombre']); ?></option>
                                </select>
                                <input type="hidden" name="ingrediente[<?php echo $rec['inventario_id']; ?>][id]" value="<?php echo $rec['inventario_id']; ?>">
                            </td>
                            <td>
                                <input type="number" step="0.001" name="ingrediente[<?php echo $rec['inventario_id']; ?>][cantidad]" value="<?php echo $rec['cantidad_requerida']; ?>" required>
                            </td>
                            <td><?php echo $rec['unidad_medida']; ?></td>
                            <td>$<?php echo number_format($rec['cantidad_requerida'] * $rec['costo_unitario'], 2); ?></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-ingrediente">X</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addIngrediente(<?php echo $plato['id']; ?>)">+ Añadir Ingrediente</button>
        </div>
    </form>
</div>
<?php endforeach; ?>

<script>
    // JSON de ítems de inventario para poblar el SELECT dinámicamente
    const inventarioItems = <?php echo json_encode($inventario_items); ?>;

    function addIngrediente(platoId) {
        const tbody = document.getElementById('receta-body-' + platoId);
        const newRow = document.createElement('tr');
        newRow.className = 'receta-row';

        // Generar un ID temporal único para el campo name (usamos Date.now() para evitar colisiones)
        const tempId = 'new_' + Date.now();

        let selectHtml = '<select name="ingrediente[' + tempId + '][id]" required>';
        selectHtml += '<option value="">-- Selecciona un Ítem --</option>';
        inventarioItems.forEach(item => {
            selectHtml += `<option value="${item.id}">${item.nombre} (${item.unidad_medida})</option>`;
        });
        selectHtml += '</select>';

        newRow.innerHTML = `
            <td>${selectHtml}</td>
            <td>
                <input type="number" step="0.001" name="ingrediente[${tempId}][cantidad]" value="1" required>
            </td>
            <td>N/A</td>
            <td>$0.00</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-ingrediente" onclick="this.closest('.receta-row').remove()">X</button>
            </td>
        `;

        // Añadir el nuevo ingrediente a la tabla
        tbody.appendChild(newRow);
    }
    
    // Función para el borrado del plato
    function confirmDeletePlato(platoId, platoName) {
        if (confirm('ADVERTENCIA: ¿Está seguro de eliminar el plato "' + platoName + '"? Esto eliminará también su receta. Esta acción es irreversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'gestion_menu.php';
            
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_plato">
                <input type="hidden" name="plato_id" value="${platoId}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<script src="../script/gestion_menu.js"></script>

<?php include '../includes/footer.php'; ?>