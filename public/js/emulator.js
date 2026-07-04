/*
 * RetroHome — Module émulateur partagé (EmulatorJS) + NETPLAY LAN
 * -------------------------------------------------------------------
 * Source unique de vérité pour lancer/fermer un jeu et gérer le netplay.
 * Corrige notamment le bug "le son continue après avoir quitté le jeu"
 * grâce à un registre global d'AudioContext (voir killAllAudio()).
 *
 * API publique :
 *   RetroHome.startGame(core, romUrl, gameName)
 *   RetroHome.closeGame()
 *   RetroHome.openNetplay()   // ouvre la modale netplay
 *
 * Alias globaux (pour les onclick inline des templates) :
 *   startGame(...), closeGame()
 */
(function () {
  'use strict';

  // =====================================================================
  // 0. CONFIGURATION
  // =====================================================================
  var SITE = (typeof window.SITE_URL === 'string' && window.SITE_URL) ? window.SITE_URL : '';
  var DATA_PATH = SITE + '/data/';

  // URL du serveur netplay : injectable par PHP via window.RETROHOME_NETPLAY_URL,
  // sinon on déduit http(s)://<host>:3000 (même machine que le site par défaut).
  function netplayBaseUrl() {
    if (typeof window.RETROHOME_NETPLAY_URL === 'string' && window.RETROHOME_NETPLAY_URL) {
      return window.RETROHOME_NETPLAY_URL.replace(/\/+$/, '');
    }
    return window.location.protocol + '//' + window.location.hostname + ':3000';
  }

  // État courant
  var state = {
    game: null,          // { core, romUrl, gameName }
    roomId: null,
    isHost: false,
    netplayRetry: 0
  };

  // Room passée dans l'URL (relance host/join)
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('room')) state.roomId = urlParams.get('room');
  if (urlParams.has('host')) state.isHost = true;

  // =====================================================================
  // 1. REGISTRE AUDIO — le correctif clé du bug "son qui continue"
  // On intercepte la création de tout AudioContext pour pouvoir les
  // fermer intégralement à la fermeture du jeu, où qu'ils soient stockés.
  // =====================================================================
  window.__RH_AUDIO_CTXS = window.__RH_AUDIO_CTXS || [];
  var AUDIO_CTXS = window.__RH_AUDIO_CTXS;

  ['AudioContext', 'webkitAudioContext'].forEach(function (name) {
    var Orig = window[name];
    if (!Orig || Orig.__rhPatched) return;
    var Patched = function () {
      var inst = Reflect.construct(Orig, Array.prototype.slice.call(arguments), Orig);
      try { AUDIO_CTXS.push(inst); } catch (e) {}
      return inst;
    };
    Patched.prototype = Orig.prototype;
    Patched.__rhPatched = true;
    try { window[name] = Patched; } catch (e) {}
  });

  function killAllAudio() {
    // a) Fermer tous les AudioContext traqués
    for (var i = 0; i < AUDIO_CTXS.length; i++) {
      var ctx = AUDIO_CTXS[i];
      try {
        if (ctx && ctx.state !== 'closed') {
          if (ctx.suspend) ctx.suspend();
          if (ctx.close) ctx.close();
        }
      } catch (e) {}
    }
    AUDIO_CTXS.length = 0;

    // b) Filet de sécurité : couper les éléments <audio>/<video> de l'émulateur
    try {
      document.querySelectorAll('#game audio, #game video, #game-container audio, #game-container video')
        .forEach(function (el) { try { el.pause(); el.muted = true; el.src = ''; el.load && el.load(); } catch (e) {} });
    } catch (e) {}
  }

  // =====================================================================
  // 2. OVERLAY DE JEU
  // =====================================================================
  function ensureGameContainer() {
    var c = document.getElementById('game-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'game-container';
      document.body.appendChild(c);
    }
    return c;
  }

  function buildOverlay(gameName) {
    var container = ensureGameContainer();
    container.innerHTML = '';
    container.style.cssText =
      'display:flex;flex-direction:column;position:fixed;inset:0;z-index:20000;background:#000;';

    // Barre de contrôle
    var bar = document.createElement('div');
    bar.style.cssText =
      'flex-shrink:0;display:flex;align-items:center;gap:10px;padding:8px 12px;' +
      'background:#101018;border-bottom:1px solid #262636;';

    var title = document.createElement('div');
    title.textContent = gameName || 'RetroHome';
    title.style.cssText =
      'flex:1;color:#fff;font-family:Cairo,system-ui,sans-serif;font-weight:700;' +
      'font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';

    var npBtn = document.createElement('button');
    npBtn.innerHTML = '<i class="fas fa-network-wired"></i> NETPLAY';
    npBtn.style.cssText = ctrlBtnCss('#2563eb');
    npBtn.onclick = function (e) { e.preventDefault(); openNetplay(); };

    var helpBtn = document.createElement('button');
    helpBtn.innerHTML = '<i class="fas fa-circle-question"></i>';
    helpBtn.title = 'Aide netplay';
    helpBtn.style.cssText = ctrlBtnCss('#4b5563');
    helpBtn.onclick = function (e) { e.preventDefault(); openNetplayHelp(); };

    var quitBtn = document.createElement('button');
    quitBtn.innerHTML = '<i class="fas fa-times"></i> QUITTER';
    quitBtn.style.cssText = ctrlBtnCss('#dc2626');
    quitBtn.onclick = function (e) { e.preventDefault(); closeGame(); };

    bar.appendChild(title);
    bar.appendChild(npBtn);
    bar.appendChild(helpBtn);
    bar.appendChild(quitBtn);
    container.appendChild(bar);

    var gameDiv = document.createElement('div');
    gameDiv.id = 'game';
    gameDiv.style.cssText = 'flex:1;width:100%;position:relative;overflow:hidden;';
    container.appendChild(gameDiv);

    document.body.style.overflow = 'hidden';
    return container;
  }

  function ctrlBtnCss(bg) {
    return 'background:' + bg + ';color:#fff;border:none;border-radius:8px;padding:8px 14px;' +
      'font-family:Cairo,system-ui,sans-serif;font-weight:700;font-size:0.8rem;cursor:pointer;' +
      'display:inline-flex;align-items:center;gap:6px;transition:filter .15s;';
  }

  // =====================================================================
  // 3. LANCEMENT DU JEU
  // =====================================================================
  function computeGameId(gameName) {
    var clean = (gameName || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    var hash = 0;
    for (var i = 0; i < clean.length; i++) hash = (Math.imul(31, hash) + clean.charCodeAt(i)) | 0;
    return Math.abs(hash);
  }

  function startGame(core, romUrl, gameName) {
    if (!romUrl) { console.error('[RetroHome] romUrl manquant'); return; }
    state.game = { core: core, romUrl: romUrl, gameName: gameName };

    buildOverlay(gameName);

    // --- Configuration EmulatorJS ---
    window.EJS_player = '#game';
    window.EJS_gameName = gameName;
    window.EJS_gameUrl = romUrl;
    window.EJS_core = core;
    window.EJS_pathtodata = DATA_PATH;
    window.EJS_startOnLoaded = true;
    window.EJS_gameID = computeGameId(gameName);

    // --- Netplay (activé seulement si une room est demandée) ---
    if (state.roomId) {
      window.EJS_EXPERIMENTAL_NETPLAY = true;
      window.EJS_netplayServer = netplayBaseUrl() + '/';
      window.EJS_netplayUrl = netplayBaseUrl() + '/';
      window.EJS_netplayICEServers = [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
      ];
      window.EJS_onGameStart = function () {
        if (state.roomId) { state.netplayRetry = 0; setTimeout(connectToNetplay, 2000); }
      };
    }

    // Charger le moteur
    var script = document.createElement('script');
    script.id = 'ejs-loader-script';
    script.src = DATA_PATH + 'loader.js';
    script.async = true;
    document.body.appendChild(script);
  }

  // =====================================================================
  // 4. FERMETURE DU JEU (teardown garanti)
  // =====================================================================
  function closeGame() {
    // a) Couper le socket netplay
    try {
      if (window.EJS_emulator && window.EJS_emulator.netplay && window.EJS_emulator.netplay.socket) {
        window.EJS_emulator.netplay.socket.disconnect();
      }
    } catch (e) {}

    // b) Détruire l'émulateur
    try {
      if (window.EJS_emulator) {
        if (typeof window.EJS_emulator.pause === 'function') { try { window.EJS_emulator.pause(); } catch (e) {} }
        if (typeof window.EJS_emulator.destroy === 'function') window.EJS_emulator.destroy();
        else if (typeof window.EJS_emulator.exit === 'function') window.EJS_emulator.exit();
      }
    } catch (e) { console.warn('[RetroHome] fermeture émulateur:', e); }

    // c) COUPER LE SON (correctif du bug rapporté)
    killAllAudio();

    // d) Nettoyer les globals EmulatorJS pour permettre un relancement propre
    var g = window;
    Object.keys(g).forEach(function (k) {
      if (k.indexOf('EJS_') === 0) { try { g[k] = undefined; } catch (e) {} }
    });
    window.EJS_emulator = null;
    window.netplayRetryCount = 0;

    // e) Retirer le script loader injecté
    var loader = document.getElementById('ejs-loader-script');
    if (loader && loader.parentNode) loader.parentNode.removeChild(loader);

    // f) Nettoyer le DOM
    var container = document.getElementById('game-container');
    if (container) { container.style.display = 'none'; container.innerHTML = ''; }
    document.body.style.overflow = '';

    // g) Nettoyer l'URL (retirer ?jeu / ?room / ?host)
    if (urlParams.has('jeu') || urlParams.has('room') || urlParams.has('host')) {
      try { history.pushState(null, '', window.location.pathname); } catch (e) {}
    }
  }

  // =====================================================================
  // 5. NETPLAY — modale, connexion, redémarrage en place
  // =====================================================================
  function loadSocketIO(cb) {
    if (typeof window.io !== 'undefined') { cb(); return; }
    var s = document.createElement('script');
    s.src = SITE + '/public/vendor/socketio/socket.io.min.js';
    s.onload = cb;
    s.onerror = function () {
      // Filet de secours : CDN (nécessite Internet)
      var c = document.createElement('script');
      c.src = 'https://cdn.socket.io/4.7.5/socket.io.min.js';
      c.onload = cb;
      c.onerror = function () { setStatus('❌ Socket.IO introuvable', 'error'); };
      document.body.appendChild(c);
    };
    document.body.appendChild(s);
  }

  // Redémarre le jeu courant avec le netplay activé (sans recharger la page)
  function restartWithNetplay(roomId, isHost) {
    var game = state.game;
    if (!game) { alert('Lancez d’abord un jeu avant d’activer le NetPlay.'); return; }
    state.roomId = roomId;
    state.isHost = !!isHost;
    closeGameKeepState();
    // petit délai pour laisser le teardown se faire
    setTimeout(function () { startGame(game.core, game.romUrl, game.gameName); }, 150);
  }

  // Comme closeGame mais on conserve state.game/roomId (pour relancer)
  function closeGameKeepState() {
    var savedGame = state.game, savedRoom = state.roomId, savedHost = state.isHost;
    closeGame();
    state.game = savedGame; state.roomId = savedRoom; state.isHost = savedHost;
  }

  function connectToNetplay() {
    if (!window.EJS_emulator || !window.EJS_emulator.netplay) {
      state.netplayRetry++;
      if (state.netplayRetry < 25) { setTimeout(connectToNetplay, 500); return; }
      setStatus('❌ Module netplay indisponible', 'error');
      return;
    }
    var netplay = window.EJS_emulator.netplay;

    // Neutraliser une éventuelle connexion automatique
    if (netplay.socket && netplay.socket.connected) {
      try { netplay.socket.disconnect(); } catch (e) {}
      netplay.socket = null;
    }

    var pseudo = localStorage.getItem('netplay_nickname') || ('Player_' + Math.floor(Math.random() * 1000));
    netplay.name = pseudo;
    netplay.playerID = pseudo + '_' + Date.now().toString(36);

    setStatus('Connexion à la room ' + state.roomId + '…', 'info');

    netplay.socket = window.io(netplayBaseUrl(), {
      reconnection: true,
      transports: ['websocket', 'polling']
    });

    netplay.socket.on('connect_error', function () {
      setStatus('❌ Serveur netplay injoignable (' + netplayBaseUrl() + ')', 'error');
    });

    netplay.socket.on('connect', function () {
      ['data-message', 'offer', 'answer', 'candidate', 'users-updated'].forEach(function (evt) { netplay.socket.off(evt); });
      netplay.socket.on('offer', function (d) { if (netplay.onOffer) netplay.onOffer(d); });
      netplay.socket.on('answer', function (d) { if (netplay.onAnswer) netplay.onAnswer(d); });
      netplay.socket.on('candidate', function (d) { if (netplay.onCandidate) netplay.onCandidate(d); });
      netplay.socket.on('data-message', function (d) { if (netplay.onDataMessage) netplay.onDataMessage(d); });
      netplay.socket.on('users-updated', function (users) {
        netplay.players = users;
        var count = Object.keys(users).length;
        if (count > 1) setStatus('✅ Joueurs : ' + count + '. Synchronisation…', 'success');
        else setStatus('En attente d’un adversaire…', 'warning');
      });

      var extra = {
        domain: window.location.hostname,
        game_id: window.EJS_gameID,
        room_name: 'Room_' + state.roomId,
        player_name: netplay.name,
        userid: netplay.playerID,
        sessionid: state.roomId
      };
      netplay.players = {}; netplay.players[netplay.playerID] = extra;

      netplay.socket.emit('join-room', { extra: extra }, function (err, currentUsers) {
        if (err) { setStatus('❌ Erreur join', 'error'); return; }
        netplay.players = currentUsers;
        try { netplay.roomJoined(state.isHost, 'Lan', '', state.roomId); }
        catch (e) { console.error('roomJoined:', e); }
      });
    });
  }

  // ---- Modale netplay (auto-injectée si absente) ----
  function ensureModal() {
    if (document.getElementById('rh-netplay-modal')) return;
    injectStyles();
    var wrap = document.createElement('div');
    wrap.id = 'rh-netplay-modal';
    wrap.className = 'rh-np-modal';
    wrap.innerHTML =
      '<div class="rh-np-card">' +
        '<div class="rh-np-head">' +
          '<h3><i class="fas fa-globe"></i> NetPlay LAN</h3>' +
          '<button class="rh-np-close" data-rh="close"><i class="fas fa-times"></i></button>' +
        '</div>' +
        '<div class="rh-np-body">' +
          '<label class="rh-np-label">Pseudo</label>' +
          '<input id="rh-np-nick" class="rh-np-input" maxlength="15" placeholder="Votre pseudo (ex: Player1)">' +
          '<div class="rh-np-tabs">' +
            '<button class="rh-np-tab active" data-rh="tab-host"><i class="fas fa-server"></i> Héberger</button>' +
            '<button class="rh-np-tab" data-rh="tab-join"><i class="fas fa-gamepad"></i> Rejoindre</button>' +
          '</div>' +
          '<div id="rh-np-host" class="rh-np-pane active">' +
            '<p class="rh-np-hint">Créez une room et partagez le code à votre ami.</p>' +
            '<button class="rh-np-btn primary" data-rh="create"><i class="fas fa-plus-circle"></i> Créer la room</button>' +
            '<div id="rh-np-code" class="rh-np-code" style="display:none">' +
              '<span class="rh-np-code-label">Code à partager</span>' +
              '<span id="rh-np-code-val" class="rh-np-code-val">---</span>' +
              '<button class="rh-np-btn primary" data-rh="launch-host" style="margin-top:12px"><i class="fas fa-play"></i> Lancer la partie</button>' +
            '</div>' +
          '</div>' +
          '<div id="rh-np-join" class="rh-np-pane">' +
            '<p class="rh-np-hint">Entrez le code reçu de l’hôte (même jeu requis).</p>' +
            '<input id="rh-np-join-code" class="rh-np-input rh-np-codeinput" maxlength="6" placeholder="CODE">' +
            '<button class="rh-np-btn secondary" data-rh="join"><i class="fas fa-sign-in-alt"></i> Rejoindre</button>' +
          '</div>' +
          '<div id="rh-np-status" class="rh-np-status"></div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(wrap);

    // Délégation d'événements
    wrap.addEventListener('click', function (e) {
      if (e.target === wrap) { closeNetplay(); return; }
      var t = e.target.closest('[data-rh]');
      if (!t) return;
      var act = t.getAttribute('data-rh');
      if (act === 'close') closeNetplay();
      else if (act === 'tab-host') switchTab('host');
      else if (act === 'tab-join') switchTab('join');
      else if (act === 'create') createRoom();
      else if (act === 'launch-host') launchHost();
      else if (act === 'join') joinRoom();
    });
  }

  function openNetplay() {
    ensureModal();
    var modal = document.getElementById('rh-netplay-modal');
    modal.classList.add('open');
    var nick = document.getElementById('rh-np-nick');
    var saved = localStorage.getItem('netplay_nickname');
    if (saved && nick) nick.value = saved;
    // charger socket.io en avance
    loadSocketIO(function () {});
  }

  function closeNetplay() {
    var modal = document.getElementById('rh-netplay-modal');
    if (modal) modal.classList.remove('open');
  }

  function switchTab(tab) {
    document.querySelectorAll('.rh-np-tab').forEach(function (t) { t.classList.remove('active'); });
    document.querySelectorAll('.rh-np-pane').forEach(function (p) { p.classList.remove('active'); });
    if (tab === 'host') {
      document.querySelector('[data-rh="tab-host"]').classList.add('active');
      document.getElementById('rh-np-host').classList.add('active');
    } else {
      document.querySelector('[data-rh="tab-join"]').classList.add('active');
      document.getElementById('rh-np-join').classList.add('active');
    }
    setStatus('');
  }

  function saveNick() {
    var nick = document.getElementById('rh-np-nick');
    if (nick && nick.value.trim()) localStorage.setItem('netplay_nickname', nick.value.trim());
  }

  function createRoom() {
    saveNick();
    var roomId = Math.random().toString(36).substring(2, 8).toUpperCase();
    state.roomId = roomId;
    document.getElementById('rh-np-code-val').textContent = roomId;
    document.getElementById('rh-np-code').style.display = 'block';
    setStatus('✅ Code généré : ' + roomId, 'success');
  }

  function launchHost() {
    if (!state.roomId) { setStatus('Créez d’abord une room', 'warning'); return; }
    closeNetplay();
    restartWithNetplay(state.roomId, true);
  }

  function joinRoom() {
    saveNick();
    var input = document.getElementById('rh-np-join-code');
    var code = input ? input.value.trim().toUpperCase() : '';
    if (!code) { setStatus('Entrez un code', 'warning'); return; }
    closeNetplay();
    restartWithNetplay(code, false);
  }

  function setStatus(msg, type) {
    var el = document.getElementById('rh-np-status');
    if (!el) return;
    el.textContent = msg || '';
    el.className = 'rh-np-status' + (type ? ' ' + type : '');
  }

  // ---- Modale d'aide ----
  function openNetplayHelp() {
    ensureModal();
    var help = document.getElementById('rh-netplay-help');
    if (!help) {
      help = document.createElement('div');
      help.id = 'rh-netplay-help';
      help.className = 'rh-np-modal';
      help.innerHTML =
        '<div class="rh-np-card">' +
          '<div class="rh-np-head"><h3><i class="fas fa-circle-question"></i> Comment jouer en réseau ?</h3>' +
          '<button class="rh-np-close" data-rh-help="close"><i class="fas fa-times"></i></button></div>' +
          '<div class="rh-np-body rh-np-help">' +
            '<ol>' +
              '<li>Sur le PC hôte, lancez le serveur NetPlay (<code>START_NETPLAY.bat</code>).</li>' +
              '<li>Les deux joueurs doivent être sur le <b>même réseau Wi-Fi / LAN</b>.</li>' +
              '<li>L’hôte ouvre un jeu → <b>NETPLAY</b> → <b>Héberger</b> → <b>Créer la room</b>, puis partage le code.</li>' +
              '<li>L’ami ouvre <b>le même jeu</b> → <b>NETPLAY</b> → <b>Rejoindre</b>, saisit le code.</li>' +
            '</ol>' +
            '<p class="rh-np-hint">Astuce : si le second PC n’est pas l’hôte, il doit viser l’IP du serveur. ' +
            'L’administrateur peut définir l’URL du serveur netplay dans les réglages.</p>' +
            '<div class="rh-np-compat"><b>Systèmes recommandés</b> (cœurs déterministes) :<br>' +
            'NES, SNES, Game Boy / GBC, GBA, Sega Genesis / Master System, Arcade (FBNeo/MAME 2003).<br>' +
            '<span class="rh-np-warn">Tous les cœurs ne supportent pas le netplay ; privilégiez des jeux 2 joueurs.</span></div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(help);
      help.addEventListener('click', function (e) {
        if (e.target === help || (e.target.closest('[data-rh-help]'))) help.classList.remove('open');
      });
    }
    help.classList.add('open');
  }

  // =====================================================================
  // 6. STYLES (injectés une fois)
  // =====================================================================
  function injectStyles() {
    if (document.getElementById('rh-np-styles')) return;
    var css =
      '.rh-np-modal{position:fixed;inset:0;z-index:21000;display:none;align-items:center;justify-content:center;' +
      'background:rgba(5,7,15,.8);backdrop-filter:blur(6px);padding:16px;}' +
      '.rh-np-modal.open{display:flex;}' +
      '.rh-np-card{width:100%;max-width:440px;background:#12131c;border:1px solid rgba(255,255,255,.1);' +
      'border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;font-family:Cairo,system-ui,sans-serif;color:#fff;}' +
      '.rh-np-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;' +
      'background:linear-gradient(135deg,rgba(37,99,235,.25),rgba(139,92,246,.15));border-bottom:1px solid rgba(255,255,255,.08);}' +
      '.rh-np-head h3{margin:0;font-size:1.05rem;display:flex;align-items:center;gap:8px;}' +
      '.rh-np-close{background:transparent;border:none;color:#fff;font-size:1.1rem;cursor:pointer;opacity:.7;}' +
      '.rh-np-close:hover{opacity:1;}' +
      '.rh-np-body{padding:20px;}' +
      '.rh-np-label{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.12em;color:#8ab4ff;margin-bottom:6px;}' +
      '.rh-np-input{width:100%;box-sizing:border-box;background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.12);' +
      'border-radius:10px;padding:11px 14px;color:#fff;outline:none;transition:border .15s;}' +
      '.rh-np-input:focus{border-color:#3b82f6;}' +
      '.rh-np-codeinput{text-align:center;font-family:monospace;text-transform:uppercase;letter-spacing:.3em;font-size:1.2rem;}' +
      '.rh-np-tabs{display:flex;gap:8px;margin:18px 0 14px;}' +
      '.rh-np-tab{flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:10px;' +
      'padding:10px;color:#cbd5e1;font-weight:700;cursor:pointer;transition:all .15s;}' +
      '.rh-np-tab.active{background:#2563eb;border-color:#2563eb;color:#fff;}' +
      '.rh-np-pane{display:none;}' +
      '.rh-np-pane.active{display:block;}' +
      '.rh-np-hint{font-size:.85rem;color:rgba(255,255,255,.6);text-align:center;margin:0 0 14px;}' +
      '.rh-np-btn{width:100%;border:none;border-radius:10px;padding:12px;font-weight:800;font-size:.95rem;cursor:pointer;' +
      'display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:filter .15s,transform .15s;color:#fff;}' +
      '.rh-np-btn:hover{filter:brightness(1.1);transform:translateY(-1px);}' +
      '.rh-np-btn.primary{background:#2563eb;}.rh-np-btn.secondary{background:#8b5cf6;}' +
      '.rh-np-code{margin-top:16px;text-align:center;background:rgba(37,99,235,.12);border:1px solid rgba(37,99,235,.4);' +
      'border-radius:12px;padding:16px;}' +
      '.rh-np-code-label{display:block;font-size:.7rem;text-transform:uppercase;color:#8ab4ff;margin-bottom:8px;}' +
      '.rh-np-code-val{font-family:monospace;font-size:2rem;font-weight:800;letter-spacing:.25em;user-select:all;}' +
      '.rh-np-status{margin-top:14px;text-align:center;font-weight:700;font-size:.85rem;min-height:18px;}' +
      '.rh-np-status.success{color:#22c55e;}.rh-np-status.error{color:#ef4444;}' +
      '.rh-np-status.warning{color:#f59e0b;}.rh-np-status.info{color:#38bdf8;}' +
      '.rh-np-help ol{padding-left:20px;line-height:1.9;font-size:.9rem;}' +
      '.rh-np-help code{background:rgba(255,255,255,.1);padding:2px 6px;border-radius:5px;font-size:.85em;}' +
      '.rh-np-compat{margin-top:16px;background:rgba(255,255,255,.04);border-radius:10px;padding:14px;font-size:.85rem;line-height:1.6;}' +
      '.rh-np-warn{color:#f59e0b;}';
    var el = document.createElement('style');
    el.id = 'rh-np-styles';
    el.textContent = css;
    document.head.appendChild(el);
  }

  // =====================================================================
  // 7. EXPORTS + gestion du bouton retour navigateur
  // =====================================================================
  window.RetroHome = {
    startGame: startGame,
    closeGame: closeGame,
    openNetplay: openNetplay,
    openNetplayHelp: openNetplayHelp
  };
  // Alias globaux pour les onclick inline des templates existants
  window.startGame = startGame;
  window.closeGame = closeGame;

  window.addEventListener('popstate', function () {
    var container = document.getElementById('game-container');
    var open = container && container.style.display !== 'none' && container.innerHTML !== '';
    if (open && !new URLSearchParams(window.location.search).has('jeu')) closeGame();
  });
})();
