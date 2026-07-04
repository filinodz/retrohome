<?php
// templates/game.php (Modern Version)
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <?php if ($isRTL): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif !important; }</style>
    <?php endif; ?>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS -->
    <!-- Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <!-- CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    
    <style>
        .game-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        @media (max-width: 768px) {
            .game-header { grid-template-columns: 1fr; }
        }
        .game-cover-art {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            border: 1px solid var(--glass-border);
        }
        .rating-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
        }
        .video-container {
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            aspect-ratio: 16/9;
            background: black;
        }
    </style>
    <link rel="stylesheet" href="<?= get_theme_asset('css/') ?>footer.css">
</head>
<body class="bg-background text-text-primary font-body theme-modern">

    <div id="game-data-container" class="hidden"
         data-game-id="<?= json_encode($game_id) ?>"
         data-is-logged-in="<?= json_encode($is_logged_in) ?>"
         data-user-rating="<?= json_encode($user_rating ?? 0) ?>"
         data-average-rating="<?= json_encode((float)$average_rating) ?>"
         data-is-favorite="<?= json_encode($is_favorite) ?>">
    </div>

    <div class="app-container">
        <header class="glass nav-bar animate__animated animate__fadeInDown">
            <div class="flex items-center gap-4">
                <a href="<?= SITE_URL ?>/" class="action-link edit" style="width: auto; padding: 0 15px; border-radius: 50px;">
                    <i class="fas fa-arrow-left mr-2"></i> <?= __('back_caps') ?>
                </a>
                <h1 class="pixel-text" style="margin: 0; color: var(--primary); font-size: 1rem;"><?= $game_title ?></h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden md:block">
                     <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                </div>
                <?php if ($is_logged_in): ?>
                <button class="favorite-toggle-button <?= $is_favorite ? 'favorited' : '' ?>" data-game-id="<?= $game_id ?>" style="font-size: 1.5rem; color: <?= $is_favorite ? 'var(--primary)' : 'white' ?>;">
                    <i class="fa<?= $is_favorite ? 's' : 'r' ?> fa-heart"></i>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <div class="game-header animate__animated animate__fadeInUp">
            <div class="glass" style="padding: 10px; border-radius: 30px;">
                <img src="<?= $game_cover ?>" alt="<?= $game_title ?>" class="game-cover-art">
            </div>
            
            <div class="flex flex-col justify-center">
                <div class="flex items-center gap-4 mb-6">
                    <?php if ($console_logo): ?>
                        <img src="<?= $console_logo ?>" alt="Console" style="height: 40px; filter: drop-shadow(0 0 5px var(--secondary));">
                    <?php endif; ?>
                    <span class="pixel-text" style="color: var(--secondary);"><?= $console_name ?></span>
                </div>

                <div class="flex flex-wrap gap-6 mb-8 opacity-60 pixel-text" style="font-size: 0.7rem;">
                    <span><i class="fas fa-calendar mr-2"></i><?= $game_year ?></span>
                    <?php if ($game_publisher): ?>
                        <span><i class="fas fa-building mr-2"></i><?= $game_publisher ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-star mr-2 text-primary"></i><?= $average_rating ?>/5</span>
                </div>

                <button class="btn-neon" onclick="startGame('<?= $console_slug ?>', '<?= $rom_path ?>', '<?= addslashes($game_title) ?>')" style="width: fit-content; padding: 15px 40px; font-size: 1.2rem;">
                    <i class="fas fa-play mr-2"></i><?= __('play_game_caps') ?>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-12 mb-12 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="lg:col-span-2">
                <div class="glass" style="padding: 30px; margin-bottom: 30px;">
                    <h2 class="pixel-text mb-6" style="color: var(--primary); font-size: 1rem;"><?= __('description_caps') ?></h2>
                    <div style="line-height: 1.8; opacity: 0.8; font-size: 1.1rem;">
                        <?= $game_description ?>
                    </div>
                </div>

                <?php if ($game_preview): ?>
                <div class="glass" style="padding: 10px;">
                    <div class="video-container">
                        <video controls preload="metadata" style="width: 100%; height: 100%;">
                            <source src="<?= $game_preview ?>" type="video/mp4">
                        </video>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="glass rating-box">
                    <h2 class="pixel-text mb-6" style="color: var(--secondary); font-size: 0.8rem;"><?= __('eval_system_caps') ?></h2>
                    
                    <div class="mb-8">
                        <div style="font-size: 3rem; font-weight: 800; color: white;"><?= $average_rating ?><span style="font-size: 1rem; opacity: 0.4;">/5</span></div>
                        <div class="pixel-text" style="font-size: 0.6rem; opacity: 0.5;"><?= $rating_count ?> <?= __('votes_received_caps') ?></div>
                    </div>

                    <div style="height: 1px; background: var(--glass-border); margin: 20px 0;"></div>

                    <?php if ($is_logged_in): ?>
                        <div class="pixel-text mb-4" style="font-size: 0.6rem;"><?= __('your_rating_caps') ?></div>
                        <div class="rating user-rating-stars mb-4" data-game-id="<?= $game_id ?>" style="font-size: 2rem; display: flex; justify-content: center; gap: 5px;">
                             <!-- Stars will be injected by JS -->
                        </div>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/login" class="pixel-text" style="font-size: 0.6rem; color: var(--primary);"><?= __('login_to_rate_caps') ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="game-container" style="display: none;"></div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const SITE_URL = "<?= SITE_URL ?>";
        window.SITE_URL = SITE_URL;
        window.RETROHOME_NETPLAY_URL = "<?= htmlspecialchars($settings->get('netplay_url', ''), ENT_QUOTES) ?>";
    </script>
    <script src="<?= SITE_URL ?>/public/vendor/socketio/socket.io.min.js"></script>
    <script src="<?= SITE_URL ?>/public/js/emulator.js"></script>
    <script src="<?= SITE_URL ?>/public/js/game-page.js"></script>
</body>
</html>
