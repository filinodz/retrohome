<?php
require_once 'config.php';

// Fetch 6 random games with preview videos
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
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <link rel="stylesheet" href="<?= get_theme_asset('css/login.css') ?>"/>
    <link rel="stylesheet" href="<?= get_theme_asset('css/footer.css') ?>">
</head>
<body class="bg-dark text-white theme-modern">
    <div class="container mx-auto px-4 min-h-screen flex items-center justify-center relative">
        <div class="absolute top-0 right-0 p-4">
             <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
        </div>
        <div class="flex flex-col lg:flex-row gap-8 lg:gap-16 items-center w-full max-w-6xl">
            <!-- TV Section (Gauche) -->
            <div class="tv-container w-full lg:w-1/2 animate__animated animate__fadeInLeft animate__slow">
                <div class="tv-body">
                    <div class="tv-screen-container"> 
                        <div id="loading" class="absolute inset-0 flex items-center justify-center z-[5]">
                             <div class="loading-spinner"></div>
                        </div>
                        <video id="gamePreview" loop muted autoplay playsinline></video>
                        <div class="tv-screen-effects"></div>
                        <div class="tv-glass-reflection"></div>
                        <div class="game-info">
                            <div class="game-info-content">
                                <img id="consoleLogo" src="" alt="Console Logo">
                                <div class="game-info-text">
                                    <h3 id="gameTitle"></h3>
                                     <p id="gameDescription"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tv-controls">
                        <button id="prevButton" class="tv-button" aria-label="Previous Game"><i class="fas fa-backward-step"></i></button>
                        <button id="playPauseButton" class="tv-button active" aria-label="Pause"><i class="fas fa-pause"></i></button>
                        <button id="nextButton" class="tv-button" aria-label="Next Game"><i class="fas fa-forward-step"></i></button>
                        <button id="muteButton" class="tv-button" aria-label="Mute"><i class="fas fa-volume-xmark"></i></button>
                    </div>
                </div>
                 <div class="tv-stand"></div>
            </div>

            <div class="auth-container w-full lg:w-1/2 animate__animated animate__fadeInRight animate__slow">
                <div class="auth-content">
                    <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="RetroHome Logo" id="logo-image" class="animate__animated animate__zoomIn animate__delay-100ms">
                    <p class="auth-subtitle animate__animated animate__fadeInUp animate__delay-200ms">
                        <?= __('auth_subtitle_modern') ?>
                    </p>
                    <form id="auth-form" class="space-y-4 animate__animated animate__fadeInUp animate__delay-300ms">
                        <div class="input-group">
                            <input type="text" name="username" id="username" placeholder=" " required autocomplete="username">
                            <label for="username"><?= __('username') ?></label>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                            <label for="password"><?= __('password') ?></label>
                        </div>
                       <div class="input-group" id="email-group" style="display: none;">
                            <input type="email" name="email" id="email" placeholder=" " autocomplete="email">
                            <label for="email"><?= __('email') ?></label>
                        </div>
                        <button type="submit" id="auth-submit-button">
                            <?= __('login_btn') ?> <i class="fas fa-sign-in-alt ml-2"></i>
                        </button>
                    </form>
                    <div class="mt-6 text-center animate__animated animate__fadeInUp animate__delay-400ms">
                        <button type="button" id="auth-toggle">
                            <?= __('not_registered') ?>
                        </button>
                    </div>
                    <div id="auth-message" class="mt-4 min-h-[1.5em] text-center animate__animated animate__fadeInUp animate__delay-500ms"></div>
                </div>
            </div>
        </div>
    </div>

    <footer class="login-footer animate__animated animate__fadeIn animate__delay-1s">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. <?= __('made_with_love') ?> <span class="text-primary font-bold">FilinoDZ</span></p>
            <div class="footer-bottom-links">
                <a href="<?= SITE_URL ?>/"><?= __('home') ?></a>
                <span>•</span>
                <a href="https://github.com/FilinoDZ/RetroHome" target="_blank">GitHub</a>
            </div>
        </div>
    </footer>

    <script>
        const games = <?= json_encode($randomGames) ?>;
        const video = document.getElementById('gamePreview');
        const title = document.getElementById('gameTitle');
        const description = document.getElementById('gameDescription');
        const consoleLogo = document.getElementById('consoleLogo');
        const loading = document.getElementById('loading');
        
        const nextBtn = document.getElementById('nextButton');
        const prevBtn = document.getElementById('prevButton');
        const playPauseBtn = document.getElementById('playPauseButton');
        const muteBtn = document.getElementById('muteButton');
        
        let currentIndex = 0;
        const SITE_URL = '<?= SITE_URL ?>';

        function getAssetUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            const cleanPath = path.startsWith('/') ? path.substring(1) : path;
            return SITE_URL + '/' + cleanPath;
        }

        function updateGame(index) {
            if (games.length === 0) return;
            const game = games[index];
            
            loading.style.display = 'flex';
            video.style.opacity = '0';
            
            video.src = getAssetUrl(game.preview);
            title.textContent = game.title;
            description.textContent = game.description;
            consoleLogo.src = getAssetUrl(game.console_logo);
            
            video.load();
        }

        video.addEventListener('canplay', () => {
            loading.style.display = 'none';
            video.style.opacity = '1';
            video.play().catch(e => console.log("Autoplay blocked"));
        });

        video.addEventListener('ended', () => {
            currentIndex = (currentIndex + 1) % games.length;
            updateGame(currentIndex);
        });

        nextBtn.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % games.length;
            updateGame(currentIndex);
        });

        prevBtn.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + games.length) % games.length;
            updateGame(currentIndex);
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
            if (video.muted) {
                muteBtn.classList.add('active');
            } else {
                muteBtn.classList.remove('active');
            }
        });

        if (games.length > 0) {
            updateGame(0);
        }

        // Auth Logic
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
                authSubmitButton.innerHTML = '<?= __('register_btn') ?> <i class="fas fa-user-plus ml-2"></i>';
                authToggle.textContent = '<?= __('already_registered') ?>';
            } else {
                emailGroup.style.display = 'none';
                authSubmitButton.innerHTML = '<?= __('login_btn') ?> <i class="fas fa-sign-in-alt ml-2"></i>';
                authToggle.textContent = '<?= __('not_registered') ?>';
            }
        });

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const action = isRegisterMode ? 'register' : 'login';
            authSubmitButton.disabled = true;
            authSubmitButton.textContent = '<?= __('wait') ?>';
            
            try {
                const formData = new FormData(authForm);
                const response = await fetch(`${SITE_URL}/api?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (response.ok) {
                    authMessage.textContent = "<?= __('success_access') ?>";
                    authMessage.className = "mt-4 min-h-[1.5em] text-center success";
                    setTimeout(() => location.href = SITE_URL + '/', 1500);
                } else {
                    authMessage.textContent = result.error || '<?= __('invalid_credentials') ?>';
                    authMessage.className = "mt-4 min-h-[1.5em] text-center error";
                }
            } catch (err) {
                authMessage.textContent = "<?= __('server_error') ?>";
                authMessage.className = "mt-4 min-h-[1.5em] text-center error";
            } finally {
                authSubmitButton.disabled = false;
                if (!isRegisterMode) {
                    authSubmitButton.innerHTML = 'Connexion <i class="fas fa-sign-in-alt ml-2"></i>';
                } else {
                    authSubmitButton.innerHTML = 'Créer un compte <i class="fas fa-user-plus ml-2"></i>';
                }
            }
        });

        // Floating label logic
        document.querySelectorAll('.input-group input').forEach(input => {
            input.addEventListener('blur', () => {
                if (input.value !== "") {
                    input.classList.add('label-floated');
                } else {
                    input.classList.remove('label-floated');
                }
            });
        });
    </script>
</body>
</html>
