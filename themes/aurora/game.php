<?php
// themes/classic/game.php
// Upgraded to Modern Design with Multi-language Support
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
        /* Immersive Modern Styles for Classic Theme */
        body {
            background-color: #0b0b0f;
            color: #f0f0f5;
            font-family: 'Inter', sans-serif;
        }
        .glass-panel {
            background: rgba(22, 22, 30, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
        }
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 70vh;
            background-size: cover;
            background-position: center;
            mask-image: linear-gradient(to bottom, black, transparent);
            -webkit-mask-image: linear-gradient(to bottom, black, transparent);
            opacity: 0.3;
            z-index: -1;
        }
        .btn-play {
            background: linear-gradient(135deg, var(--primary), #ff4081);
            transition: all 0.3s ease;
        }
        .btn-play:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(255, 45, 85, 0.5);
        }
        
        /* Star Rating Styles */
        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 0.25rem;
        }
        .rating-stars input { display: none; }
        .rating-stars label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #4b5563;
            transition: color 0.2s;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #fbbf24;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col relative overflow-x-hidden theme-classic">

    <!-- Background Art Overlay -->
    <div class="hero-bg" style="background-image: url('<?= $game_cover ?>');"></div>

    <div class="container mx-auto px-6 py-8 flex-grow relative z-10">
        <!-- Header / Nav -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-12 gap-6 bg-transparent border-none shadow-none">
            <a href="<?= SITE_URL ?>/" class="flex items-center gap-2 text-gray-400 hover:text-primary transition-colors group">
                <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                <span class="font-bold tracking-widest uppercase text-sm"><?= __('back_caps') ?></span>
            </a>
            
            <div class="flex items-center gap-6">
                <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
                <?php if ($is_logged_in): ?>
                    <a href="<?= SITE_URL ?>/profile" class="text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors">
                        <i class="fas fa-user-circle mr-2"></i><?= __('profile') ?>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/login" class="text-xs font-bold uppercase tracking-widest text-primary border border-primary/30 px-6 py-2 rounded-full hover:bg-primary hover:text-black transition-all">
                        <?= __('login') ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content Grid -->
        <main class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
            
            <!-- Left Column: Art & Actions -->
            <div class="lg:col-span-4 flex flex-col gap-6 animate__animated animate__fadeInLeft">
                <div class="relative group">
                    <img src="<?= $game_cover ?>" alt="<?= $game_title ?>" class="w-full rounded-2xl shadow-2xl border border-white/10 transition-all duration-500 group-hover:scale-[1.01]">
                </div>

                <div class="glass-panel p-6 flex flex-col gap-4">
                    <button onclick="startGame('<?= $console_slug ?>', '<?= $rom_path ?>', '<?= addslashes($game_title) ?>')" 
                            class="btn-play w-full py-4 rounded-xl text-black font-black text-lg tracking-widest flex items-center justify-center gap-3 uppercase">
                        <i class="fas fa-play text-2xl"></i>
                        <?= __('play_game_caps') ?>
                    </button>
                    
                    <div class="grid grid-cols-2 gap-3">
                         <button id="favorite-btn" 
                                 data-game-id="<?= $game['id'] ?>"
                                 class="bg-white/5 hover:bg-white/10 <?= $is_favorite ? 'favorited' : '' ?> text-gray-400 py-3 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                            <i class="<?= $is_favorite ? 'fas text-primary' : 'far' ?> fa-heart"></i> <?= __('favorite_caps') ?>
                         </button>
                         <button class="bg-white/5 hover:bg-white/10 text-gray-400 py-3 rounded-lg font-bold text-xs uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                             <i class="fas fa-star text-secondary"></i> <span id="avg-rating"><?= $average_rating ?></span>
                         </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Info & Details -->
            <div class="lg:col-span-8 flex flex-col gap-8 animate__animated animate__fadeInRight">
                
                <div>
                    <h1 class="pixel-font text-4xl md:text-6xl text-primary mb-4 leading-tight drop-shadow-[0_0_15px_rgba(255,45,85,0.3)]">
                        <?= $game_title ?>
                    </h1>
                    
                    <div class="flex flex-wrap items-center gap-4 text-gray-400 text-xs font-bold uppercase tracking-widest">
                        <div class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-full border border-white/10">
                            <i class="fas fa-gamepad text-primary"></i> <?= $console_name ?>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-full border border-white/10">
                            <i class="fas fa-calendar text-secondary"></i> <?= $game_year ?>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-8">
                    <h3 class="text-sm font-bold text-primary uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                        <i class="fas fa-align-left"></i> <?= __('description_caps') ?>
                    </h3>
                    <div class="text-gray-300 leading-relaxed text-lg">
                        <?= $game_description ?>
                    </div>
                </div>

                <div class="glass-panel p-8">
                    <h3 class="text-sm font-bold text-secondary uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                        <i class="fas fa-star text-secondary"></i> <?= __('eval_system_caps') ?>
                    </h3>
                    <div class="flex flex-col md:flex-row md:items-center gap-6">
                        <div class="rating-stars" id="user-rating-stars">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($user_rating == $i) ? 'checked' : '' ?>>
                                <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <div class="flex items-center gap-4 text-gray-400 text-xs font-bold uppercase tracking-wider">
                            <span><?= __('votes_received_caps') ?>: <span id="vote-count"><?= $rating_count ?></span></span>
                            <span id="rating-status"><?= $user_rating ? __('your_rating_caps') . ' ' . $user_rating : __('login_to_rate_caps') ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($game_preview): ?>
                <div class="glass-panel p-2">
                    <div class="relative pt-[56.25%] bg-black rounded-xl overflow-hidden group">
                        <video controls class="absolute top-0 left-0 w-full h-full object-cover">
                            <source src="<?= $game_preview ?>" type="video/mp4">
                        </video>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </main>
    </div>

    <!-- Game Overlay Container -->
    <div id="game-container" style="display:none;" class="fixed inset-0 z-[20000] bg-black flex flex-col">
        <div class="flex justify-between items-center p-4 bg-black border-b border-white/10">
            <span class="pixel-font text-primary text-sm tracking-tighter"><?= $game_title ?></span>
            <button onclick="location.reload()" class="bg-primary hover:bg-white text-black px-6 py-2 rounded-lg font-black uppercase text-xs transition-colors">
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
        window.RETROHOME_USER = "<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES) ?>";
    </script>
    <script src="<?= SITE_URL ?>/public/vendor/socketio/socket.io.min.js"></script>
    <script src="<?= SITE_URL ?>/public/js/netplay-fix.js"></script>
    <script src="<?= SITE_URL ?>/public/js/emulator.js"></script>
    <script>
        const GAME_ID = <?= $game['id'] ?>;

        // --- Favorites ---
        document.getElementById('favorite-btn').addEventListener('click', async function() {
            const btn = this;
            const isFav = btn.classList.contains('favorited');
            const action = isFav ? 'removeFavorite' : 'addFavorite';
            
            btn.disabled = true;
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `game_id=${GAME_ID}`
                });
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                btn.classList.toggle('favorited');
                const icon = btn.querySelector('i');
                if (btn.classList.contains('favorited')) {
                    icon.className = 'fas text-primary fa-heart';
                } else {
                    icon.className = 'far fa-heart';
                    icon.classList.remove('text-primary');
                }
            } catch (error) {
                console.error(error);
                alert("Erreur lors de la mise à jour des favoris.");
            } finally {
                btn.disabled = false;
            }
        });

        // --- Ratings ---
        document.querySelectorAll('#user-rating-stars input').forEach(input => {
            input.addEventListener('change', async function() {
                const rating = this.value;
                const statusEl = document.getElementById('rating-status');
                
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=addRating`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `game_id=${GAME_ID}&rating=${rating}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);

                    // Update UI
                    document.getElementById('avg-rating').textContent = parseFloat(data.average_rating).toFixed(1);
                    document.getElementById('vote-count').textContent = data.rating_count;
                    statusEl.textContent = `VOTRE NOTE : ${rating}`;
                } catch (error) {
                    console.error(error);
                    alert("Erreur lors de la notation.");
                }
            });
        });
    </script>
</body>
</html>
