<?php
// Security Check: If config.php already exists and works, block installation
$isInstalled = false;
$configFile = '../config.php';
if (file_exists($configFile)) {
    try {
        require_once $configFile;
        if (defined('DB_NAME') && $db) {
            $isInstalled = true;
        }
    } catch (Exception $e) {
        $isInstalled = false;
    }
}

// Language Detection for Installer (standalone)
$availableLangs = ['fr', 'en', 'ar'];
$currentLang = 'fr';
if (isset($_GET['lang']) && in_array($_GET['lang'], $availableLangs)) {
    $currentLang = $_GET['lang'];
    setcookie('lang', $currentLang, time() + 3600, '/');
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $availableLangs)) {
    $currentLang = $_COOKIE['lang'];
}

$translations = [];
$langFile = '../lang/' . $currentLang . '.php';
if (file_exists($langFile)) {
    $translations = require $langFile;
}

function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

$isRTL = ($currentLang === 'ar');

// Requirements Check
$requirements = [
    'php' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo' => extension_loaded('pdo_mysql'),
    'gd' => extension_loaded('gd'),
    'config_writable' => is_writable('../') || (file_exists($configFile) && is_writable($configFile)),
];
$allMet = !in_array(false, $requirements);

$default_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace('/install/index.php', '', $_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= t('install_title') ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .lang-switcher { position: fixed; top: 20px; right: 20px; z-index: 1000; display: flex; gap: 10px; }
        .lang-btn { background: var(--glass); border: 1px solid var(--border); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; transition: all 0.2s; }
        .lang-btn:hover { background: var(--primary); border-color: var(--primary); }
        .lang-btn.active { background: var(--primary); border-color: var(--primary); }
        [dir="rtl"] .lang-switcher { right: auto; left: 20px; }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="?lang=fr" class="lang-btn <?= $currentLang == 'fr' ? 'active' : '' ?>">FR</a>
        <a href="?lang=en" class="lang-btn <?= $currentLang == 'en' ? 'active' : '' ?>">EN</a>
        <a href="?lang=ar" class="lang-btn <?= $currentLang == 'ar' ? 'active' : '' ?>">AR</a>
    </div>

    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="retro-details top-left">SYSTEM_BOOT_SEQUENCE: v1.0.4<br>MEM_CHECK: OK</div>
    <div class="retro-details bottom-right">AWAITING_INPUT<br>READY._</div>

    <?php if ($isInstalled): ?>
    <div class="installer-card animate__animated animate__shakeX">
        <header>
            <img src="../public/img/logo_new.png" alt="Logo" class="logo">
            <h1 style="color: var(--error);"><?= t('install_locked_title') ?></h1>
            <p class="subtitle"><?= t('install_locked_msg') ?></p>
        </header>
        <div class="alert alert-error" style="display: flex; margin-bottom: 20px;">
            <i class="fas fa-shield-alt"></i>
            <span><?= t('install_secure_warn') ?></span>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-primary"><?= t('install_goto_site') ?></a>
        </div>
    </div>
    <?php else: ?>
    <div class="installer-card">
        <header>
            <img src="../public/img/logo_new.png" alt="Logo" class="logo">
            <h1>RetroHome</h1>
            <p class="subtitle"><?= t('install_subtitle') ?></p>
        </header>

        <div id="req-panel" style="margin-bottom: 25px;">
            <label><?= t('install_req_title') ?></label>
            <div class="requirements-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                <div class="req-item <?= $requirements['php'] ? 'met' : 'failed' ?>" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; font-size: 11px; display: flex; justify-content: space-between;">
                    <span><?= t('install_req_php') ?></span>
                    <i class="fas <?= $requirements['php'] ? 'fa-check-circle text-success' : 'fa-times-circle text-error' ?>"></i>
                </div>
                <div class="req-item <?= $requirements['pdo'] ? 'met' : 'failed' ?>" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; font-size: 11px; display: flex; justify-content: space-between;">
                    <span><?= t('install_req_pdo') ?></span>
                    <i class="fas <?= $requirements['pdo'] ? 'fa-check-circle text-success' : 'fa-times-circle text-error' ?>"></i>
                </div>
                <div class="req-item <?= $requirements['gd'] ? 'met' : 'failed' ?>" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; font-size: 11px; display: flex; justify-content: space-between;">
                    <span><?= t('install_req_gd') ?></span>
                    <i class="fas <?= $requirements['gd'] ? 'fa-check-circle text-success' : 'fa-times-circle text-error' ?>"></i>
                </div>
                <div class="req-item <?= $requirements['config_writable'] ? 'met' : 'failed' ?>" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; font-size: 11px; display: flex; justify-content: space-between;">
                    <span><?= t('install_req_write') ?></span>
                    <i class="fas <?= $requirements['config_writable'] ? 'fa-check-circle text-success' : 'fa-times-circle text-error' ?>"></i>
                </div>
            </div>
            <?php if (!$allMet): ?>
            <div class="alert alert-error" style="margin-top: 15px; font-size: 12px;">
                <?= t('install_req_fail') ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="step-indicator">
            <div class="step-dot active" data-step="1"></div>
            <div class="step-dot" data-step="2"></div>
            <div class="step-dot" data-step="3"></div>
            <div class="step-dot" data-step="4"></div>
        </div>

        <div id="error-container" class="alert alert-error" style="display: none;">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="error-message"></span>
        </div>

        <!-- Step 1: Database -->
        <div id="step-1" class="step-content">
            <label><?= t('install_step_db') ?></label>
            <div class="form-group">
                <label for="db_host"><?= t('install_db_host') ?></label>
                <input type="text" id="db_host" placeholder="localhost" value="localhost">
            </div>
            <div class="form-group">
                <label for="db_user"><?= t('install_db_user') ?></label>
                <input type="text" id="db_user" placeholder="root" value="root">
            </div>
            <div class="form-group">
                <label for="db_pass"><?= t('install_db_pass') ?></label>
                <input type="password" id="db_pass" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label for="db_name"><?= t('install_db_name') ?></label>
                <input type="text" id="db_name" placeholder="retro" value="retro">
                <p class="info-text"><?= t('install_db_info') ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="nextStep(2)"><?= t('install_next') ?></button>
            </div>
        </div>

        <!-- Step 2: Site Configuration -->
        <div id="step-2" class="step-content" style="display: none;">
            <label><?= t('install_step_site') ?></label>
            <div class="form-group">
                <label for="site_name"><?= t('install_site_name') ?></label>
                <input type="text" id="site_name" placeholder="Mon RetroHome" value="RetroHome">
            </div>
            <div class="form-group">
                <label for="site_url"><?= t('install_site_url') ?></label>
                <input type="text" id="site_url" value="<?= $default_url ?>">
            </div>
            <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--border);">
            <label><i class="fas fa-image"></i> <?= t('install_ss_config') ?></label>
            <p class="info-text"><?= t('install_ss_info') ?></p>
            <div class="form-group" style="margin-top: 15px;">
                <label for="ss_user"><?= t('install_ss_user') ?></label>
                <input type="text" id="ss_user" placeholder="Votre pseudo">
            </div>
            <div class="form-group">
                <label for="ss_pass"><?= t('install_ss_pass') ?></label>
                <input type="password" id="ss_pass" placeholder="••••••••">
            </div>
            <div class="alert alert-info" style="margin-top: 10px;">
                <i class="fas fa-info-circle"></i>
                <p style="font-size: 12px;"><?= t('install_ss_create') ?></p>
            </div>
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="prevStep(1)"><?= t('install_prev') ?></button>
                <button class="btn btn-primary" onclick="nextStep(3)"><?= t('install_next') ?></button>
            </div>
        </div>

        <!-- Step 3: Admin Account -->
        <div id="step-3" class="step-content" style="display: none;">
            <label><?= t('install_step_admin') ?></label>
            <div class="form-group">
                <label for="admin_user"><?= t('install_admin_user') ?></label>
                <input type="text" id="admin_user" placeholder="admin" value="admin">
            </div>
            <div class="form-group">
                <label for="admin_email"><?= t('install_admin_email') ?></label>
                <input type="email" id="admin_email" placeholder="admin@example.com">
            </div>
            <div class="form-group">
                <label for="admin_pass"><?= t('install_admin_pass') ?></label>
                <input type="password" id="admin_pass" placeholder="••••••••">
            </div>
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="prevStep(2)"><?= t('install_prev') ?></button>
                <button class="btn btn-primary" onclick="runInstallation()"><?= t('install_run') ?></button>
            </div>
        </div>

        <!-- Step 4: Installation Progress -->
        <div id="step-4" class="step-content" style="display: none; text-align: center;">
            <div id="loader" style="margin-bottom: 20px;">
                <i class="fas fa-circle-notch fa-spin fa-3x" style="color: var(--primary);"></i>
            </div>
            <h2 id="install-title"><?= t('install_processing') ?></h2>
            <p id="install-msg" class="subtitle"><?= t('install_proc_msg') ?></p>
            
            <div id="success-group" style="display: none; margin-top: 30px;">
                <div style="color: var(--success); font-size: 48px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <p class="subtitle" style="margin-bottom: 30px;"><?= t('install_success') ?></p>
                <a href="../index.php" class="btn btn-primary"><?= t('install_goto_site') ?></a>
            </div>
        </div>
    </div>

    <script>
        const translations = {
            err_db: "<?= t('install_err_db') ?>",
            err_site: "<?= t('install_err_site') ?>",
            err_admin: "<?= t('install_err_admin') ?>",
            err_generic: "<?= t('install_err_generic') ?>"
        };

        let currentStep = 1;

        function nextStep(step) {
            // Basic validation
            if (currentStep === 1) {
                if (!document.getElementById('db_host').value || !document.getElementById('db_name').value) {
                    showError(translations.err_db);
                    return;
                }
            }
            if (currentStep === 2) {
                if (!document.getElementById('site_name').value || !document.getElementById('site_url').value) {
                    showError(translations.err_site);
                    return;
                }
            }

            hideError();
            document.getElementById('step-' + currentStep).style.display = 'none';
            document.getElementById('step-' + step).style.display = 'block';
            
            document.querySelector('.step-dot[data-step="' + currentStep + '"]').classList.remove('active');
            document.querySelector('.step-dot[data-step="' + step + '"]').classList.add('active');
            
            currentStep = step;
        }

        function prevStep(step) {
            hideError();
            document.getElementById('step-' + currentStep).style.display = 'none';
            document.getElementById('step-' + step).style.display = 'block';

            document.querySelector('.step-dot[data-step="' + currentStep + '"]').classList.remove('active');
            document.querySelector('.step-dot[data-step="' + step + '"]').classList.add('active');

            currentStep = step;
        }

        function showError(msg) {
            const container = document.getElementById('error-container');
            const message = document.getElementById('error-message');
            message.innerText = msg;
            container.style.display = 'flex';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function hideError() {
            document.getElementById('error-container').style.display = 'none';
        }

        async function runInstallation() {
            if (!document.getElementById('admin_user').value || !document.getElementById('admin_pass').value) {
                showError(translations.err_admin);
                return;
            }

            nextStep(4);

            const data = {
                db_host: document.getElementById('db_host').value,
                db_user: document.getElementById('db_user').value,
                db_pass: document.getElementById('db_pass').value,
                db_name: document.getElementById('db_name').value,
                site_name: document.getElementById('site_name').value,
                site_url: document.getElementById('site_url').value,
                ss_user: document.getElementById('ss_user').value,
                ss_pass: document.getElementById('ss_pass').value,
                admin_user: document.getElementById('admin_user').value,
                admin_email: document.getElementById('admin_email').value,
                admin_pass: document.getElementById('admin_pass').value
            };

            try {
                const response = await fetch('setup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('loader').style.display = 'none';
                    document.getElementById('install-title').style.display = 'none';
                    document.getElementById('install-msg').style.display = 'none';
                    document.getElementById('success-group').style.display = 'block';
                } else {
                    prevStep(3);
                    showError(result.message || translations.err_generic);
                }
            } catch (error) {
                prevStep(3);
                showError("Erreur de connexion : " + error.message);
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
