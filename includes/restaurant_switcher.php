<?php
// restaurant_switcher.php

if (isset($disable_restaurant_switcher) && $disable_restaurant_switcher) {
    return; // No mostramos el switcher en esta vista
}


if (!isset($_SESSION['user_id']) || $_SESSION['user_jerarquia'] > 2) {
    return; // Solo visible para Jerarquía 1 y 2
}

// Lógica para obtener el nombre del restaurante actual
function getRestaurantName($id) {
    global $pdo;
    if (!$id) return 'Seleccionar Restaurante';
    try {
        $stmt = $pdo->prepare("SELECT nombre FROM restaurantes WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'Restaurante Desconocido';
    } catch (PDOException $e) {
        error_log("Error al obtener nombre de restaurante: " . $e->getMessage());
        return 'Error de DB';
    }
}

// Lógica para obtener todos los restaurantes que el usuario puede ver
function getUserRestaurants($user_id, $is_super_admin) {
    global $pdo;
    $sql = "SELECT id, nombre FROM restaurantes";
    
    // Si NO es Super Admin, filtramos por la asignación en usuarios_restaurantes
    if (!$is_super_admin) {
        $sql .= " WHERE id IN (SELECT restaurante_id FROM usuarios_restaurantes WHERE usuario_id = :user_id)";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        if (!$is_super_admin) {
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener lista de restaurantes: " . $e->getMessage());
        return [];
    }
}

// Lógica para procesar el cambio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_res'])) {
    $new_res_id = (int)$_POST['restaurant_id_selector'];
    if (switchRestaurant($new_res_id)) {
        header("Location: " . $_SERVER['REQUEST_URI']); // Recarga la página
        exit;
    }
}

$is_super_admin = $_SESSION['user_jerarquia'] == 1;
$available_restaurants = getUserRestaurants($_SESSION['user_id'], $is_super_admin);
?>

<form action="" method="POST" class="restaurant-switcher-form">
    <i class="fas fa-sync-alt"></i>
    <select name="restaurant_id_selector" onchange="this.form.submit()">
        <?php foreach ($available_restaurants as $res): ?>
            <option value="<?php echo $res['id']; ?>"
                <?php echo ($res['id'] == $restaurante_actual_id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($res['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="switch_res" value="1">
</form>