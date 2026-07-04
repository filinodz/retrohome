<?php
// templates/index.php (Modern Version)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= SITE_NAME ?> - Station Retro</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    
    <!-- CSS -->
    <!-- CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    
    <style>
        /* Extra bits for the main page */
        #game-container {
            position: fixed;
            inset: 0;
            background: #000;
            z-index: 1000;
            display: none;
            flex-direction: column;
        }
        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-family: var(--font-pixel);
            font-size: 0.7rem;
            cursor: pointer;
            box-shadow: var(--shadow-neon);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate__animated animate__fadeInDown">
            <div class="flex items-center gap-4">
                <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="logo-main">
                <div>
                    <h1 class="pixel-text" style="margin: 0; color: var(--primary); font-size: 1.2rem;"><?= SITE_NAME ?></h1>
                    <span class="pixel-text" style="font-size: 0.6rem; color: var(--text-muted);">RETRO_SYSTEM_READY [v2.0]</span>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <!-- Quick Stats -->
                <div class="hidden md:flex gap-6">
                    <div class="text-center">
                        <div id="stats-game-count" class="pixel-text" style="color: var(--secondary); font-size: 1.2rem;">--</div>
                        <div class="pixel-text" style="font-size: 0.5rem;">GAMES</div>
                    </div>
                    <div class="text-center">
                        <div id="stats-console-count" class="pixel-text" style="color: var(--accent); font-size: 1.2rem;">--</div>
                        <div class="pixel-text" style="font-size: 0.5rem;">CONSOLES</div>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="relative">
                    <button id="menu-button" class="glass" style="padding: 10px 20px; border-radius: 50px; display: flex; align-items: center; gap: 10px; cursor: pointer; color: white;">
                        <i class="fas fa-user-circle"></i>
                        <span class="pixel-text" style="font-size: 0.7rem;"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                    </button>
                    <!-- Menu Dropdown -->
                    <div id="profile-menu" class="glass hidden" style="position: absolute; top: 100%; right: 0; mt: 10px; width: 200px; padding: 10px; z-index: 100;">
                        <a href="profile" class="pixel-text" style="display: block; padding: 10px; color: white; text-decoration: none; font-size: 0.6rem;">PROFIL</a>
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <a href="admin/" class="pixel-text" style="display: block; padding: 10px; color: var(--accent); text-decoration: none; font-size: 0.6rem;">ADMINISTRATION</a>
                        <?php endif; ?>
                        <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 5px 0;">
                        <a href="#" id="logout-btn" class="pixel-text" style="display: block; padding: 10px; color: var(--primary); text-decoration: none; font-size: 0.6rem;">DECONNEXION</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Search & Filter Area -->
        <div class="search-wrapper animate__animated animate__fadeInUp flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-1">
                <input type="text" id="game-search" class="search-input w-full" placeholder="RECHERCHER_UN_JEU...">
                <i class="fas fa-search absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            <div class="flex gap-2">
                <select id="console-filter" class="glass pixel-text px-4 py-2" style="border-radius: 50px; background: rgba(255,255,255,0.05); color: white; border: 1px solid var(--glass-border); min-width: 150px;">
                    <option value="">TOUTES</option>
                </select>
                <div class="glass flex p-1" style="border-radius: 50px; border: 1px solid var(--glass-border);">
                    <button id="view-grid" class="view-btn active p-2" title="Grille">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button id="view-list" class="view-btn p-2" title="Liste">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Console Selector -->
        <div class="console-selector flex justify-center flex-wrap gap-4 mb-10 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <!-- Injected by JS -->
        </div>

        <!-- Games Grid -->
        <div id="games-list" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 animate__animated animate__fadeInUp" style="display: grid; animation-delay: 0.4s">
            <!-- Injected by JS -->
        </div>

        <!-- Hidden containers -->
        <div id="game-container"></div>
        
        <div id="preview-container" class="fixed inset-0 bg-black bg-opacity-90 backdrop-blur-md flex items-center justify-center z-50" style="display: none;">
            <div id="preview-tv" class="relative w-11/12 md:w-3/4 max-w-4xl">
                <div class="glass" style="padding: 20px; border-radius: 20px;">
                    <div style="aspect-ratio: 16/9; background: #000; overflow: hidden; border-radius: 10px; position: relative;">
                        <video id="preview-video" style="width: 100%; height: 100%; object-fit: cover;" autoplay muted loop></video>
                        <div id="preview-game-title" class="pixel-text" style="position: absolute; bottom: 20px; left: 20px; font-size: 1rem; text-shadow: 2px 2px #000; color: var(--secondary);"></div>
                    </div>
                </div>
                <button id="close-preview-btn" class="btn-neon" style="position: absolute; -top: 15px; -right: 15px; border-radius: 50%; width: 40px; height: 40px; padding: 0;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>

    <!-- Inclusion des scripts -->
    <script>const SITE_URL = "<?= SITE_URL ?>";</script>
    <script src="<?= SITE_URL ?>/public/js/video-preview.js"></script>
    <script src="<?= SITE_URL ?>/public/js/script.js"></script>
    <script>
        // --- Gestion Menu déroulant profil ---
        const menuButton = document.getElementById('menu-button');
        const profileMenu = document.getElementById('profile-menu');

        if (menuButton && profileMenu) {
            menuButton.addEventListener('click', (event) => {
                 event.stopPropagation();
                const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
                profileMenu.classList.toggle('hidden');
                if (profileMenu.classList.contains('hidden')) {
                    profileMenu.classList.remove('animate__animated', 'animate__fadeIn'); 
                } else {
                    profileMenu.classList.add('animate__animated', 'animate__fadeIn'); 
                    profileMenu.classList.remove('animate__fadeOut'); 
                }
                menuButton.setAttribute('aria-expanded', !isExpanded);
            });

            document.addEventListener('click', (event) => {
                if (!profileMenu.classList.contains('hidden') && !profileMenu.contains(event.target) && !menuButton.contains(event.target)) {
                     profileMenu.classList.remove('animate__fadeIn');
                     profileMenu.classList.add('animate__fadeOut', 'animate__faster');
                     profileMenu.addEventListener('animationend', () => {
                          profileMenu.classList.add('hidden');
                          profileMenu.classList.remove('animate__animated', 'animate__fadeOut', 'animate__faster');
                     }, { once: true });
                    menuButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
    </script>
</body>
</html>
