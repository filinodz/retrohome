<?php // themes/aurora/multiplayer.php — Lobby des parties NetPlay ?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">

    <style>
        .mp-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 60px; }
        .mp-head { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin: 24px 0 8px; }
        .mp-title { font-family:'Outfit',sans-serif; font-weight:800; font-size:1.8rem; display:flex; align-items:center; gap:12px; }
        .mp-title i { color: var(--secondary); }
        .mp-sub { color: var(--text-secondary); font-size:.9rem; margin-bottom: 18px; }
        .mp-status { display:flex; align-items:center; gap:10px; padding:14px 18px; border-radius:14px;
            background: var(--glass-bg); border:1px solid var(--glass-border); font-size:.9rem; margin-bottom: 22px; }
        .mp-status code { background:rgba(255,255,255,.1); padding:2px 7px; border-radius:6px; }
        .mp-status.live { border-color: rgba(52,211,153,.4); }
        .mp-status.offline { border-color: rgba(239,68,68,.4); color:#fca5a5; }
        .mp-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(230px,1fr)); gap:20px; }
        .mp-card { border-radius:18px; overflow:hidden; display:flex; flex-direction:column; transition: transform .25s, box-shadow .25s; }
        .mp-card:hover { transform: translateY(-6px); box-shadow: var(--box-shadow-hover); }
        .mp-card-cover { position:relative; aspect-ratio:3/4; background:#0a0c16; overflow:hidden; }
        .mp-card-cover img { width:100%; height:100%; object-fit:cover; }
        .mp-live-dot { position:absolute; top:10px; left:10px; background:rgba(5,18,26,.85); color:#34d399;
            font-size:.6rem; font-weight:800; padding:4px 9px; border-radius:999px; letter-spacing:1px; }
        .mp-live-dot i { font-size:.7em; animation: mpPulse 1.4s infinite; }
        @keyframes mpPulse { 0%,100%{opacity:1;} 50%{opacity:.3;} }
        .mp-card-body { padding:14px; display:flex; flex-direction:column; gap:6px; flex:1; }
        .mp-room-name { font-size:.72rem; color: var(--secondary); font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .mp-game-title { font-family:'Outfit',sans-serif; font-weight:700; color:#fff; line-height:1.2; }
        .mp-console { font-size:.75rem; color: var(--text-secondary); }
        .mp-meta { margin-top:2px; }
        .mp-players { font-size:.8rem; color: var(--text-secondary); }
        .mp-join-btn { margin-top:12px; width:100%; border:none; border-radius:10px; padding:11px; font-weight:800;
            cursor:pointer; color:#05121a; background: linear-gradient(120deg,#22d3ee,#34d399);
            display:inline-flex; align-items:center; justify-content:center; gap:8px; transition: transform .15s, filter .15s; }
        .mp-join-btn:hover:not(:disabled) { transform: translateY(-2px); filter:brightness(1.06); }
        .mp-join-btn:disabled { opacity:.5; cursor:not-allowed; background:#334155; color:#cbd5e1; }
        .mp-topbtn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:12px;
            border:1px solid var(--glass-border); background:var(--glass-bg); color:var(--text-primary); cursor:pointer; font-weight:700; text-decoration:none; }
        .mp-topbtn:hover { border-color: var(--primary-light); }
    </style>
</head>
<body class="bg-background text-text-primary">

    <div class="mp-wrap">
        <div class="mp-head">
            <a href="<?= SITE_URL ?>/" class="mp-topbtn"><i class="fas fa-arrow-left"></i> Retour</a>
            <button id="mp-refresh" class="mp-topbtn"><i class="fas fa-rotate"></i> Actualiser</button>
        </div>

        <h1 class="mp-title"><i class="fas fa-users"></i> Multiplayer — Parties en ligne</h1>
        <p class="mp-sub">Rejoignez une partie NetPlay en cours sur votre réseau local. Ouvrez le même jeu et affrontez vos amis !</p>

        <div id="mp-status" class="mp-status"><i class="fas fa-spinner fa-spin"></i> Recherche des parties…</div>
        <div id="mp-grid" class="mp-grid"></div>
    </div>

    <div id="game-container" style="display:none;"></div>

    <script>
        window.SITE_URL = "<?= SITE_URL ?>";
        window.RETROHOME_NETPLAY_URL = "<?= htmlspecialchars($settings->get('netplay_url', ''), ENT_QUOTES) ?>";
        window.RETROHOME_USER = "<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES) ?>";
    </script>
    <script src="<?= SITE_URL ?>/public/vendor/socketio/socket.io.min.js"></script>
    <script src="<?= SITE_URL ?>/public/js/netplay-fix.js?v=<?= @filemtime(BASE_PATH . '/public/js/netplay-fix.js') ?>"></script>
    <script src="<?= SITE_URL ?>/public/js/emulator.js?v=<?= @filemtime(BASE_PATH . '/public/js/emulator.js') ?>"></script>
    <script src="<?= SITE_URL ?>/public/js/multiplayer.js?v=<?= @filemtime(BASE_PATH . '/public/js/multiplayer.js') ?>"></script>
</body>
</html>
