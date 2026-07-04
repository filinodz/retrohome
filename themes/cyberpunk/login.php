<?php
// themes/cyberpunk/login.php
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
    <title><?= SITE_NAME ?> - 2077_AUTH</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('css/login.css') ?>"/>
    <link rel="stylesheet" href="<?= get_theme_asset('css/footer.css') ?>">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <style>
        :root {
            --primary: #ff0055;
            --secondary: #00fbff;
        }
        body {
            background-color: #050510;
            overflow: hidden;
        }
        .auth-container {
            background: rgba(10, 10, 30, 0.9);
            border: 1px solid var(--primary);
            box-shadow: 0 0 20px rgba(255, 0, 85, 0.2);
            clip-path: polygon(0 0, 95% 0, 100% 20px, 100% 100%, 5% 100%, 0 80%);
        }
        .modal-input, .input-group input {
            background: rgba(0, 251, 255, 0.05) !important;
            border: 1px solid rgba(0, 251, 255, 0.3) !important;
            color: var(--secondary) !important;
            font-family: 'Roboto Mono', monospace;
        }
        .input-group label {
            color: var(--secondary) !important;
            opacity: 0.6;
        }
        #auth-submit-button {
            background: var(--primary) !important;
            color: white !important;
            font-family: 'Orbitron', sans-serif;
            clip-path: polygon(0 0, 90% 0, 100% 10px, 100% 100%, 10% 100%, 0 90%);
        }
        .tv-body {
            border-color: var(--secondary) !important;
            box-shadow: 0 0 30px rgba(0, 251, 255, 0.2) !important;
        }
    </style>
</head>
<body class="text-white">
    <div class="container mx-auto px-4 min-h-screen flex items-center justify-center relative">
        <!-- Scanline Effect -->
        <div class="absolute inset-0 pointer-events-none opacity-20" style="background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%); background-size: 100% 4px;"></div>
        
        <div class="absolute top-0 right-0 p-4 z-50">
             <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 lg:gap-16 items-center w-full max-w-6xl relative z-10">
            <!-- TV Section -->
            <div class="tv-container w-full lg:w-1/2 animate__animated animate__fadeInLeft">
                <div class="tv-body">
                    <div class="tv-screen-container"> 
                        <div id="loading" class="absolute inset-0 flex items-center justify-center z-[5]">
                             <div class="loading-spinner"></div>
                        </div>
                        <video id="gamePreview" loop muted autoplay playsinline class="filter saturate-150"></video>
                        <div class="tv-screen-effects"></div>
                        <div class="game-info">
                            <div class="game-info-content">
                                <img id="consoleLogo" src="" alt="Console" class="h-8 mb-2">
                                <div class="game-info-text">
                                    <h3 id="gameTitle" class="text-lg font-bold text-cyan-400"></h3>
                                     <p id="gameDescription" class="text-xs opacity-70"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-container w-full lg:w-1/2 p-8 md:p-12 animate__animated animate__fadeInRight">
                <div class="text-center mb-10">
                    <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="h-20 mx-auto mb-6 filter drop-shadow(0 0 10px var(--primary))">
                    <h2 class="text-2xl font-bold tracking-widest text-secondary mb-2 uppercase"><?= __('cp_terminal_login') ?></h2>
                    <p class="text-xs opacity-50 font-mono"><?= __('cp_encrypted') ?></p>
                </div>

                <form id="auth-form" class="space-y-6">
                    <div class="input-group">
                        <input type="text" name="username" id="username" placeholder=" " required autocomplete="username">
                        <label for="username"><?= __('cp_user_id') ?></label>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                        <label for="password"><?= __('cp_security_creds') ?></label>
                    </div>
                    <div class="input-group" id="email-group" style="display: none;">
                        <input type="email" name="email" id="email" placeholder=" " autocomplete="email">
                        <label for="email"><?= __('cp_contact_url') ?></label>
                    </div>
                    <button type="submit" id="auth-submit-button" class="w-full py-4 text-sm font-bold tracking-widest hover:brightness-125 transition-all">
                        <?= __('cp_initiate_session') ?> <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <button type="button" id="auth-toggle" class="text-[10px] font-bold text-cyan-400 hover:text-white transition-colors tracking-tighter uppercase underline decoration-cyan-500/30">
                        <?= __('cp_create_profile') ?>
                    </button>
                </div>
                <div id="auth-message" class="mt-4 text-xs font-mono text-center min-h-[1em]"></div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const games = <?= json_encode($randomGames) ?>;
        const video = document.getElementById('gamePreview');
        const title = document.getElementById('gameTitle');
        const description = document.getElementById('gameDescription');
        const consoleLogo = document.getElementById('consoleLogo');
        const loading = document.getElementById('loading');
        
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
            description.textContent = game.description || 'System data: Encrypted game summary...';
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
                authSubmitButton.innerHTML = '<?= __('cp_register_profile') ?> <i class="fas fa-user-plus ml-2"></i>';
                authToggle.textContent = '<?= __('cp_existing_profile') ?>';
            } else {
                emailGroup.style.display = 'none';
                authSubmitButton.innerHTML = '<?= __('cp_initiate_session') ?> <i class="fas fa-sign-in-alt ml-2"></i>';
                authToggle.textContent = '<?= __('cp_create_profile') ?>';
            }
        });

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const action = isRegisterMode ? 'register' : 'login';
            authSubmitButton.disabled = true;
            authSubmitButton.textContent = '<?= __('cp_processing') ?>';
            
            try {
                const formData = new FormData(authForm);
                const response = await fetch(`${SITE_URL}/api?action=${action}`, {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                const result = await response.json();
                
                if (response.ok) {
                    authMessage.textContent = "<?= __('cp_access_granted') ?>";
                    authMessage.style.color = "var(--secondary)";
                    setTimeout(() => location.href = SITE_URL + '/', 1000);
                } else {
                    authMessage.textContent = "ERROR: " + (result.error || '<?= __('cp_unauthorized') ?>');
                    authMessage.style.color = "var(--primary)";
                }
            } catch (err) {
                authMessage.textContent = "<?= __('cp_system_fault') ?>";
                authMessage.style.color = "var(--primary)";
            } finally {
                authSubmitButton.disabled = false;
                if (!isRegisterMode) {
                    authSubmitButton.innerHTML = '<?= __('cp_initiate_session') ?> <i class="fas fa-sign-in-alt ml-2"></i>';
                } else {
                    authSubmitButton.innerHTML = '<?= __('cp_register_profile') ?> <i class="fas fa-user-plus ml-2"></i>';
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
