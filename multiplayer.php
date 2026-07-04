<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

$pageTitle = 'Multiplayer - ' . SITE_NAME;

$template = $themeManager->getTemplate('multiplayer');
if ($template) {
    include $template;
} else {
    die('Multiplayer template not found.');
}
