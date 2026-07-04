<?php
// logout.php — Déconnexion propre de l'utilisateur
require_once 'config.php';

// Vider et détruire la session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirection vers la page de connexion
header('Location: ' . SITE_URL . '/login');
exit();
