<?php
require_once 'config.php';

// Redirect to login.php if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit();
}

// Include the template
$template = $themeManager->getTemplate('index');
if ($template) {
    include $template;
} else {
    die("Index template not found.");
}
?>