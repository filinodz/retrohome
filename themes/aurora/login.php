<?php
// themes/aurora/login.php — Connexion, design Aurora moderne
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
    <title><?= SITE_NAME ?> - <?= __('login_title') ?? 'Connexion' ?></title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_theme_asset('css/login.css') ?>">
</head>
<body class="aurora-login">

    <div class="login-lang">
        <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
    </div>

    <div class="login-shell">

        <!-- ═══════════ VITRINE VIDÉO ═══════════ -->
        <section class="showcase glass">
            <div class="showcase-screen">
                <video id="gamePreview" muted playsinline></video>
                <div class="showcase-scanlines"></div>

                <div id="loading" class="showcase-loading">
                    <div class="loading-spinner"></div>
                </div>

                <div class="showcase-live">
                    <span class="tv-led"></span> LIVE
                </div>

                <div class="game-info">
                    <div class="game-info-content">
                        <img id="consoleLogo" src="" alt="Console">
                        <div class="game-info-text">
                            <h3 id="gameTitle"></h3>
                            <p id="gameDescription"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="showcase-controls">
                <button class="sc-btn" id="prevGame" title="<?= __('prev_game') ?>"><i class="fas fa-backward-step"></i></button>
                <button class="sc-btn" id="playPause" title="<?= __('play_pause') ?>"><i class="fas fa-pause"></i></button>
                <button class="sc-btn" id="nextGame" title="<?= __('next_game') ?>"><i class="fas fa-forward-step"></i></button>
                <button class="sc-btn" id="muteToggle" title="<?= __('mute') ?>"><i class="fas fa-volume-high"></i></button>
                <span class="sc-tag">RetroHome</span>
            </div>
        </section>

        <!-- ═══════════ FORMULAIRE ═══════════ -->
        <section class="auth glass">
            <div class="auth-content">
                <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="<?= SITE_NAME ?>" id="logo-image">
                <h1 class="auth-brand"><?= SITE_NAME ?></h1>
                <p class="auth-subtitle"><?= __('auth_subtitle') ?></p>

                <form id="auth-form" class="auth-form">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" placeholder=" " required autocomplete="username">
                        <label for="username"><?= __('username') ?></label>
                    </div>

                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                        <label for="password"><?= __('password') ?></label>
                    </div>

                    <div id="email-group" class="input-group" style="display: none;">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email" placeholder=" " autocomplete="email">
                        <label for="email"><?= __('email') ?></label>
                    </div>

                    <button type="submit" id="auth-submit-button">
                        <span><?= __('login_btn') ?></span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <button id="auth-toggle"><?= __('create_account') ?></button>
                <div id="auth-message"></div>

                <p class="auth-foot">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?> — <?= __('made_with_love') ?? 'Made with' ?>
                    <i class="fas fa-heart" style="color:#f472b6;"></i> <b>FilinoDZ</b>
                </p>
            </div>
        </section>
    </div>

    <script>
        const games = <?= json_encode($randomGames) ?>;
        const video = document.getElementById('gamePreview');
        const title = document.getElementById('gameTitle');
        const description = document.getElementById('gameDescription');
        const consoleLogo = document.getElementById('consoleLogo');
        const loading = document.getElementById('loading');
        const tvLed = document.querySelector('.tv-led');
        const prevBtn = document.getElementById('prevGame');
        const nextBtn = document.getElementById('nextGame');
        const playPauseBtn = document.getElementById('playPause');
        const muteBtn = document.getElementById('muteToggle');

        let currentIndex = 0;
        const SITE_URL = '<?= SITE_URL ?>';

        function getAssetUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            const cleanPath = path.startsWith('/') ? path.substring(1) : path;
            return SITE_URL + '/' + cleanPath;
        }

        function loadVideo(index) {
            const game = games[index];
            if (!game) return;
            if (loading) loading.style.display = 'flex';
            video.style.opacity = '0';
            if (tvLed) tvLed.classList.remove('on');
            video.src = getAssetUrl(game.preview);
            title.textContent = game.title;
            description.textContent = game.description || "<?= __('rediscover_golden_age') ?? 'Redécouvrez l\'époque dorée du jeu vidéo.' ?>";
            consoleLogo.src = getAssetUrl(game.console_logo);
            video.load();
        }

        if (video) {
            video.addEventListener('canplay', () => {
                if (loading) loading.style.display = 'none';
                video.style.opacity = '1';
                if (tvLed) tvLed.classList.add('on');
                video.play().catch(e => console.log("Autoplay blocked"));
            });
            video.onended = () => { currentIndex = (currentIndex + 1) % games.length; loadVideo(currentIndex); };
        }

        if (nextBtn) nextBtn.onclick = () => { currentIndex = (currentIndex + 1) % games.length; loadVideo(currentIndex); };
        if (prevBtn) prevBtn.onclick = () => { currentIndex = (currentIndex - 1 + games.length) % games.length; loadVideo(currentIndex); };
        if (playPauseBtn) playPauseBtn.onclick = () => {
            if (video.paused) { video.play(); playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>'; }
            else { video.pause(); playPauseBtn.innerHTML = '<i class="fas fa-play"></i>'; }
        };
        if (muteBtn) muteBtn.onclick = () => {
            video.muted = !video.muted;
            muteBtn.innerHTML = video.muted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-high"></i>';
        };
        if (games.length > 0) loadVideo(0);

        // ═══════════ AUTH ═══════════
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
                authSubmitButton.querySelector('span').textContent = '<?= __('register_btn') ?>';
                authToggle.textContent = '<?= __('already_registered') ?>';
            } else {
                emailGroup.style.display = 'none';
                authSubmitButton.querySelector('span').textContent = '<?= __('login_btn') ?>';
                authToggle.textContent = '<?= __('create_account') ?>';
            }
        });

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const action = isRegisterMode ? 'register' : 'login';
            authSubmitButton.disabled = true;
            const originalText = authSubmitButton.querySelector('span').textContent;
            authSubmitButton.querySelector('span').textContent = '<?= __('wait') ?? 'Patientez...' ?>';
            try {
                const formData = new FormData(authForm);
                const response = await fetch(`${SITE_URL}/api?action=${action}`, { method: 'POST', body: new URLSearchParams(formData) });
                const result = await response.json();
                if (response.ok) {
                    authMessage.textContent = "<?= __('success_access') ?? 'Connexion réussie !' ?>";
                    authMessage.className = 'success';
                    setTimeout(() => location.href = SITE_URL + '/', 500);
                } else {
                    authMessage.textContent = result.error || '<?= __('invalid_credentials') ?? 'Identifiants invalides' ?>';
                    authMessage.className = 'error';
                }
            } catch (err) {
                authMessage.textContent = "<?= __('link_error') ?? 'Erreur de connexion' ?>";
                authMessage.className = 'error';
            } finally {
                authSubmitButton.disabled = false;
                authSubmitButton.querySelector('span').textContent = originalText;
            }
        });
    </script>
</body>
</html>
