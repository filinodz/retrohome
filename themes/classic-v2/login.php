<?php
// themes/classic-v2/login.php
require_once 'config.php';

// Fetch 6 random games
try {
    $stmt = $db->query("
        SELECT g.*, c.name as console_name, c.logo as console_logo, c.slug as console_slug
        FROM games g
        JOIN consoles c ON g.console_id = c.id
        WHERE g.preview IS NOT NULL AND g.preview != ''
        ORDER BY RAND()
        LIMIT 6
    ");
    $randomGames = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $randomGames = [];
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= SITE_NAME ?> - Login</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <link rel="stylesheet" href="<?= get_theme_asset('css/login.css') ?>">
</head>
<body class="bg-background theme-classic min-h-screen overflow-x-hidden">
    <div class="container mx-auto py-12 px-4 relative">
        <div class="absolute top-0 right-0 p-4">
             <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
        </div>
        <div class="flex flex-col lg:flex-row items-center justify-center gap-12">
            <!-- Login Section -->
            <div class="w-full max-w-md">
                <div class="login-box bg-surface p-8 rounded-lg border border-border-color shadow-2xl">
                    <div class="text-center mb-8">
                        <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="mx-auto w-24 mb-4">
                        <h1 class="text-2xl font-bold text-primary"><?= __('login') ?></h1>
                        <p class="text-secondary text-sm"><?= __('auth_subtitle') ?></p>
                    </div>

                    <form id="auth-form" class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-2"><?= __('username') ?></label>
                            <input type="text" name="username" required class="w-full bg-background border border-border-color p-3 rounded focus:border-primary outline-none text-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-2"><?= __('password') ?></label>
                            <input type="password" name="password" required class="w-full bg-background border border-border-color p-3 rounded focus:border-primary outline-none text-primary">
                        </div>
                        <div id="email-group" style="display: none;">
                            <label class="block text-xs font-bold uppercase mb-2"><?= __('email') ?></label>
                            <input type="email" name="email" class="w-full bg-background border border-border-color p-3 rounded focus:border-primary outline-none text-primary">
                        </div>

                        <button type="submit" id="auth-submit-button" class="w-full bg-primary text-black font-bold py-3 rounded hover:bg-opacity-90 transition-all">
                            <?= __('login_btn') ?>
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <button id="auth-toggle" class="text-secondary hover:text-primary text-sm underline decoration-dotted">
                            <?= __('create_account') ?>
                        </button>
                    </div>
                    <div id="auth-message" class="mt-4 text-center text-sm font-semibold"></div>
                </div>
            </div>

        <!-- Discovery Section -->
        <div class="flex-1 w-full max-w-4xl">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i class="fas fa-play-circle"></i> APERÇUS VIDÉO
                </h2>
                <a href="/" class="text-secondary hover:text-primary text-sm transition-colors">Explorer tout <i class="fas fa-arrow-right ml-1"></i></a>
            </div>

            <div class="login-tv-container group">
                <div class="tv-body">
                    <div class="tv-screen-container">
                        <div id="loading" class="absolute inset-0 flex items-center justify-center z-[5] bg-black bg-opacity-50" style="display: none;">
                             <div class="w-10 h-10 border-4 border-t-primary border-transparent rounded-full animate-spin"></div>
                        </div>
                        <video id="login-preview-video" muted playsinline></video>
                        <div class="tv-screen-effects"></div>
                        <div class="tv-glass-reflection"></div>
                        <div class="loading-bar" id="preview-progress"></div>
                        
                        <div class="tv-game-info">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 id="preview-title" class="text-xl font-bold text-white mb-1"></h3>
                                    <p id="preview-console" class="text-sm text-secondary"></p>
                                </div>
                                <img id="preview-console-logo" src="" alt="Console" class="h-6 object-contain">
                            </div>
                        </div>
                    </div>
                    <div class="tv-controls">
                        <button id="prevGame" class="tv-button" aria-label="<?= __('prev_game') ?>"><i class="fas fa-backward-step"></i></button>
                        <button id="playPause" class="tv-button active" aria-label="<?= __('play_pause') ?>"><i class="fas fa-pause"></i></button>
                        <button id="nextGame" class="tv-button" aria-label="<?= __('next_game') ?>"><i class="fas fa-forward-step"></i></button>
                        <button id="muteToggle" class="tv-button" aria-label="<?= __('mute') ?>"><i class="fas fa-volume-xmark"></i></button>
                    </div>
                </div>
                <div class="tv-stand"></div>
            </div>
        </div>
    </div>
</div>

    <script>
        // Discovery Video Logic
        const games = <?= json_encode($randomGames) ?>;
        const video = document.getElementById('login-preview-video');
        const title = document.getElementById('preview-title');
        const consoleText = document.getElementById('preview-console');
        const consoleLogo = document.getElementById('preview-console-logo');
        const progress = document.getElementById('preview-progress');
        const loading = document.getElementById('loading');
        
        const prevBtn = document.getElementById('prevGame');
        const nextBtn = document.getElementById('nextGame');
        const playPauseBtn = document.getElementById('playPause');
        const muteBtn = document.getElementById('muteToggle');

        let currentGameIndex = 0;
        const SITE_URL = '<?= SITE_URL ?>';

        function getAssetUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            const cleanPath = path.startsWith('/') ? path.substring(1) : path;
            return SITE_URL + '/' + cleanPath;
        }

        function loadGame(index) {
            const game = games[index];
            if (!game) return;

            loading.style.display = 'flex';
            video.classList.remove('playing');
            
            video.src = getAssetUrl(game.preview);
            title.textContent = game.title;
            consoleText.textContent = game.console_name;
            consoleLogo.src = getAssetUrl(game.console_logo);
            
            video.load();
        }

        video.addEventListener('canplay', () => {
            loading.style.display = 'none';
            video.classList.add('playing');
            video.play().catch(e => console.log("Auto-play blocked:", e));
        });

        video.addEventListener('timeupdate', () => {
            if (video.duration) {
                const percentage = (video.currentTime / video.duration) * 100;
                progress.style.width = percentage + '%';
            }
        });

        video.addEventListener('ended', () => {
            currentGameIndex = (currentGameIndex + 1) % games.length;
            loadGame(currentGameIndex);
        });

        nextBtn.addEventListener('click', () => {
            currentGameIndex = (currentGameIndex + 1) % games.length;
            loadGame(currentGameIndex);
        });

        prevBtn.addEventListener('click', () => {
            currentGameIndex = (currentGameIndex - 1 + games.length) % games.length;
            loadGame(currentGameIndex);
        });

        playPauseBtn.addEventListener('click', () => {
            if (video.paused) {
                video.play();
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                playPauseBtn.classList.add('active');
            } else {
                video.pause();
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                playPauseBtn.classList.remove('active');
            }
        });

        muteBtn.addEventListener('click', () => {
            video.muted = !video.muted;
            muteBtn.innerHTML = video.muted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-high"></i>';
            muteBtn.classList.toggle('active', video.muted);
        });

        if (games.length > 0) {
            loadGame(0);
        }

        if (games.length > 0) {
            loadGame(0);
        }

        // Shared login logic (simplified for classic template)
        const authForm = document.getElementById('auth-form');
        const authSubmitButton = document.getElementById('auth-submit-button');
        const authToggle = document.getElementById('auth-toggle');
        const emailGroup = document.getElementById('email-group');
        const authMessage = document.getElementById('auth-message');
        
        let isRegisterMode = false;

        authToggle.addEventListener('click', () => {
            isRegisterMode = !isRegisterMode;
            if (isRegisterMode) {
                emailGroup.style.display = 'block';
                authSubmitButton.textContent = '<?= __('register_btn') ?>';
                authToggle.textContent = '<?= __('already_registered') ?>';
            } else {
                emailGroup.style.display = 'none';
                authSubmitButton.textContent = '<?= __('login_btn') ?>';
                authToggle.textContent = '<?= __('create_account') ?>';
            }
        });

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const action = isRegisterMode ? 'register' : 'login';
            authSubmitButton.disabled = true;
            authSubmitButton.textContent = '<?= __('wait') ?>';
            
            try {
                const formData = new FormData(authForm);
                const response = await fetch(`${SITE_URL}/api?action=${action}`, { method: 'POST', body: formData });
                const result = await response.json();
                
                if (response.ok) {
                    authMessage.textContent = "<?= __('success_access') ?>";
                    authMessage.style.color = "var(--primary)";
                    location.href = SITE_URL + '/';
                } else {
                    authMessage.textContent = result.error || '<?= __('error') ?>';
                    authMessage.style.color = "red";
                }
            } catch (err) {
                authMessage.textContent = "<?= __('server_error') ?>";
                authMessage.style.color = "red";
            } finally {
                authSubmitButton.disabled = false;
                authSubmitButton.textContent = isRegisterMode ? '<?= __('register_btn') ?>' : '<?= __('login_btn') ?>';
            }
        });
    </script>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
