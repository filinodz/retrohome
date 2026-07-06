<?php
// themes/cyberpunk/index.php
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= SITE_NAME ?> - 2077_ENDPOINT</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    
    <!-- CSS -->
    <!-- CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('css/') ?>footer.css">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <style>
        #game-container {
            position: fixed;
            inset: 0;
            background: #050510;
            z-index: 20000;
            display: none;
            flex-direction: column;
            border: 2px solid var(--primary);
        }
        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 20001;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            font-family: var(--font-heading);
            font-size: 0.7rem;
            cursor: pointer;
            clip-path: polygon(0 0, 90% 0, 100% 10px, 100% 100%, 10% 100%, 0 90%);
        }
    </style>
</head>
<body class="theme-cyberpunk">
    <div class="scanner-line"></div>
    <div class="app-container p-4 md:p-8">
        <header class="glass-no-clip animate__animated animate__glitch flex flex-col md:flex-row justify-between items-center p-6 mb-8">
            <div class="flex items-center gap-6">
                <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="h-16 filter drop-shadow(0 0 10px var(--primary))">
                <div>
                    <h1 class="text-3xl font-bold" style="color: var(--secondary); text-shadow: 0 0 10px var(--secondary);"><?= SITE_NAME ?></h1>
                    <span class="pixel-text" style="color: var(--primary);"><?= __('cp_protocol') ?></span>
                </div>
            </div>

            <div class="flex items-center gap-8 mt-4 md:mt-0">
                <div class="hidden md:flex gap-8">
                    <div class="text-center">
                        <div id="stats-game-count" class="text-2xl font-bold" style="color: var(--accent);">--</div>
                        <div class="text-xs opacity-50"><?= __('cp_rom_count') ?></div>
                    </div>
                    <div class="text-center">
                        <div id="stats-console-count" class="text-2xl font-bold" style="color: var(--primary);">--</div>
                        <div class="text-xs opacity-50"><?= __('cp_sys_cores') ?></div>
                    </div>
                </div>

                <div class="block">
                     <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                </div>
                <div class="relative">
                    <button id="menu-button" class="btn-neon flex items-center gap-3">
                        <i class="fas fa-terminal text-xs"></i>
                        <span><?= strtoupper(htmlspecialchars($_SESSION['username'])) ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div id="profile-menu" class="glass hidden absolute top-full right-0 mt-4 w-56 p-2 z-50">
                        <a href="<?= SITE_URL ?>/profile" class="block p-3 hover:bg-cyan-900 transition-colors text-sm font-bold"><?= __('cp_profile_access') ?></a>
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <a href="<?= SITE_URL ?>/admin/" class="block p-3 hover:bg-pink-900 transition-colors text-sm font-bold text-pink-400"><?= __('cp_admin_override') ?></a>
                        <?php endif; ?>
                        <div class="h-px bg-cyan-900 my-2"></div>
                        <a href="#" id="logout-btn" class="block p-3 hover:bg-red-900 transition-colors text-sm font-bold text-red-500"><?= __('cp_terminate_session') ?></a>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-col md:flex-row gap-6 mb-10">
            <div class="relative flex-1">
                <input type="text" id="game-search" class="search-input w-full pl-12" placeholder="<?= __('cp_enter_search') ?>">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-cyan-400"></i>
            </div>
            <div class="flex gap-4">
                <select id="console-filter" class="glass px-6 py-2 text-xs font-bold outline-none" style="background: var(--bg-card); color: var(--secondary);">
                    <option value=""><?= __('cp_all_systems') ?></option>
                </select>
                <div class="glass flex p-1">
                    <button id="view-grid" class="p-2 hover:text-cyan-400 active"><i class="fas fa-th-large"></i></button>
                    <button id="view-list" class="p-2 hover:text-cyan-400"><i class="fas fa-list"></i></button>
                </div>
            </div>
        </div>

        <div class="console-selector flex justify-center flex-wrap gap-4 mb-12">
            <!-- Injected by JS -->
        </div>

        <div id="games-list" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
            <!-- Injected by JS -->
        </div>

        <div id="game-container"></div>
        
        <div id="preview-container" class="fixed inset-0 flex items-center justify-center z-50 bg-black/90 backdrop-blur-xl" style="display: none;">
            <div id="preview-tv" class="relative w-full max-w-5xl p-4">
                <div class="glass p-4">
                    <div class="relative aspect-video bg-black overflow-hidden border-2 border-cyan-500/30">
                        <video id="preview-video" class="w-full h-full object-cover" autoplay muted loop></video>
                        <div id="preview-game-title" class="absolute bottom-6 left-6 text-2xl font-bold text-cyan-400 tracking-widest [text-shadow:_0_0_10px_rgba(0,251,255,0.5)]"></div>
                    </div>
                </div>
                <button id="close-preview-btn" class="absolute -top-4 -right-4 btn-neon w-12 h-12 flex items-center justify-center text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>const SITE_URL = "<?= SITE_URL ?>"; window.RETROHOME_USER = "<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES) ?>";</script>
    <?php include BASE_PATH . '/includes/js_translations.php'; ?>
    <script src="<?= SITE_URL ?>/public/js/video-preview.js"></script>
    <script src="<?= SITE_URL ?>/public/js/netplay-fix.js?v=<?= @filemtime(BASE_PATH . '/public/js/netplay-fix.js') ?>"></script>
    <script src="<?= SITE_URL ?>/public/js/script.js?v=<?= @filemtime(BASE_PATH . '/public/js/script.js') ?>"></script>
</body>
</html>
