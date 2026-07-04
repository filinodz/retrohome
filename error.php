<?php
require_once 'config.php';

$error_code = $_GET['error'] ?? 'unknown_error';

// Localization
$error_title = __('error_title') ?? 'Oops!';
$error_message = __('error_message') ?? 'Something went wrong.';

switch ($error_code) {
    case 'user_not_found':
        $error_title = __('user_not_found_title') ?? 'User Not Found';
        $error_message = __('user_not_found_msg') ?? 'The user you are looking for does not exist.';
        break;
    case 'profile_private':
        $error_title = __('profile_private_title') ?? 'Private Profile';
        $error_message = __('profile_private_msg') ?? 'This profile is private and only visible to the owner.';
        break;
    case 'invalid_user':
        $error_title = __('invalid_user_title') ?? 'Invalid User';
        $error_message = __('invalid_user_msg') ?? 'The requested user is invalid.';
        break;
}

$pageTitle = $error_title . " - RetroHome";

// Include theme template
$template = $themeManager->getTemplate('error');
if ($template) {
    include $template;
} else {
    // Fallback simple design if theme doesn't have an error template
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= $pageTitle ?></title>
        <style>
            body { background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
            .card { background: rgba(255,255,255,0.05); padding: 2rem; border-radius: 1rem; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
            a { color: #3b82f6; text-decoration: none; margin-top: 1rem; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1><?= $error_title ?></h1>
            <p><?= $error_message ?></p>
            <a href="<?= SITE_URL ?>/">Back to Home</a>
        </div>
    </body>
    </html>
    <?php
}
?>
