<?php
// themes/classic/index.php (Original Design)
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= SITE_NAME ?> - Station Retro</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/vendor/animatecss/4.1.1/animate.min.css">
    <link rel="stylesheet" href="<?php echo get_theme_asset('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo get_theme_asset('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/public/css/footer.css">
    
    <style>
        #game-container {
            position: fixed;
            inset: 0;
            background: #000;
            z-index: 20000;
            display: none;
            flex-direction: column;
        }
    </style>
</head>
<body class="bg-background theme-classic">
    <div class="container mx-auto px-4 py-8">
        <header class="classic-header animate__animated animate__fadeInDown p-6 rounded-2xl mb-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex items-center gap-6">
                    <img id="header-logo" src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="w-16 h-16 transition-all duration-300">
                    <div>
                        <h1 class="pixel-font text-3xl text-primary"><?= SITE_NAME ?></h1>
                        <p class="text-xs tracking-widest opacity-70 uppercase"><?= __('your_retro_station') ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <div class="hidden lg:flex gap-6 mr-6 opacity-60">
                        <div class="text-center border-r border-border-color pr-6">
                             <div id="stats-game-count" class="text-xl font-bold text-secondary">--</div>
                             <div class="text-[10px] uppercase"><?= __('stats_games') ?></div>
                        </div>
                        <div class="text-center">
                             <div id="stats-console-count" class="text-xl font-bold text-primary">--</div>
                             <div class="text-[10px] uppercase"><?= __('stats_consoles') ?></div>
                        </div>
                    </div>

                    <div class="hidden lg:block">
                        <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                    </div>
                    <div class="relative group">
                        <button id="menu-button" class="profile-btn flex items-center gap-3 px-4 py-2 rounded-xl transition-all">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span class="font-bold text-sm"><?= strtoupper(htmlspecialchars($_SESSION['username'])) ?></span>
                            <i class="fas fa-chevron-down text-[10px] opacity-50"></i>
                        </button>
                        <div id="profile-menu" class="hidden absolute top-full right-0 mt-3 w-56 glass-menu rounded-xl overflow-hidden py-2 z-50">
                            <a href="<?= SITE_URL ?>/profile" class="flex items-center gap-3 px-4 py-3 hover:bg-primary hover:text-black transition-all font-bold text-xs"><i class="fas fa-id-card"></i> <?= __('my_profile') ?></a>
                            <?php if($_SESSION['role'] === 'admin'): ?>
                                <a href="<?= SITE_URL ?>/admin/" class="flex items-center gap-3 px-4 py-3 hover:bg-secondary hover:text-black transition-all font-bold text-xs" style="color: var(--secondary);"><i class="fas fa-tools"></i> <?= __('admin_panel') ?></a>
                            <?php endif; ?>
                            <div class="h-px bg-white/10 my-2"></div>
                            <a href="#" id="logout-btn" class="flex items-center gap-3 px-4 py-3 hover:bg-red-500 hover:text-white transition-all font-bold text-xs text-red-400"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row items-center gap-4 mt-8">
                <div class="search-box-modern flex-1 relative w-full">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary opacity-50"></i>
                    <input type="text" id="game-search" class="w-full bg-black/30 border border-white/10 rounded-xl py-3 pl-12 pr-4 text-sm focus:border-primary outline-none transition-all" placeholder="<?= __('search_placeholder') ?>">
                </div>
                <div class="flex items-center gap-4 w-full md:w-auto">
                    <div class="console-filter-wrapper flex-1 md:flex-initial">
                        <select id="console-filter" class="w-full bg-black/30 border border-white/10 rounded-xl py-3 px-6 text-sm focus:border-primary outline-none appearance-none transition-all cursor-pointer">
                            <option value=""><?= __('all_consoles') ?></option>
                        </select>
                    </div>
                    <div class="bg-black/30 border border-white/10 p-1 rounded-xl flex">
                        <button id="view-grid" class="p-2 w-10 h-10 rounded-lg transition-all hover:text-primary active"><i class="fas fa-th-large"></i></button>
                        <button id="view-list" class="p-2 w-10 h-10 rounded-lg transition-all hover:text-primary"><i class="fas fa-list"></i></button>
                    </div>
                </div>
            </div>
        </header>

        <div class="console-selector mb-12">
            <!-- Injected by JS -->
        </div>

        <div id="games-list" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
            <!-- Injected by JS -->
        </div>

        <div id="game-container"></div>

        <!-- Hidden containers for Preview -->
        <div id="preview-container" class="fixed inset-0 bg-black bg-opacity-90 backdrop-blur-md flex items-center justify-center z-50 transition-all duration-300" style="display: none;">
            <div id="preview-tv" class="relative w-11/12 md:w-3/4 max-w-4xl animate__animated animate__zoomIn">
                <div class="glass" style="padding: 20px; border-radius: 20px;">
                    <div style="aspect-ratio: 16/9; background: #000; overflow: hidden; border-radius: 10px; position: relative;">
                        <video id="preview-video" style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop></video>
                        <div id="preview-game-title" class="pixel-text" style="position: absolute; bottom: 20px; left: 20px; font-size: 1rem; text-shadow: 2px 2px #000; color: var(--secondary);"></div>
                    </div>
                </div>
                <button id="close-preview-btn" class="btn-neon" style="position: absolute; top: 15px; right: 15px; border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
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
