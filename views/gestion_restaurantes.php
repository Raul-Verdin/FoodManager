<?php
// Desactivamos el switcher de restaurante para esta vista
$disable_restaurant_switcher = true;

include '../includes/header.php'; 

// Permiso requerido: Solo Super Administrador puede crear/eliminar restaurantes.
$permission_needed = 'gestion_plataforma'; 

if (!checkPermission($permission_needed)) {
    // Si no tiene permiso, bloqueamos la vista y salimos.
    logActivity($_SESSION['user_id'], 'ACCESS DENIED', "Intento de acceso a {$permission_needed}");
    echo '<h1><i class="fas fa-lock"></i> Acceso Denegado</h1><p>Esta vista es exclusiva del Super Administrador.</p>';
    include '../includes/footer.php'; 
    exit;
}

$message = '';
$error_message = '';

// ----------------------------------------------------------------------
// Lógica de Procesamiento (CREATE / DELETE / UPDETE)
// ----------------------------------------------------------------------

// Detectar si estamos editando un restaurante
$modo_edicion = false;
$restaurante_editar = null;

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $modo_edicion = true;
    $edit_id = (int)$_GET['edit_id'];

    // Cargar datos del restaurante a editar
    $stmt_edit = $pdo->prepare("SELECT * FROM restaurantes WHERE id = :id");
    $stmt_edit->bindParam(':id', $edit_id, PDO::PARAM_INT);
    $stmt_edit->execute();
    $restaurante_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);

    if (!$restaurante_editar) {
        $error_message = "Restaurante no encontrado.";
        $modo_edicion = false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. Lógica de CREACIÓN de Restaurante
    if (isset($_POST['action']) && $_POST['action'] == 'create') {
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $gerenteres_id = (int)$_POST['gerenteres_id'];
        
        if (empty($nombre) || empty($direccion) || $gerenteres_id <= 0) {
            $error_message = 'Todos los campos son obligatorios y debe seleccionar un Gerente.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO restaurantes (nombre, direccion, gerenteres_id) VALUES (:nombre, :direccion, :gerenteres_id)");
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':direccion', $direccion);
                $stmt->bindParam(':gerenteres_id', $gerenteres_id);
                $stmt->execute();
                
                // Además, asignamos el rol 'Gerente de Restaurante' al usuario en la tabla usuarios_restaurantes si no lo tiene.
                $rol_gerente_id = $pdo->query("SELECT id FROM roles WHERE nombre = 'gerente_restaurante'")->fetchColumn();
                $stmt_assign = $pdo->prepare("
                    INSERT INTO usuarios_restaurantes (usuario_id, restaurante_id, rol_id, fecha_ingreso) 
                    VALUES (:uid, :rid, :rolid, CURDATE())
                    ON DUPLICATE KEY UPDATE rol_id = rol_id; -- No hace nada si ya existe
                ");
                $stmt_assign->bindParam(':uid', $gerenteres_id);
                
                $restaurante_id = $pdo->lastInsertId();
                $stmt_assign->bindParam(':rid', $restaurante_id);
                
                $stmt_assign->bindParam(':rolid', $rol_gerente_id);
                $stmt_assign->execute();

                logActivity($_SESSION['user_id'], 'CREATE RESTAURANT', "Nuevo restaurante creado: " . $nombre);
                $message = "✅ Restaurante '{$nombre}' creado y asignado exitosamente.";

            } catch (PDOException $e) {
                $error_message = "Error al crear el restaurante: " . $e->getMessage();
                error_log("DB Error CREATE RESTAURANT: " . $e->getMessage());
            }
        }
    }
    
    // B Lógica de ACTUALIZACIÓN de Restaurante
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        $restaurante_id = (int)$_POST['restaurante_id'];
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $gerenteres_id = (int)$_POST['gerenteres_id'];

        if (empty($nombre) || empty($direccion) || $gerenteres_id <= 0) {
            $error_message = 'Todos los campos son obligatorios y debe seleccionar un Gerente.';
        } else {
            try {
                // Actualizar restaurante
                $stmt = $pdo->prepare("UPDATE restaurantes SET nombre = :nombre, direccion = :direccion, gerenteres_id = :gerenteres_id WHERE id = :id");
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':direccion', $direccion);
                $stmt->bindParam(':gerenteres_id', $gerenteres_id);
                $stmt->bindParam(':id', $restaurante_id);
                $stmt->execute();

                // También actualizamos la relación en usuarios_restaurantes
                $rol_gerente_id = $pdo->query("SELECT id FROM roles WHERE nombre = 'gerente_restaurante'")->fetchColumn();

                $stmt_assign = $pdo->prepare("
                    INSERT INTO usuarios_restaurantes (usuario_id, restaurante_id, rol_id, fecha_ingreso) 
                    VALUES (:uid, :rid, :rolid, CURDATE())
                    ON DUPLICATE KEY UPDATE rol_id = :rolid;
                ");
                $stmt_assign->bindParam(':uid', $gerenteres_id);
                $stmt_assign->bindParam(':rid', $restaurante_id);
                $stmt_assign->bindParam(':rolid', $rol_gerente_id);
                $stmt_assign->execute();

                logActivity($_SESSION['user_id'], 'UPDATE RESTAURANT', "Restaurante actualizado: ID $restaurante_id");
                $message = "✅ Restaurante actualizado correctamente.";

            } catch (PDOException $e) {
                $error_message = "Error al actualizar el restaurante: " . $e->getMessage();
                error_log("DB Error UPDATE RESTAURANT: " . $e->getMessage());
            }
        }
    }


    // C. Lógica de ELIMINACIÓN de Restaurante (TRANSACCIÓN SEGURA)
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $restaurante_id = (int)$_POST['restaurante_id'];
        
        // 1. Iniciar Transacción
        $pdo->beginTransaction();
        
        try {
            // NOTA: Gracias a ON DELETE CASCADE en las FK de usuarios_restaurantes, 
            // el registro del restaurante en la tabla 'restaurantes' se borrará.
            // Los usuarios asociados, los logs de actividad, etc., se mantendrán, 
            // pero las asociaciones en usuarios_restaurantes se borrarán automáticamente.
            
            // 2. Eliminar el registro principal
            $stmt = $pdo->prepare("DELETE FROM restaurantes WHERE id = :id");
            $stmt->bindParam(':id', $restaurante_id);
            $stmt->execute();
            
            // 3. Confirmar la Transacción (borrado seguro)
            $pdo->commit();
            
            logActivity($_SESSION['user_id'], 'DELETE RESTAURANT', "Restaurante ID: {$restaurante_id} eliminado.");
            $message = "✅ Restaurante ID {$restaurante_id} y todos sus datos asociados fueron eliminados de forma segura.";

        } catch (PDOException $e) {
            // 4. Revertir la Transacción si falla algo
            $pdo->rollBack();
            $error_message = "Error crítico al eliminar el restaurante. La operación fue revertida.";
            error_log("DB Error DELETE RESTAURANT: " . $e->getMessage());
        }
    }
}

