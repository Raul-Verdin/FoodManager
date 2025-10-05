<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: ../index_login.php");
        exit;
    }
}

function requireRole($rolRequerido) {
    requireLogin();

    if ($_SESSION['rol'] != $rolRequerido) {
        header("Location: acceso_denegado.php");
        exit;
    }
}

function userHasRole($rol) {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === $rol;
}

define('ACCESS_DENIED_MESSAGE', 'No tienes permisos para acceder a esta sección.');

function showAccessDenied($mensaje = ACCESS_DENIED_MESSAGE) { 
    echo "<div style='padding: 10px; background-color: #ffd5d5; border: 1px solid red; margin-top: 20px;'>
            <strong>⚠ Acceso denegado:</strong> $mensaje
          </div>";
}
