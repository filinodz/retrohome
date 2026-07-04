<?php
// themes/cyberpunk/game.php
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <?php if ($isRTL): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif !important; }</style>
    <?php endif; ?>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> // Cyber_Database</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('css/') ?>footer.css">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
</head>
<body class="theme-cyberpunk p-4 md:p-8">
    <div id="game-data-container" class="hidden"
         data-game-id="<?= json_encode($game_id) ?>"
         data-is-logged-in="<?= json_encode($is_logged_in) ?>"
         data-user-rating="<?= json_encode($user_rating ?? 0) ?>"
         data-average-rating="<?= json_encode((float)$average_rating) ?>"
         data-is-favorite="<?= json_encode($is_favorite) ?>">
    </div>

    <div class="app-container">
        <header class="glass flex justify-between items-center p-6 mb-10">
            <div class="flex items-center gap-6">
                <div>
                     <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                </div>
                <a href="<?= SITE_URL ?>/" class="btn-neon text-xs"><i class="fas fa-arrow-left mr-2"></i><?= __('cp_db_exit') ?></a>
                <h1 class="text-xl font-bold text-cyan-400"><?= strtoupper($game_title) ?></h1>
            </div>
            <?php if ($is_logged_in): ?>
            <button class="favorite-toggle-button p-4 rounded-full border border-pink-500/30 hover:bg-pink-500/10 transition-all <?= $is_favorite ? 'text-pink-500 shadow-[0_0_15px_rgba(255,0,85,0.4)]' : 'text-gray-600' ?>" data-game-id="<?= $game_id ?>">
                <i class="fa<?= $is_favorite ? 's' : 'r' ?> fa-heart text-2xl"></i>
            </button>
            <?php endif; ?>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10 mb-12">
            <div class="lg:col-span-1">
                <div class="glass p-2">
                    <img src="<?= $game_cover ?>" alt="<?= $game_title ?>" class="w-full filter saturate-150 contrast-125">
                </div>
            </div>
            
            <div class="lg:col-span-3 flex flex-col justify-center">
                <div class="flex items-center gap-6 mb-8">
                    <div class="px-6 py-2 border-l-4 border-cyan-500 bg-cyan-500/10">
                        <span class="text-xs text-cyan-400 font-bold tracking-widest uppercase"><?= $console_name ?></span>
                    </div>
                    <?php if ($game_year): ?>
                    <div class="px-6 py-2 border-l-4 border-pink-500 bg-pink-500/10">
                        <span class="text-xs text-pink-400 font-bold tracking-widest uppercase">YEAR: <?= $game_year ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-10">
                    <div class="text-sm text-gray-400 mb-2 uppercase font-bold tracking-tighter"><?= __('description_caps') ?></div>
                    <div class="text-lg leading-relaxed text-blue-100/80 max-w-3xl">
                        <?= $game_description ?>
                    </div>
                </div>

                <button class="btn-neon play-button text-2xl py-6 px-12 inline-flex items-center justify-center gap-4" onclick="startGame('<?= $console_slug ?>', '<?= $rom_path ?>', '<?= addslashes($game_title) ?>')">
                    <i class="fas fa-bolt"></i>
                    <?= __('play_game_caps') ?>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-20">
            <?php if ($game_preview): ?>
            <div class="lg:col-span-2 glass p-4">
                <div class="aspect-video bg-black rounded overflow-hidden">
                    <video controls preload="metadata" class="w-full h-full object-cover opacity-80">
                        <source src="<?= $game_preview ?>" type="video/mp4">
                    </video>
                </div>
            </div>
            <?php endif; ?>

            <div class="lg:col-span-1 glass p-8 flex flex-col items-center justify-center text-center">
                <h2 class="text-xs font-bold text-yellow-400 tracking-[0.3em] mb-8 uppercase italic border-b border-yellow-400/30 pb-2"><?= __('eval_system_caps') ?></h2>
                
                <div class="mb-10 p-6 border-2 border-yellow-400/20 bg-yellow-400/5 rotate-1">
                    <div class="text-5xl font-bold text-white mb-2"><?= $average_rating ?><span class="text-xl text-yellow-400/40">/5.0</span></div>
                    <div class="text-[10px] text-yellow-400/60 font-mono tracking-widest"><?= $rating_count ?> <?= __('votes_received_caps') ?></div>
                </div>

                <?php if ($is_logged_in): ?>
                    <div class="text-xs font-bold text-cyan-400 mb-6 tracking-widest uppercase"><?= __('your_rating_caps') ?></div>
                    <div class="rating user-rating-stars flex gap-3 text-3xl" data-game-id="<?= $game_id ?>">
                        <!-- JS injected stars -->
                    </div>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login" class="btn-neon text-[10px] border-yellow-400 text-yellow-400"><?= __('login_to_rate_caps') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="game-container"></div>

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
