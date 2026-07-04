<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $settings->set($key, $value);
    }
    $message = __('admin_settings_updated');
}

$allSettings = $settings->getAll();

// Define known settings with human names for better UI
$knownSettings = [
    'site_name' => __('install_site_name'),
    'screenscraper_user' => __('install_ss_user'),
    'screenscraper_password' => __('install_ss_pass'),
    'site_url' => __('install_site_url'),
];

$excludedSettings = ['screenscraper_devid', 'screenscraper_devpass'];

?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= __('admin_sys_config') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/admin_style.css?v=<?= @filemtime(__DIR__ . "/public/css/admin_style.css") ?>">
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
            <div class="flex items-center gap-4">
                <div style="background: rgba(255, 45, 85, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px rgba(255, 45, 85, 0.1);">
                    <i class="fas fa-sliders-h text-xl text-accent"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_sys_config') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;">SYSTEM_REGISTRY_v2</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <main class="animate-fade-in" style="animation-delay: 0.1s;">
            <div class="glass stat-card" style="max-width: 900px; margin: 0 auto; padding: 45px;">
                <?php if ($message): ?>
                    <div class="badge badge-success w-full mb-8 py-4 flex items-center justify-center gap-2" style="font-size: 0.8rem; border-radius: 12px;">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="grid gap-6">
                        <?php 
                        // Combine settings for the loop
                        $displaySettings = array_merge($knownSettings, array_diff_key($allSettings, array_flip($excludedSettings), $knownSettings));
                        
                        foreach ($displaySettings as $key => $labelOrValue): 
                            $label = isset($knownSettings[$key]) ? $knownSettings[$key] : $key;
                            // Skip site_theme as it's managed via Themes page
                            if ($key === 'site_theme') continue;
                            
                            $value = $settings->get($key);
                            $isPassword = (strpos($key, 'password') !== false || strpos($key, 'pass') !== false);
                        ?>
                            <div class="form-group">
                                <label for="setting_<?= $key ?>"><?= mb_strtoupper($label) ?></label>
                                <input type="<?= $isPassword ? 'password' : 'text' ?>" 
                                       id="setting_<?= $key ?>"
                                       name="settings[<?= $key ?>]" 
                                       value="<?= htmlspecialchars($value ?? '') ?>" 
                                       class="form-control" 
                                       placeholder="<?= __('enter') ?> <?= strtolower($label) ?>...">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-10 flex justify-center">
                        <button type="submit" class="btn-modern btn-primary" style="padding: 18px 80px; font-size: 0.95rem;">
                            <i class="fas fa-save mr-2"></i> <?= __('admin_save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <footer style="margin-top: 60px; text-align: center; opacity: 0.2; font-size: 0.65rem; letter-spacing: 3px;">
            SETTINGS_CORE // STATUS_ENCRYPTED // <?= date('Y') ?>
        </footer>
    </div>
</body>
</html>


