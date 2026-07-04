/*
 * RetroHome — Module émulateur partagé (EmulatorJS) + NETPLAY LAN
 * -------------------------------------------------------------------
 * Source unique de vérité pour lancer/fermer un jeu.
 *
 *  • Corrige le bug "le son continue après avoir quitté" (registre AudioContext).
 *  • Active le NETPLAY natif d'EmulatorJS (relais serveur, fiable en LAN) :
 *    on positionne les bons flags puis on ouvre le menu netplay intégré au moteur.
 *
 * API : RetroHome.startGame(core, romUrl, name) / RetroHome.closeGame()
 * Alias globaux : startGame(...), closeGame()
 */
(function () {
  'use strict';

  var SITE = (typeof window.SITE_URL === 'string' && window.SITE_URL) ? window.SITE_URL : '';
  var DATA_PATH = SITE + '/data/';

  function netplayBaseUrl() {
    if (typeof window.RETROHOME_NETPLAY_URL === 'string' && window.RETROHOME_NETPLAY_URL) {
      return window.RETROHOME_NETPLAY_URL.replace(/\/+$/, '');
    }
    return window.location.protocol + '//' + window.location.hostname + ':3000';
  }

  var state = { game: null };

  // =====================================================================
  // REGISTRE AUDIO — correctif du bug "son qui continue"
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
    for (var i = 0; i < AUDIO_CTXS.length; i++) {
      var ctx = AUDIO_CTXS[i];
      try { if (ctx && ctx.state !== 'closed') { if (ctx.suspend) ctx.suspend(); if (ctx.close) ctx.close(); } } catch (e) {}
    }
    AUDIO_CTXS.length = 0;
    try {
      document.querySelectorAll('#game audio, #game video, #game-container audio, #game-container video')
        .forEach(function (el) { try { el.pause(); el.muted = true; el.src = ''; el.load && el.load(); } catch (e) {} });
    } catch (e) {}
  }

  // =====================================================================
  // OVERLAY
  // =====================================================================
  function ensureContainer() {
    var c = document.getElementById('game-container');
    if (!c) { c = document.createElement('div'); c.id = 'game-container'; document.body.appendChild(c); }
    return c;
  }
  function ctrlBtnCss(bg) {
    return 'background:' + bg + ';color:#fff;border:none;border-radius:8px;padding:8px 14px;' +
      'font-family:Cairo,system-ui,sans-serif;font-weight:700;font-size:0.8rem;cursor:pointer;' +
      'display:inline-flex;align-items:center;gap:6px;transition:filter .15s;';
  }
  function buildOverlay(gameName) {
    var container = ensureContainer();
    container.innerHTML = '';
    container.style.cssText = 'display:flex;flex-direction:column;position:fixed;inset:0;z-index:20000;background:#000;';

    var bar = document.createElement('div');
    bar.style.cssText = 'flex-shrink:0;display:flex;align-items:center;gap:10px;padding:8px 12px;background:#101018;border-bottom:1px solid #262636;';

    var title = document.createElement('div');
    title.textContent = gameName || 'RetroHome';
    title.style.cssText = 'flex:1;color:#fff;font-family:Cairo,system-ui,sans-serif;font-weight:700;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';

    var npBtn = document.createElement('button');
    npBtn.innerHTML = '<i class="fas fa-network-wired"></i> NETPLAY';
    npBtn.style.cssText = ctrlBtnCss('#2563eb');
    npBtn.onclick = function (e) { e.preventDefault(); openNativeNetplay(); };

    var helpBtn = document.createElement('button');
    helpBtn.innerHTML = '<i class="fas fa-circle-question"></i>';
    helpBtn.title = 'Aide netplay';
    helpBtn.style.cssText = ctrlBtnCss('#4b5563');
    helpBtn.onclick = function (e) { e.preventDefault(); openNetplayHelp(); };

    var quitBtn = document.createElement('button');
    quitBtn.innerHTML = '<i class="fas fa-times"></i> QUITTER';
    quitBtn.style.cssText = ctrlBtnCss('#dc2626');
    quitBtn.onclick = function (e) { e.preventDefault(); closeGame(); };

    bar.appendChild(title); bar.appendChild(npBtn); bar.appendChild(helpBtn); bar.appendChild(quitBtn);
    container.appendChild(bar);

    var gameDiv = document.createElement('div');
    gameDiv.id = 'game';
    gameDiv.style.cssText = 'flex:1;width:100%;position:relative;overflow:hidden;';
    container.appendChild(gameDiv);

    document.body.style.overflow = 'hidden';
    return container;
  }

  // =====================================================================
  // LANCEMENT
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

    // Configuration EmulatorJS
    window.EJS_player = '#game';
    window.EJS_gameName = gameName;
    window.EJS_gameUrl = romUrl;
    window.EJS_core = core;
    window.EJS_pathtodata = DATA_PATH;
    window.EJS_startOnLoaded = true;

    // NETPLAY natif : ces 4 conditions sont OBLIGATOIRES pour que le bouton
    // netplay d'EmulatorJS apparaisse (voir emulator.js : netplayEnabled).
    window.EJS_DEBUG_XX = true;                 // requis par le moteur
    window.EJS_EXPERIMENTAL_NETPLAY = true;     // requis par le moteur
    window.EJS_netplayServer = netplayBaseUrl();// mappé sur config.netplayUrl
    window.EJS_gameID = computeGameId(gameName);// doit être un nombre

    var script = document.createElement('script');
    script.id = 'ejs-loader-script';
    script.src = DATA_PATH + 'loader.js';
    script.async = true;
    document.body.appendChild(script);
  }

  // Ouvre le menu netplay INTÉGRÉ au moteur (création / liste / rejoindre)
  function openNativeNetplay() {
    var em = window.EJS_emulator;
    if (em && typeof em.openNetplayMenu === 'function') {
      try { em.openNetplayMenu(); return; } catch (e) {}
    }
    openNetplayHelp(true);
  }

  // Lance un jeu ET rejoint automatiquement une room existante (depuis le lobby).
  // Best-effort : si l'auto-join échoue, le menu netplay reste ouvert avec la
  // room visible pour un "Join" manuel.
  function joinRoom(core, romUrl, name, sessionid, roomName) {
    var nick = localStorage.getItem('netplay_nickname') || ('Player' + Math.floor(1000 + Math.random() * 9000));
    startGame(core, romUrl, name);

    var tries = 0;
    var iv = setInterval(function () {
      tries++;
      var em = window.EJS_emulator;
      if (!(em && em.netplayEnabled && typeof em.openNetplayMenu === 'function')) {
        if (tries > 80) clearInterval(iv);
        return;
      }
      clearInterval(iv);
      try { em.openNetplayMenu(); } catch (e) {}

      var t2 = 0;
      var iv2 = setInterval(function () {
        t2++;
        var e2 = window.EJS_emulator;
        if (!e2) { clearInterval(iv2); return; }
        try {
          // 1) écran "Set Player Name" présent -> le remplir automatiquement
          if (!e2.netplay || !e2.netplay.name) {
            var menu = e2.netplayMenu;
            var input = menu && menu.querySelector('input[type="text"]');
            var submit = menu && menu.querySelector('.ejs_popup_submit');
            if (input && submit) { input.value = nick; submit.click(); }
            return;
          }
          // 2) pseudo OK -> rejoindre la room ciblée
          localStorage.setItem('netplay_nickname', e2.netplay.name || nick);
          if (typeof e2.netplay.joinRoom === 'function') {
            e2.netplay.joinRoom(sessionid, roomName);
          }
          clearInterval(iv2);
        } catch (err) {
          // on laisse le menu ouvert pour un join manuel
          clearInterval(iv2);
        }
        if (t2 > 40) clearInterval(iv2);
      }, 250);
    }, 400);
  }

  // =====================================================================
  // FERMETURE (teardown garanti)
  // =====================================================================
  function closeGame() {
    try {
      if (window.EJS_emulator && window.EJS_emulator.netplay && window.EJS_emulator.netplay.socket) {
        window.EJS_emulator.netplay.socket.disconnect();
      }
    } catch (e) {}
    try {
      if (window.EJS_emulator) {
        if (typeof window.EJS_emulator.pause === 'function') { try { window.EJS_emulator.pause(); } catch (e) {} }
        if (typeof window.EJS_emulator.destroy === 'function') window.EJS_emulator.destroy();
        else if (typeof window.EJS_emulator.exit === 'function') window.EJS_emulator.exit();
      }
    } catch (e) { console.warn('[RetroHome] fermeture:', e); }

    killAllAudio(); // coupe le son (fix du bug)

    var g = window;
    Object.keys(g).forEach(function (k) { if (k.indexOf('EJS_') === 0) { try { g[k] = undefined; } catch (e) {} } });
    window.EJS_emulator = null;

    var loader = document.getElementById('ejs-loader-script');
    if (loader && loader.parentNode) loader.parentNode.removeChild(loader);

    var container = document.getElementById('game-container');
    if (container) { container.style.display = 'none'; container.innerHTML = ''; }
    document.body.style.overflow = '';
  }

  // =====================================================================
  // MODALE D'AIDE
  // =====================================================================
  function injectHelpStyles() {
    if (document.getElementById('rh-np-styles')) return;
    var css =
      '.rh-np-modal{position:fixed;inset:0;z-index:21000;display:none;align-items:center;justify-content:center;background:rgba(5,7,15,.8);backdrop-filter:blur(6px);padding:16px;}' +
      '.rh-np-modal.open{display:flex;}' +
      '.rh-np-card{width:100%;max-width:460px;background:#12131c;border:1px solid rgba(255,255,255,.1);border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;font-family:Cairo,system-ui,sans-serif;color:#fff;}' +
      '.rh-np-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,rgba(37,99,235,.25),rgba(139,92,246,.15));border-bottom:1px solid rgba(255,255,255,.08);}' +
      '.rh-np-head h3{margin:0;font-size:1.05rem;display:flex;align-items:center;gap:8px;}' +
      '.rh-np-close{background:transparent;border:none;color:#fff;font-size:1.1rem;cursor:pointer;opacity:.7;}.rh-np-close:hover{opacity:1;}' +
      '.rh-np-body{padding:20px;}' +
      '.rh-np-help ol{padding-left:20px;line-height:1.9;font-size:.9rem;margin:0 0 12px;}' +
      '.rh-np-help code{background:rgba(255,255,255,.1);padding:2px 6px;border-radius:5px;font-size:.85em;}' +
      '.rh-np-compat{margin-top:6px;background:rgba(255,255,255,.04);border-radius:10px;padding:14px;font-size:.85rem;line-height:1.6;}' +
      '.rh-np-warn{color:#f59e0b;}';
    var el = document.createElement('style'); el.id = 'rh-np-styles'; el.textContent = css;
    document.head.appendChild(el);
  }

  function openNetplayHelp(fromError) {
    injectHelpStyles();
    var help = document.getElementById('rh-netplay-help');
    if (!help) {
      help = document.createElement('div');
      help.id = 'rh-netplay-help';
      help.className = 'rh-np-modal';
      help.innerHTML =
        '<div class="rh-np-card">' +
          '<div class="rh-np-head"><h3><i class="fas fa-circle-question"></i> Comment jouer en réseau ?</h3>' +
          '<button class="rh-np-close" data-close="1"><i class="fas fa-times"></i></button></div>' +
          '<div class="rh-np-body rh-np-help">' +
            '<ol>' +
              '<li>Sur le PC hôte, lancez le serveur NetPlay (<code>START_NETPLAY.bat</code>).</li>' +
              '<li>Les joueurs doivent être sur le <b>même réseau Wi-Fi / LAN</b>.</li>' +
              '<li>Dans le jeu, cliquez sur <b>NETPLAY</b> (ou l’icône réseau de la barre de l’émulateur).</li>' +
              '<li><b>Hôte</b> : « Create a room », donnez un nom. <b>Ami</b> : ouvrez <b>le même jeu</b>, votre room apparaît dans la liste → <b>Join</b>.</li>' +
            '</ol>' +
            '<div class="rh-np-compat"><b>Systèmes recommandés</b> (cœurs déterministes, 2 joueurs) :<br>' +
            'NES, SNES, Game Boy / GBC, GBA, Sega Genesis / Master System, Arcade (FBNeo / MAME 2003).<br>' +
            '<span class="rh-np-warn">Tous les cœurs ne supportent pas le netplay ; privilégiez des jeux versus.</span></div>' +
          '</div>' +
        '</div>';
      document.body.appendChild(help);
      help.addEventListener('click', function (e) {
        if (e.target === help || e.target.closest('[data-close]')) help.classList.remove('open');
      });
    }
    help.classList.add('open');
  }

  // =====================================================================
  // EXPORTS
  // =====================================================================
  window.RetroHome = { startGame: startGame, closeGame: closeGame, joinRoom: joinRoom, openNetplayHelp: openNetplayHelp };
  window.startGame = startGame;
  window.closeGame = closeGame;
})();
