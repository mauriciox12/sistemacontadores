<?php
session_start();

// Vaciar variables
$_SESSION = array();

// Destruir cookie de sesiиоn
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Aniquilar sesiиоn y volver al login
session_destroy();
header("Location: login.php");
exit;
?>