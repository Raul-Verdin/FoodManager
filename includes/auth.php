<?php
session_start();
require_once 'connection.php';

// ----------------------------------------------------------------------
// A. VERIFICACIÓN BÁSICA DE AUTENTICACIÓN
// ----------------------------------------------------------------------
function checkAuth() {
    // Verifica si el usuario y el rol están definidos en la sesión
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rol_id'])) {
        // Si no está autenticado, destruye cualquier sesión y redirige al login
        session_unset();
        session_destroy();
        header("Location: /index.php");
        exit;
    }
}

// ----------------------------------------------------------------------
// B. CARGA CENTRALIZADA DE DATOS DEL USUARIO
// ----------------------------------------------------------------------
function loadUserSessionData($user_id) {
    global $pdo;

    try {
        // Consulta unida entre usuario, su rol y restaurante actual (si lo hay)
        $stmt = $pdo->prepare("
            SELECT 
                u.id AS user_id,
                u.nombre_completo,
                r.id AS rol_id,
                r.nombre AS rol_nombre,
                ur.restaurante_id
            FROM usuarios u
            LEFT JOIN usuarios_restaurantes ur ON u.id = ur.usuario_id AND ur.activo = 1
            LEFT JOIN roles r ON ur.rol_id = r.id
            WHERE u.id = :uid
            LIMIT 1
        ");
        $stmt->bindParam(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_full_name'] = $user['nombre_completo'] ?? 'Usuario';
            $_SESSION['user_rol_id'] = $user['rol_id'] ?? null;
            $_SESSION['user_rol_name'] = $user['rol_nombre'] ?? 'Sin rol';
            $_SESSION['restaurante_actual_id'] = $user['restaurante_id'] ?? null;
        } else {
            // Si no se encuentra el usuario, destruir sesión
            session_unset();
            session_destroy();
            header("Location: /index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error al cargar datos del usuario: " . $e->getMessage());
        session_unset();
        session_destroy();
        header("Location: /index.php");
        exit;
    }
}

// ----------------------------------------------------------------------
// C. VERIFICACIÓN DE PERMISOS POR ROL (El Core de la Seguridad)
// ----------------------------------------------------------------------
function checkPermission($permission_name) {
    global $pdo;

    // SuperAdmin o Gerente General (1 y 2) siempre tienen acceso
    if (in_array($_SESSION['user_rol_id'], [1, 2])) {
        return true;
    }

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(rp.id)
            FROM usuarios_restaurantes ur
            JOIN roles_permisos rp ON ur.rol_id = rp.rol_id
            JOIN permisos p ON rp.permiso_id = p.id
            WHERE ur.usuario_id = :user_id
              AND p.nombre = :permission_name
              AND ur.activo = 1
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':permission_name', $permission_name, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;

    } catch (PDOException $e) {
        error_log("Error de permiso: " . $e->getMessage());
        return false;
    }
}

// ----------------------------------------------------------------------
// D. FUNCIÓN PARA EL CONMUTADOR DE RESTAURANTE (Super Admin / Gerente de Restaurantes)
// ----------------------------------------------------------------------
function switchRestaurant($restaurant_id) {
    global $pdo;
    
    // Solo si el usuario es Super Admin (1) o Gerente de Restaurantes (2)
    if ($_SESSION['user_rol_id'] > 2) {
        return false; // Permiso denegado
    }
    
    // Verificamos que el usuario esté asociado al restaurante que quiere seleccionar
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(id) FROM usuarios_restaurantes 
            WHERE usuario_id = :user_id AND restaurante_id = :restaurant_id
        ");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() > 0 || $_SESSION['user_rol_id'] == 1) {
            // Si está asociado o es Super Admin, cambiamos la variable de contexto
            $_SESSION['restaurante_actual_id'] = $restaurant_id;
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error al cambiar de restaurante: " . $e->getMessage());
        return false;
    }
    return false;
}

// ----------------------------------------------------------------------
// E. REGISTRO DE ACTIVIDAD
// ----------------------------------------------------------------------
function logActivity($user_id, $action, $details = null) {
    global $pdo;
    
    // Captura la IP de origen y el User Agent del cliente
    $ip_origen = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO registro_actividad (usuario_id, accion, detalles, ip_origen, user_agent)
            VALUES (:user_id, :action, :details, :ip_origen, :user_agent)
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        $stmt->bindParam(':ip_origen', $ip_origen, PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
        
        $stmt->execute();
    } catch (PDOException $e) {
        // En caso de error en el log, solo lo registramos internamente (no detenemos el flujo del usuario)
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}

// ----------------------------------------------------------------------
// F. LÓGICA DE RECUPERACIÓN DE CUENTA POR JERARQUÍA (Generación de Token)
// ----------------------------------------------------------------------
/**
 * Genera el token de recuperación en la DB si el usuario solicitante tiene permiso.
 * Se utiliza para las jerarquías 2 y 3.
 *
 * @param int $user_to_recover_id ID del usuario que requiere el token.
 * @param int $solicitado_por_id ID del usuario superior que genera el token.
 * @return string|bool El token generado o false si falla.
 */
function generateRecoveryToken($user_to_recover_id, $solicitado_por_id) {
    global $pdo;

    $token = bin2hex(random_bytes(32)); // Token criptográficamente seguro
    $jerarquia_solicitante = $_SESSION['user_jerarquia'];

    // 1. Obtener la jerarquía del usuario a recuperar
    $stmt_target = $pdo->prepare("
        SELECT r.jerarquia
        FROM usuarios_restaurantes ur
        JOIN roles r ON ur.rol_id = r.id
        WHERE ur.usuario_id = :target_id
        ORDER BY r.jerarquia ASC LIMIT 1
    ");
    $stmt_target->bindParam(':target_id', $user_to_recover_id, PDO::PARAM_INT);
    $stmt_target->execute();
    $target_jerarquia = $stmt_target->fetchColumn();

    // 2. Aplicar la Lógica de Jerarquía para Generar Token:
    // Si Jerarquía del objetivo es 2 (Gerente Restaurante), SOLO Super Admin (J1) puede generarlo.
    if ($target_jerarquia == 2 && $jerarquia_solicitante != 1) {
        logActivity($solicitado_por_id, 'DENIED: Generación Token', 'Intento fallido de generar token para J2.');
        return false;
    }
    // Si Jerarquía del objetivo es 3 (Gerente General, RRHH, etc.), J1 o J2 pueden generarlo.
    if ($target_jerarquia == 3 && $jerarquia_solicitante > 2) {
        logActivity($solicitado_por_id, 'DENIED: Generación Token', 'Intento fallido de generar token para J3.');
        return false;
    }

    // 3. Insertar el token en la tabla
    try {
        $stmt = $pdo->prepare("
            INSERT INTO solicitudes_recuperacion (usuario_id, solicitado_por, token)
            VALUES (:usuario_id, :solicitado_por, :token)
        ");
        $stmt->bindParam(':usuario_id', $user_to_recover_id, PDO::PARAM_INT);
        $stmt->bindParam(':solicitado_por', $solicitado_por_id, PDO::PARAM_INT);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        logActivity($solicitado_por_id, 'GENERATE TOKEN', "Token generado para usuario ID: {$user_to_recover_id}");
        return $token;

    } catch (PDOException $e) {
        error_log("Error al insertar token: " . $e->getMessage());
        return false;
    }
}
?>
