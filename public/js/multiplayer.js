/*
 * RetroHome — Lobby Multiplayer
 * Affiche les parties NetPlay en cours et permet de les rejoindre.
 * Fait le lien entre les rooms du serveur netplay (identifiées par un hash du
 * titre) et les jeux de la bibliothèque.
 */
(function () {
  'use strict';

  var SITE = (typeof window.SITE_URL === 'string' && window.SITE_URL) ? window.SITE_URL : '';

  function netplayBaseUrl() {
    if (typeof window.RETROHOME_NETPLAY_URL === 'string' && window.RETROHOME_NETPLAY_URL) {
      return window.RETROHOME_NETPLAY_URL.replace(/\/+$/, '');
    }
    return window.location.protocol + '//' + window.location.hostname + ':3000';
  }

  // Même algorithme que emulator.js / script.js (EJS_gameID)
  function computeGameId(gameName) {
    var clean = (gameName || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    var hash = 0;
    for (var i = 0; i < clean.length; i++) hash = (Math.imul(31, hash) + clean.charCodeAt(i)) | 0;
    return Math.abs(hash);
  }

  function assetUrl(path) {
    if (!path) return SITE + '/public/img/default_cover.png';
    if (path.indexOf('http') === 0) return path;
    return SITE + (path.charAt(0) === '/' ? '' : '/') + path;
  }

  var gamesById = {};   // hash -> game
  var gamesLoaded = false;
  var statusEl, gridEl;

  // Construit une URL d'API sur la MÊME origine que la page courante, même si
  // SITE_URL pointe sur un autre host (ex. IP LAN alors qu'on accède via localhost).
  // Évite les erreurs "Failed to fetch" (cross-origin).
  function apiUrl(action) {
    try {
      var u = new URL(SITE || window.location.href, window.location.href);
      if (u.origin !== window.location.origin) {
        return window.location.origin + u.pathname.replace(/\/+$/, '') + '/api?action=' + action;
      }
    } catch (e) {}
    return (SITE || '') + '/api?action=' + action;
  }

  function loadGames() {
    return fetch(apiUrl('getGames'))
      .then(function (r) { return r.json(); })
      .then(function (games) {
        gamesById = {};
        (games || []).forEach(function (g) { gamesById[computeGameId(g.title)] = g; });
        gamesLoaded = true;
      })
      .catch(function () { gamesLoaded = false; });
  }

  function fetchRooms() {
    var url = netplayBaseUrl() + '/list';
    return fetch(url, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (obj) {
        var rooms = [];
        for (var sid in obj) { if (obj.hasOwnProperty(sid)) { var r = obj[sid]; r.sessionid = sid; rooms.push(r); } }
        return { ok: true, rooms: rooms };
      })
      .catch(function () { return { ok: false, rooms: [] }; });
  }

  function render(state) {
    if (!gridEl) return;
    if (!state.ok) {
      gridEl.innerHTML = '';
      statusEl.innerHTML = '<i class="fas fa-plug"></i> Serveur NetPlay hors-ligne. ' +
        'Lancez <code>START_NETPLAY.bat</code> sur le PC hôte, puis actualisez.';
      statusEl.className = 'mp-status offline';
      return;
    }
    var rooms = state.rooms;
    if (!rooms.length) {
      gridEl.innerHTML = '';
      statusEl.innerHTML = '<i class="fas fa-satellite-dish"></i> Aucune partie en cours. ' +
        'Lancez un jeu multijoueur et créez une room pour commencer !';
      statusEl.className = 'mp-status empty';
      return;
    }
    statusEl.innerHTML = '<i class="fas fa-circle" style="color:#34d399;font-size:.6em;"></i> ' +
      rooms.length + ' partie(s) en cours';
    statusEl.className = 'mp-status live';

    gridEl.innerHTML = '';
    rooms.forEach(function (room) {
      var game = gamesById[room.game_id];
      var card = document.createElement('div');
      card.className = 'mp-card glass';

      var cover = game ? assetUrl(game.cover) : (SITE + '/public/img/default_cover.png');
      var title = game ? game.title : (room.room_name || 'Jeu inconnu');
      var consoleName = game ? (game.console_name || '') : '';
      var full = room.current >= room.max;

      card.innerHTML =
        '<div class="mp-card-cover"><img src="' + cover + '" alt="" loading="lazy">' +
          '<span class="mp-live-dot"><i class="fas fa-circle"></i> LIVE</span></div>' +
        '<div class="mp-card-body">' +
          '<div class="mp-room-name"><i class="fas fa-door-open"></i> ' + escapeHtml(room.room_name || 'Room') + '</div>' +
          '<div class="mp-game-title">' + escapeHtml(title) + '</div>' +
          '<div class="mp-console">' + escapeHtml(consoleName) + '</div>' +
          '<div class="mp-meta"><span class="mp-players"><i class="fas fa-users"></i> ' +
            room.current + '/' + room.max + '</span></div>' +
        '</div>';

      var btn = document.createElement('button');
      btn.className = 'mp-join-btn';
      if (full) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-lock"></i> Complète';
      } else if (!game) {
        btn.disabled = true;
        btn.title = "Ce jeu n'est pas dans votre bibliothèque";
        btn.innerHTML = '<i class="fas fa-question"></i> Jeu introuvable';
      } else {
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Rejoindre';
        btn.onclick = function () { joinRoom(game, room); };
      }
      card.querySelector('.mp-card-body').appendChild(btn);
      gridEl.appendChild(card);
    });
  }

  function joinRoom(game, room) {
    if (!window.RetroHome || typeof window.RetroHome.joinRoom !== 'function') {
      alert('Module émulateur non chargé.'); return;
    }
    var romPath = game.rom_path && game.rom_path.charAt(0) === '/' ? SITE + game.rom_path : game.rom_path;
    window.RetroHome.joinRoom(game.console_slug, romPath, game.title, room.sessionid, room.room_name);
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function refresh() {
    fetchRooms().then(function (state) {
      if (!gamesLoaded && state.ok && state.rooms.length) {
        loadGames().then(function () { render(state); });
      } else {
        render(state);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    statusEl = document.getElementById('mp-status');
    gridEl = document.getElementById('mp-grid');
    if (!gridEl) return;
    loadGames().finally(function () { refresh(); });
    setInterval(refresh, 5000); // auto-actualisation
    var rb = document.getElementById('mp-refresh');
    if (rb) rb.onclick = function () { refresh(); };
  });
})();
