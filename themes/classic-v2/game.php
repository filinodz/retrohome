<?php
// themes/classic-v2/game.php
// Modern Design & Multi-language Support
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
    
    <!-- Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <link rel="stylesheet" href="<?= get_theme_asset('css/game.css') ?>">
    
    <style>
        /* Custom Modern Styles */
        body {
            background-color: #0f172a; /* Slate 900 */
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
        }
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 60vh;
            background-size: cover;
            background-position: center;
            mask-image: linear-gradient(to bottom, black, transparent);
            -webkit-mask-image: linear-gradient(to bottom, black, transparent);
            opacity: 0.4;
            z-index: -1;
        }
        .btn-play {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            transition: all 0.3s ease;
        }
        .btn-play:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(168, 85, 247, 0.5);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col relative overflow-x-hidden">

    <!-- Background Art -->
    <div class="hero-bg" style="background-image: url('<?= $game_cover ?>');"></div>

    <div class="container mx-auto px-6 py-8 flex-grow">
        <!-- Header / Nav -->
        <header class="flex justify-between items-center mb-12">
            <a href="<?= SITE_URL ?>/" class="flex items-center gap-2 text-gray-300 hover:text-white transition-colors group">
                <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                <span class="font-medium tracking-wide"><?= __('back_caps') ?></span>
            </a>
            
            <div class="flex items-center gap-4">
                <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                <?php if ($is_logged_in): ?>
                    <a href="<?= SITE_URL ?>/profile" class="text-sm font-bold text-gray-300 hover:text-white transition-colors">
                        <i class="fas fa-user-circle mr-2"></i><?= __('profile') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login" class="text-sm font-bold text-white bg-white/10 px-4 py-2 rounded hover:bg-white/20 transition-colors">
                        <?= __('login') ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content Grid -->
        <main class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            
            <!-- Left Column: Art & Actions -->
            <div class="lg:col-span-4 flex flex-col gap-6 animate-fade-in-up">
                <div class="relative group">
                    <img src="<?= $game_cover ?>" alt="<?= $game_title ?>" class="w-full rounded-2xl shadow-2xl border border-white/10 transform transition-transform duration-500 group-hover:scale-[1.02]">
                    <div class="absolute inset-0 rounded-2xl shadow-[0_0_30px_rgba(99,102,241,0.3)] opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                </div>

                <div class="glass-panel p-6 flex flex-col gap-4">
                    <button onclick="startGame('<?= $console_slug ?>', '<?= $rom_path ?>', '<?= addslashes($game_title) ?>')" 
                            class="btn-play w-full py-4 rounded-xl text-white font-bold text-lg tracking-widest flex items-center justify-center gap-3">
                        <i class="fas fa-gamepad text-2xl"></i>
                        <?= __('play_game_caps') ?>
                    </button>
                    
                    <?php if ($is_logged_in): ?>
                    <div class="grid grid-cols-2 gap-3">
                         <!-- Add to favorites / rating placeholder -->
                         <button class="bg-white/5 hover:bg-white/10 text-gray-300 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                            <i class="<?= $is_favorite ? 'fas text-red-500' : 'far' ?> fa-heart"></i> <?= __('favorite_caps') ?>
                         </button>
                         <button class="bg-white/5 hover:bg-white/10 text-gray-300 py-3 rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                             <i class="fas fa-star text-yellow-500"></i> <?= $average_rating ?>
                         </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Info & Details -->
            <div class="lg:col-span-8 flex flex-col gap-8">
                
                <div>
                    <h1 class="text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400 mb-4 leading-tight">
                        <?= $game_title ?>
                    </h1>
                    
                    <div class="flex flex-wrap items-center gap-6 text-gray-400 text-sm font-medium uppercase tracking-wider">
                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/5">
                            <i class="fas fa-gamepad"></i> <?= $console_name ?>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/5">
                            <i class="fas fa-calendar"></i> <?= $game_year ?>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/5">
                            <i class="fas fa-building"></i> <?= $game_publisher ?>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-8">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-align-left text-primary"></i> <?= __('description_caps') ?>
                    </h3>
                    <div class="prose prose-invert max-w-none text-gray-300 leading-relaxed text-lg">
                        <?= $game_description ?>
                    </div>
                </div>

                <div class="glass-panel p-8">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-star text-secondary"></i> <?= __('eval_system_caps') ?>
                    </h3>
                    <div class="flex items-center gap-4 text-gray-300">
                        <span><?= __('votes_received_caps') ?>: <?= $rating_count ?></span>
                        <span><?= __('your_rating_caps') ?> <?= $user_rating ?? __('login_to_rate_caps') ?></span>
                    </div>
                </div>

                <?php if ($game_preview): ?>
                <div class="glass-panel p-1">
                    <div class="relative pt-[56.25%] bg-black rounded-xl overflow-hidden group">
                        <video controls class="absolute top-0 left-0 w-full h-full object-cover">
                            <source src="<?= $game_preview ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </main>
    </div>

    <!-- Game Overlay Container -->
    <div id="game-container" style="display:none;" class="fixed inset-0 z-[9999] bg-black flex flex-col">
        <div class="flex justify-between items-center p-4 bg-gray-900 border-b border-gray-800">
            <span class="text-white font-bold text-lg"><i class="fas fa-gamepad mr-2 text-primary"></i> <?= $game_title ?></span>
            <button onclick="location.reload()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-bold transition-colors">
                <?= __('close') ?>
            </button>
        </div>
        <div id="game" class="flex-grow w-full h-full bg-black"></div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const SITE_URL = "<?= SITE_URL ?>";
        window.SITE_URL = SITE_URL;
        window.RETROHOME_NETPLAY_URL = "<?= htmlspecialchars($settings->get('netplay_url', ''), ENT_QUOTES) ?>";
    </script>
    <script src="<?= SITE_URL ?>/public/vendor/socketio/socket.io.min.js"></script>
    <script src="<?= SITE_URL ?>/public/js/emulator.js"></script>
</body>
</html>
