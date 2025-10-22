<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'FOODMANAGER_DB');
define('DB_USER', 'root');
define('DB_PASS', '12345678');

try {
    // Crea una nueva instancia de PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    
    // Configura PDO para lanzar excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Deshabilita la emulación de prepared statements (mejora la seguridad)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // Si la conexión falla, detiene la ejecución y muestra un error
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>