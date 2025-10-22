<?php
include '../includes/header.php'; 

// Permiso: Sólo J1 y J2 pueden generar tokens.
if ($_SESSION['user_jerarquia'] > 2) {
    echo '<h1>Acceso Denegado</h1><p>Solo usuarios con Jerarquía 1 o 2 pueden autorizar tokens de recuperación.</p>';
    include '../includes/footer.php';
    exit;
}

$token_generated = '';
$error_message = '';

// Lógica de generación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['target_user_id'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    
    // Llamar a la función que valida la jerarquía y genera el token
    $token = generateRecoveryToken($target_user_id, $_SESSION['user_id']);

    if ($token) {
        $token_generated = "✅ Token Generado. Compártelo con el usuario: **{$token}**";
    } else {
        $error_message = "❌ Error al generar el token o no tienes permiso para autorizar a este nivel de usuario.";
    }
}

// Obtener la lista de usuarios que PUEDEN necesitar un token (J2 y J3)
try {
    // Excluir Super Admin (J1) y J4+ (auto-recuperación)
    $stmt = $pdo->query("
        SELECT u.id, u.nombre_completo, r.nombre AS rol_nombre, r.jerarquia
        FROM usuarios_restaurantes ur
        JOIN usuarios u ON ur.usuario_id = u.id
        JOIN roles r ON ur.rol_id = r.id
        WHERE r.jerarquia IN (2, 3) 
        ORDER BY r.jerarquia ASC, u.nombre_completo ASC
    ");
    $users_to_authorize = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error al cargar la lista de usuarios.";
    $users_to_authorize = [];
}
?>

<h1>Autorización de Tokens de Recuperación (J2 y J3)</h1>

<?php if ($token_generated): ?>
    <div class="alert alert-success"><?php echo $token_generated; ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<p>Selecciona el usuario que necesita el token. Solo puedes generar tokens para usuarios cuya jerarquía sea menor a la tuya (J1 genera para J2/J3; J2 genera solo para J3).</p>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre Completo</th>
            <th>Rol (Jerarquía)</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users_to_authorize as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                <td><?php echo htmlspecialchars($user['rol_nombre']); ?> (J<?php echo $user['jerarquia']; ?>)</td>
                <td>
                    <form action="" method="POST" style="display:inline;">
                        <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-warning">Generar Token</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>