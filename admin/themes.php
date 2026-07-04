<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

if (isset($_POST['activate'])) {
    if ($themeManager->activateTheme($_POST['theme'])) {
        $message = __('admin_activate_success');
    } else {
        $error = __('admin_activate_error');
    }
}

if (isset($_FILES['theme_zip'])) {
    $zipFile = $_FILES['theme_zip']['tmp_name'];
    if ($themeManager->importTheme($zipFile)) {
        $message = __('admin_import_success');
    } else {
        $error = __('admin_import_error');
    }
}

$themes = $themeManager->listThemes();
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= __('admin_themes_title') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/admin_style.css">
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
            <div class="flex items-center gap-4">
                <div style="background: rgba(112, 0, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px rgba(112, 0, 255, 0.1);">
                    <i class="fas fa-magic text-xl text-secondary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_themes_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;">THEME_ENGINE_v2</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <main class="animate-fade-in" style="animation-delay: 0.1s;">
            <?php if ($message): ?>
                <div class="badge badge-success w-full mb-8 py-4 flex items-center justify-center gap-2" style="font-size: 0.8rem; border-radius: 12px;">
                    <i class="fas fa-check-circle"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="badge badge-danger w-full mb-8 py-4 flex items-center justify-center gap-2" style="font-size: 0.8rem; border-radius: 12px; background: rgba(255, 45, 85, 0.1); color: var(--accent); border: 1px solid var(--accent);">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Import Section -->
            <div class="glass p-8 mb-12" style="border: 2px dashed rgba(255, 255, 255, 0.05); border-radius: 24px; background: rgba(0, 242, 255, 0.02);">
                <div class="flex items-center gap-4 mb-6">
                    <i class="fas fa-cloud-arrow-up text-primary"></i>
                    <h2 style="margin: 0; font-size: 0.95rem; color: white; font-weight: 800;"><?= __('admin_import_label') ?></h2>
                </div>
                <form method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-4">
                    <input type="file" name="theme_zip" accept=".zip" class="form-control flex-1">
                    <button type="submit" class="btn-modern btn-primary" style="padding: 0 45px;">
                        <i class="fas fa-upload mr-2"></i> <?= __('admin_import_button') ?>
                    </button>
                </form>
            </div>

            <!-- Grid -->
            <div class="flex items-center gap-4 mb-8">
                <i class="fas fa-th-large text-2xl text-primary"></i>
                <h2 style="margin: 0; font-size: 1.2rem; color: white; font-weight: 800;"><?= __('admin_installed_themes') ?></h2>
            </div>

            <div class="theme-grid">
                <?php foreach ($themes as $theme): ?>
                    <div class="glass theme-card <?= $theme['active'] ? 'active-theme' : '' ?>" style="<?= $theme['active'] ? 'border-color: rgba(0, 242, 255, 0.4); box-shadow: 0 0 30px rgba(0, 242, 255, 0.1);' : '' ?>">
                        <div class="theme-preview">
                            <?php if (isset($theme['screenshot']) && file_exists("../themes/{$theme['slug']}/{$theme['screenshot']}")): ?>
                                <img src="../themes/<?= $theme['slug'] ?>/<?= $theme['screenshot'] ?>" alt="Preview">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.05); font-size: 4rem;">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($theme['active']): ?>
                                <div style="position: absolute; top: 20px; right: 20px; z-index: 10;" class="badge badge-success"><?= __('admin_active') ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="p-6 flex-1 flex flex-col">
                            <h3 style="margin: 0 0 10px 0; font-size: 1.2rem; color: white; font-weight: 800;"><?= htmlspecialchars($theme['name']) ?></h3>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 25px; line-height: 1.6; flex: 1; opacity: 0.8;"><?= htmlspecialchars($theme['description'] ?? __('admin_no_desc')) ?></p>
                            
                            <div class="flex justify-between items-center mb-6" style="font-size: 0.65rem; opacity: 0.4; font-weight: 900; letter-spacing: 1px;">
                                <span>BUILD_v1.2.0</span>
                                <span>DEV_BY_<?= strtoupper(htmlspecialchars($theme['author'] ?? 'UNKNOWN')) ?></span>
                            </div>

                            <?php if (!$theme['active']): ?>
                                <form method="POST" class="mt-auto">
                                    <input type="hidden" name="theme" value="<?= $theme['slug'] ?>">
                                    <button type="submit" name="activate" class="btn-modern btn-primary w-full" style="padding: 12px;">
                                        <i class="fas fa-bolt mr-2"></i> <?= __('admin_activate_button') ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button disabled class="btn-modern btn-secondary w-full mt-auto" style="opacity: 0.4; cursor: not-allowed; border-color: var(--primary); color: var(--primary);">
                                    <i class="fas fa-check-double mr-2"></i> <?= __('admin_current_theme') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

        <footer style="margin-top: 60px; text-align: center; opacity: 0.2; font-size: 0.65rem; letter-spacing: 3px;">
            THEME_REGISTRY // STATUS_SYNCHRONIZED // <?= date('Y') ?>
        </footer>
    </div>
</body>
</html>