// ----------------------------------------------------------------------
// Lógica de Lectura (READ)
// ----------------------------------------------------------------------

// Obtener la lista de posibles Gerentes de Restaurantes (solo usuarios que no son ya Gerentes de Restaurante)
try {
    $rol_gerente_id = $pdo->query("SELECT id FROM roles WHERE nombre = 'gerente_restaurante'")->fetchColumn();
    
    $stmt_gerentes = $pdo->prepare("
        SELECT id, nombre_completo 
        FROM usuarios 
        WHERE id NOT IN (
            SELECT usuario_id FROM usuarios_restaurantes WHERE rol_id = :rol_gerente_id
        ) 
        AND activo = TRUE 
        AND id != 101 -- Excluir Super Admin
        ORDER BY nombre_completo
    ");
    $stmt_gerentes->bindParam(':rol_gerente_id', $rol_gerente_id, PDO::PARAM_INT);
    $stmt_gerentes->execute();
    $potential_gerentes = $stmt_gerentes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la lista de Restaurantes con su Gerente asignado
    $stmt_list = $pdo->query("
        SELECT r.id, r.nombre, r.direccion, u.nombre_completo AS gerente_nombre
        FROM restaurantes r
        LEFT JOIN usuarios u ON r.gerenteres_id = u.id
        ORDER BY r.id ASC
    ");
    $restaurantes_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error al cargar datos del sistema.";
}
?>

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../style/gestion_restaurantes.css">
</head>

<h1>Gestión de Restaurantes</h1><br>

<button type="button" class="btn btn-primary toggle-form-btn" onclick="toggleForm()">
    <?php echo $modo_edicion ? 'Editar Restaurante' : '+ Crear Restaurante'; ?>
</button>

<div class="messages">
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
</div>

<div id="formContainer" class="form-toggle-container <?php echo $modo_edicion ? 'show' : ''; ?>">
    <h2><?php echo $modo_edicion ? 'Editar Restaurante' : 'Añadir Nuevo Restaurante'; ?></h2>

    <form action="gestion_restaurantes.php<?php echo $modo_edicion ? '?edit_id=' . $edit_id : ''; ?>" method="POST" class="form-crud">
        <input type="hidden" name="action" value="<?php echo $modo_edicion ? 'update' : 'create'; ?>">
        <?php if ($modo_edicion): ?>
            <input type="hidden" name="restaurante_id" value="<?php echo $restaurante_editar['id']; ?>">
        <?php endif; ?>

        <label for="nombre">Nombre del Restaurante:</label>
        <input type="text" name="nombre" id="nombre" required value="<?php echo $modo_edicion ? htmlspecialchars($restaurante_editar['nombre']) : ''; ?>">

        <label for="direccion">Dirección:</label>
        <textarea name="direccion" id="direccion" required><?php echo $modo_edicion ? htmlspecialchars($restaurante_editar['direccion']) : ''; ?></textarea>

        <label for="gerenteres_id">Gerente de Restaurante:</label>
        <select name="gerenteres_id" id="gerenteres_id" required>
            <option value="">-- Seleccionar Gerente --</option>
            <?php foreach ($potential_gerentes as $gerente): ?>
                <option value="<?php echo $gerente['id']; ?>" 
                    <?php echo ($modo_edicion && $gerente['id'] == $restaurante_editar['gerenteres_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($gerente['nombre_completo']); ?> (ID: <?php echo $gerente['id']; ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-<?php echo $modo_edicion ? 'success' : 'primary'; ?>">
            <?php echo $modo_edicion ? 'Guardar Cambios' : 'Crear Restaurante'; ?>
        </button>

        <?php if ($modo_edicion): ?>
            <a href="gestion_restaurantes.php" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
    </form>
</div><br>

<hr><br>

<h2>Restaurantes Activos</h2>
<div class="table-scroll-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Gerente Asignado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($restaurantes_list as $res): ?>
                <tr>
                    <td><?php echo $res['id']; ?></td>
                    <td><?php echo htmlspecialchars($res['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($res['direccion']); ?></td>
                    <td><?php echo htmlspecialchars($res['gerente_nombre'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="gestion_restaurantes.php?edit_id=<?php echo $res['id']; ?>" class="btn btn-secondary btn-sm">Editar</a>
                        
                        <form action="gestion_restaurantes.php" method="POST" style="display:inline;" onsubmit="return confirm('ADVERTENCIA: ¿Está seguro de eliminar el restaurante <?php echo htmlspecialchars($res['nombre']); ?>? Se borrarán todos los datos asociados.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="restaurante_id" value="<?php echo $res['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="../script/gestion_restaurantes.js"></script>

<?php include '../includes/footer.php'; ?>