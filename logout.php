<?php
// ==============================================================================
// ARCHIVO: logout.php
// DESCRIPCIÓN: Cierra la sesión activa del usuario y lo redirige al login.
// ==============================================================================

session_start();

// Limpia todas las variables de sesión
$_SESSION = [];

// Si se usan cookies de sesión, borra también la cookie en el navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruye la sesión en el servidor
session_destroy();

// Redirige al login
header("Location: login.php");
exit;
