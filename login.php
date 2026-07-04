<?php
require_once 'config.php';

// Redirect to index.php if logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/');
    exit();
}

// Include the template
$template = $themeManager->getTemplate('login');
if ($template) {
    include $template;
} else {
    die("Login template not found.");
}
?>