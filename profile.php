<?php
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit();
}

$userId = $_SESSION['user_id']; 
$username = $_SESSION['username'];

// Include the template
$template = $themeManager->getTemplate('profile');
if ($template) {
    include $template;
} else {
    die("Profile template not found.");
}
?>