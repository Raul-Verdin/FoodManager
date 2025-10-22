<?php
include '../includes/header.php'; 

$permission_needed = 'tomar_ordenes'; 

if (!checkPermission($permission_needed)) {
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso al Punto de Venta.");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Tu rol no puede tomar órdenes.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';
$restaurante_id = $_SESSION['restaurante_actual_id'] ?? null;
$usuario_id = $_SESSION['user_id'];

if (!$restaurante_id) {
    echo '<h1>Punto de Venta (POS)</h1><p class="alert alert-warning">Por favor, selecciona un restaurante para iniciar la venta.</p>';
    include '../includes/footer.php';
    exit;
}

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE ORDEN)
// ----------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_order') {
    $items = $_POST['items'] ?? [];
    $mesa_id = (int)$_POST['mesa_id'] ?? null;

    if (empty($items)) {
        $error_message = 'La orden no puede estar vacía.';
    } else {
        $total_neto = 0;
        $impuestos_tasa = 0.16; // Tasa de impuestos fija (16%)
        
        // 1. Calcular totales y validar
        $orden_detalles = [];
        $plato_ids = array_keys($items);
        
        // 1.1. Obtener datos clave de los platos (precio y costo) en una sola consulta
        $placeholders = implode(',', array_fill(0, count($plato_ids), '?'));
        $stmt_platos = $pdo->prepare("
            SELECT id, nombre, precio_venta, costo_produccion
            FROM platos_menu
            WHERE id IN ({$placeholders}) AND restaurante_id = ? AND activo = TRUE
        ");
        $stmt_platos->execute(array_merge($plato_ids, [$restaurante_id]));
        $platos_data = $stmt_platos->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_ASSOC);

        if (count($platos_data) != count($plato_ids)) {
             $error_message = 'Uno o más platos seleccionados no están activos o no existen.';
        } else {
            foreach ($items as $plato_id => $data) {
                $plato_id = (int)$plato_id;
                $cantidad = (int)$data['cantidad'];
                $notas = trim($data['notas'] ?? '');
                
                if ($cantidad > 0) {
                    $precio_unitario = (float)$platos_data[$plato_id]['precio_venta'];
                    $costo_unitario = (float)$platos_data[$plato_id]['costo_produccion'];
                    
                    $subtotal_item = $precio_unitario * $cantidad;
                    $total_neto += $subtotal_item;
                    
                    $orden_detalles[] = [
                        'plato_id' => $plato_id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'costo_unitario' => $costo_unitario,
                        'notas' => $notas
                    ];
                }
            }
        }
        
        // 2. Transacción de Creación
        if (empty($error_message)) {
            $impuestos = $total_neto * $impuestos_tasa;
            $total_bruto = $total_neto + $impuestos;
            
            try {
                $pdo->beginTransaction();

                // 2.1. Insertar la Orden Principal
                $stmt_orden = $pdo->prepare("
                    INSERT INTO ordenes (restaurante_id, usuario_id, mesa_id, total_neto, impuestos, total_bruto, estado) 
                    VALUES (:rid, :uid, :mid, :neto, :impuestos, :bruto, 'PENDIENTE')
                ");
                $stmt_orden->execute([
                    ':rid' => $restaurante_id,
                    ':uid' => $usuario_id,
                    ':mid' => $mesa_id ?: null,
                    ':neto' => $total_neto,
                    ':impuestos' => $impuestos,
                    ':bruto' => $total_bruto
                ]);
                $orden_id = $pdo->lastInsertId();

                // 2.2. Insertar los Detalles de la Orden
                $stmt_detalle = $pdo->prepare("
                    INSERT INTO detalle_ordenes (orden_id, plato_id, cantidad, precio_unitario, costo_unitario, notas) 
                    VALUES (:oid, :pid, :cant, :precio, :costo, :notas)
                ");
                foreach ($orden_detalles as $detalle) {
                    $stmt_detalle->execute([
                        ':oid' => $orden_id,
                        ':pid' => $detalle['plato_id'],
                        ':cant' => $detalle['cantidad'],
                        ':precio' => $detalle['precio_unitario'],
                        ':costo' => $detalle['costo_unitario'],
                        ':notas' => $detalle['notas']
                    ]);
                }
                
                // 2.3. Opcional: Marcar mesa como OCUPADA si se usó una mesa
                if ($mesa_id) {
                    $stmt_mesa = $pdo->prepare("UPDATE mesas SET estado = 'OCUPADA' WHERE id = :mid AND restaurante_id = :rid");
                    $stmt_mesa->execute([':mid' => $mesa_id, ':rid' => $restaurante_id]);
                }

                $pdo->commit();
                logActivity($usuario_id, 'CREATE ORDER', "Orden #{$orden_id} creada. Total: {$total_bruto}");
                $message = "✅ Orden #{$orden_id} creada con éxito. Total Bruto: $" . number_format($total_bruto, 2);

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error crítico al crear la orden: " . $e->getMessage();
            }
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ: Menú y Mesas)
// ----------------------------------------------------------------------

try {
    // 1. Obtener el menú activo
    $stmt_menu = $pdo->prepare("
        SELECT id, nombre, precio_venta, costo_produccion
        FROM platos_menu
        WHERE restaurante_id = :rid AND activo = TRUE
        ORDER BY nombre ASC
    ");
    $stmt_menu->bindParam(':rid', $restaurante_id);
    $stmt_menu->execute();
    $menu_list = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener las mesas disponibles o activas
    $stmt_mesas = $pdo->prepare("
        SELECT id, numero, estado
        FROM mesas
        WHERE restaurante_id = :rid
        ORDER BY numero ASC
    ");
    $stmt_mesas->bindParam(':rid', $restaurante_id);
    $stmt_mesas->execute();
    $mesas_list = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar datos de Menú/Mesas: " . $e->getMessage();
}
?>

<h1>Punto de Venta (POS) - Nuevo Pedido</h1>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<form action="" method="POST" id="pos-form">
    <input type="hidden" name="action" value="create_order">

    <div class="pos-header">
        <label for="mesa_id">Asignar a Mesa:</label>
        <select name="mesa_id" id="mesa_id">
            <option value="">(Sin Mesa / Llevar)</option>
            <?php foreach ($mesas_list as $mesa): ?>
                <option value="<?php echo $mesa['id']; ?>" <?php echo ($mesa['estado'] === 'OCUPADA' ? 'disabled' : ''); ?>>
                    Mesa <?php echo htmlspecialchars($mesa['numero']); ?> (<?php echo htmlspecialchars($mesa['estado']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="pos-layout">
        
        <div class="pos-menu">
            <h2>Menú Activo</h2>
            <div class="menu-grid">
                <?php foreach ($menu_list as $plato): ?>
                    <div class="menu-item" data-id="<?php echo $plato['id']; ?>" data-name="<?php echo htmlspecialchars($plato['nombre']); ?>" data-price="<?php echo $plato['precio_venta']; ?>">
                        <h4><?php echo htmlspecialchars($plato['nombre']); ?></h4>
                        <p>$<?php echo number_format($plato['precio_venta'], 2); ?></p>
                        <button type="button" class="btn btn-sm btn-info add-to-order-btn">Añadir</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pos-order">
            <h2>Orden Actual</h2>
            <table class="data-table" id="order-items-table">
                <thead>
                    <tr>
                        <th>Cant.</th>
                        <th>Producto</th>
                        <th>Subtotal</th>
                        <th>Notas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="order-tbody">
                    </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">Neto (Subtotal):</td>
                        <td id="total-neto">$0.00</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;">Impuestos (16%):</td>
                        <td id="total-impuestos">$0.00</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">**Total Bruto:**</td>
                        <td id="total-bruto">**$0.00**</td>
                    </tr>
                </tfoot>
            </table>
            
            <button type="submit" class="btn btn-primary btn-block" id="submit-order-btn" disabled>
                Crear Orden y Enviar a Cocina
            </button>
        </div>
    </div>
</form>

<script>
    let orderItems = {};

    function updateOrderTable() {
        const tbody = document.getElementById('order-tbody');
        tbody.innerHTML = '';
        let totalNeto = 0;

        for (const id in orderItems) {
            const item = orderItems[id];
            const subtotal = item.cantidad * item.precio;
            totalNeto += subtotal;

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input type="number" name="items[${id}][cantidad]" value="${item.cantidad}" min="1" class="item-cantidad-input" data-id="${id}" style="width: 50px;">
                </td>
                <td>${item.nombre}</td>
                <td>$${subtotal.toFixed(2)}</td>
                <td>
                    <input type="text" name="items[${id}][notas]" value="${item.notas}" placeholder="Notas..." style="width: 100%;">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-item-btn" data-id="${id}">X</button>
                </td>
            `;
            tbody.appendChild(newRow);
        }

        const impuestos = totalNeto * 0.16;
        const totalBruto = totalNeto + impuestos;

        document.getElementById('total-neto').innerText = `$${totalNeto.toFixed(2)}`;
        document.getElementById('total-impuestos').innerText = `$${impuestos.toFixed(2)}`;
        document.getElementById('total-bruto').innerText = `**$${totalBruto.toFixed(2)}**`;

        // Habilitar/deshabilitar botón de envío
        document.getElementById('submit-order-btn').disabled = totalNeto === 0;
    }

    document.addEventListener('click', function(e) {
        // Añadir Ítem
        if (e.target.classList.contains('add-to-order-btn')) {
            const itemElement = e.target.closest('.menu-item');
            const id = itemElement.dataset.id;
            const name = itemElement.dataset.name;
            const price = parseFloat(itemElement.dataset.price);

            if (orderItems[id]) {
                orderItems[id].cantidad++;
            } else {
                orderItems[id] = {
                    nombre: name,
                    precio: price,
                    cantidad: 1,
                    notas: ''
                };
            }
            updateOrderTable();
        }

        // Eliminar Ítem
        if (e.target.classList.contains('remove-item-btn')) {
            const id = e.target.dataset.id;
            delete orderItems[id];
            updateOrderTable();
        }
    });

    document.addEventListener('change', function(e) {
        // Cambiar Cantidad
        if (e.target.classList.contains('item-cantidad-input')) {
            const id = e.target.dataset.id;
            let cantidad = parseInt(e.target.value);
            
            if (isNaN(cantidad) || cantidad < 1) {
                cantidad = 1; // Forzar mínimo 1
                e.target.value = 1;
            }
            
            if (orderItems[id]) {
                orderItems[id].cantidad = cantidad;
                updateOrderTable();
            }
        }
    });

    // Cargar la vista inicial
    updateOrderTable();

</script>

<?php include '../includes/footer.php'; ?>