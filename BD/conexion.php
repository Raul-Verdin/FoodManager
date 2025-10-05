<?php
$servername = "localhost"; 
$username = "root"; 
$password = "12345678";
$dbname = "foodmanager"; 

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Revisar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Opcional: establecer charset utf8 para evitar problemas con acentos
$conn->set_charset("utf8");
?>