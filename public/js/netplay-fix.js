/*
 * RetroHome — NetPlay v5 (lockstep déterministe) — chargé sur toutes les pages de jeu
 * ---------------------------------------------------------------------------------
 * La synchro de contrôles native d'EmulatorJS est cassée ("control syncing - broken"
 * dans le moteur), et son crochet Module.postMainLoop n'est de toute façon JAMAIS
 * appelé : Emscripten ne lit ce crochet qu'une seule fois, à l'initialisation du cœur.
 *
 * Ce module :
 *   1) intercepte la fabrique du cœur (window.EJS_Runtime) pour y installer, AVANT
 *      l'initialisation, des trampolines preMainLoop / postMainLoop ;
 *   2) implémente un lockstep : chaque joueur publie ses entrées frame par frame,
 *      elles sont appliquées sur TOUS les postes à la même frame (frame + DELAY) ;
 *      une frame ne s'exécute que lorsque les entrées de tous les pairs sont connues ;
 *   3) démarre la partie sur un état identique (sauvegarde d'état de l'hôte) et
 *      des entrées remises à zéro ;
 *   4) vérifie régulièrement une empreinte de l'état : en cas de divergence
 *      (cœur non déterministe…), l'hôte resynchronise automatiquement ;
 *   5) remplit le pseudo netplay depuis le compte connecté (window.RETROHOME_USER).
 *
 * Diagnostic : window.RH_NP_DEBUG = true avant le chargement active des logs détaillés ;
 * window.__rhNP expose l'état courant (frame, époque, empreintes).
 */
(function () {
  'use strict';

  var DELAY = 4;           // délai d'entrée en frames (~67 ms à 60 fps, confortable en LAN)
  var HASH_EVERY = 180;    // vérification de cohérence toutes les 180 frames (~3 s)
  var BLOCK = 4096;        // la cohérence est vérifiée par blocs de 4 Ko de l'état
  var NOISE = 0.02;        // part de blocs tolérée (octets de bruit sans effet de certains cœurs, ex. MAME)
  var WAIT_TOAST_MS = 4000;

  // ─────────────────────────────────────────────────────────────────────────
  // 1) Trampolines installés dans la configuration du cœur avant son init
  // ─────────────────────────────────────────────────────────────────────────
  (function trapRuntime() {
    var desc = Object.getOwnPropertyDescriptor(window, 'EJS_Runtime');
    if (desc && desc.set && desc.set.__rh) return;
    var real = (desc && 'value' in desc) ? desc.value : undefined;
    function wrap(factory) {
      if (typeof factory !== 'function' || factory.__rhWrapped) return factory;
      var wrapped = function (cfg) {
        cfg = cfg || {};
        var prevPre = cfg.preMainLoop, prevPost = cfg.postMainLoop;
        cfg.preMainLoop = function () {
          if (typeof cfg.__rhPre === 'function' && cfg.__rhPre() === false) return false;
          return prevPre ? prevPre() : undefined;
        };
        cfg.postMainLoop = function () {
          if (typeof cfg.__rhPost === 'function') cfg.__rhPost();
          if (prevPost) prevPost();
        };
        cfg.__rhHooks = true;
        return factory.apply(this, arguments);
      };
      wrapped.__rhWrapped = true;
      return wrapped;
    }
    var setter = function (v) { real = wrap(v); };
    setter.__rh = true;
    try {
      Object.defineProperty(window, 'EJS_Runtime', {
        configurable: true, enumerable: true,
        get: function () { return real; },
        set: setter
      });
      if (real) real = wrap(real);
    } catch (e) { console.warn('[RetroHome] NetPlay : interception du cœur impossible', e); }
  })();

  // ─────────────────────────────────────────────────────────────────────────
  // Pseudo
  // ─────────────────────────────────────────────────────────────────────────
  function netplayNick() {
    if (typeof window.RETROHOME_USER === 'string' && window.RETROHOME_USER.trim()) {
      return window.RETROHOME_USER.trim().slice(0, 20);
    }
    var saved = null;
    try { saved = localStorage.getItem('netplay_nickname'); } catch (e) {}
    return saved || ('Player' + Math.floor(1000 + Math.random() * 9000));
  }

  // Remplit automatiquement l'écran "Set Player Name" avec le pseudo du compte.
  function autoFillNetplayName(em) {
    if (em.netplay && em.netplay.name) return;
    var nick = netplayNick();
    var tries = 0;
    var iv = setInterval(function () {
      tries++;
      if (em.netplay && em.netplay.name) { clearInterval(iv); return; }
      var submit = em.netplayMenu && em.netplayMenu.querySelector('.ejs_popup_submit');
      var input = submit && submit.parentElement && submit.parentElement.querySelector('input[type="text"]');
      if (submit && input) {
        input.value = nick;
        try { localStorage.setItem('netplay_nickname', nick); } catch (e) {}
        submit.click();
        clearInterval(iv);
      }
      if (tries > 30) clearInterval(iv);
    }, 100);
  }

  function hashBytes(u8) {
    var h = 2166136261;
    for (var i = 0; i < u8.length; i++) { h ^= u8[i]; h = Math.imul(h, 16777619); }
    return (h >>> 0).toString(16);
  }

  // ─────────────────────────────────────────────────────────────────────────
  // 2) Lockstep
  // ─────────────────────────────────────────────────────────────────────────
  function applyNetplayPatch(em) {
    var np = em.netplay;
    if (!np || np.__rhInputPatched) return;

    var M = em.Module;
    if (!M || !M.__rhHooks || !em.gameManager) {
      // cœur pas encore prêt : ensure() réessaiera (toutes les 250 ms)
      if (M && M.calledRun && !M.__rhHooks && !np.__rhWarned) {
        np.__rhWarned = true;
        console.warn('[RetroHome] NetPlay : crochets de boucle absents (netplay-fix.js chargé après le cœur ?) — synchro impossible');
      }
      return;
    }
    np.__rhInputPatched = true;
    console.log('[RetroHome] NetPlay v5 (lockstep) actif');

    var L = null;          // null = pas de partie synchronisée
    var epochCounter = 0;  // hôte : n° de synchro
    var dbg = window.__rhNP = {
      lockstep: function () { return L; },
      coreFrame: coreFrame,
      checks: {},          // "époque:frame" -> nb de blocs différents avec le pair
      desyncs: 0,
      boots: 0,
      settle: []           // nb d'itérations nécessaires pour appliquer chaque état chargé
    };

    function log(msg) { if (window.RH_NP_DEBUG) console.log('[RetroHome NetPlay] ' + msg); }
    function toast(msg) {
      try { em.displayMessage(msg, 4000); } catch (e) {}
      console.log('[RetroHome NetPlay] ' + msg);
    }
    function coreFrame() { try { return em.gameManager.getFrameNum(); } catch (e) { return -1; } }

    function resetLockstep(epoch) {
      L = {
        epoch: epoch,
        started: false,     // la frame 1 ne part qu'au "go" commun
        frame: 0,           // frames émulées depuis la synchro
        lastCore: -1,       // compteur de frames du cœur (détection d'avancement)
        pending: [],        // entrées locales de la frame courante
        queue: {},          // queue[f] = [[port, index, value], …] appliquées avant la frame f
        peers: {},          // "p<port>" -> dernière frame publiée par ce pair
        myHash: {},         // frame -> empreintes locales par bloc
        peerHash: {},       // frame -> empreintes reçues par bloc
        waitingSince: 0,
        ready: 0,           // hôte : nb d'invités prêts
        settling: true,     // état chargé (différé par le cœur) pas encore appliqué
        settleTries: 0,
        bootHash: null,
        selfReady: false
      };
    }

    function myPort() {
      var i = np.getUserIndex(np.playerID);
      return (i < 0) ? (np.owner ? 0 : 1) : i;
    }
    function peerCount() {
      var n = 0;
      for (var k in (np.players || {})) { if (k !== np.playerID) n++; }
      return n;
    }
    function rawInput(port, index, value) {
      try { em.gameManager.functions.simulateInput(port, index, value); } catch (e) {}
    }
    function clearInputs() {
      for (var p = 0; p < 4; p++) for (var i = 0; i < 24; i++) rawInput(p, i, 0);
    }
    function getStateSafe() {
      try {
        var st = em.gameManager && em.gameManager.getState();
        if (st && st instanceof Uint8Array && st.length > 0) return st;
      } catch (e) {}
      return null;
    }
    function send(data) {
      try { np.socket.emit('data-message', data); } catch (e) {}
    }
    function minPeerFrame() {
      var m = Infinity;
      for (var k in L.peers) { if (L.peers[k] < m) m = L.peers[k]; }
      return (m === Infinity) ? -1 : m;
    }
    function knownPeers() {
      var n = 0;
      for (var k in L.peers) n++;
      return n;
    }

    // ── Avant chaque itération de la boucle : la frame suivante peut-elle partir ?
    M.__rhPre = function () {
      if (!em.isNetplay || !L) return true;
      // avant le départ : on laisse tourner la boucle tant que l'état chargé n'est pas appliqué
      if (!L.started) return L.settling;
      if (window.RH_NP_STOP_AT && L.frame >= window.RH_NP_STOP_AT) return false; // diagnostic
      var needed = L.frame + 1 - DELAY;
      if (needed > 0 && peerCount() > 0 && (knownPeers() < peerCount() || minPeerFrame() < needed)) {
        if (!L.waitingSince) L.waitingSince = Date.now();
        else if (Date.now() - L.waitingSince > WAIT_TOAST_MS && !L.waitToasted) {
          L.waitToasted = true;
          toast('NETPLAY : en attente de l\'autre joueur…');
        }
        return false;       // on saute l'itération : pas d'émulation, pas de dérive
      }
      L.waitingSince = 0; L.waitToasted = false;
      var list = L.queue[L.frame + 1];
      if (list) {
        // ordre identique sur tous les postes : tri stable par port
        list.sort(function (x, y) { return x[0] - y[0]; });
        for (var i = 0; i < list.length; i++) rawInput(list[i][0], list[i][1], list[i][2]);
        delete L.queue[L.frame + 1];
      }
      return true;
    };

    // ── Après chaque itération : si le cœur a avancé d'une frame, publier ses entrées
    M.__rhPost = function () {
      if (!em.isNetplay || !L) return;
      if (L.settling) {                        // le chargement différé est-il appliqué ?
        L.settleTries++;
        var cur = getStateSafe();
        var ok = !!cur && hashBytes(cur) === L.bootHash;
        if (ok || L.settleTries >= 10) {
          L.settling = false;
          dbg.settle.push(ok ? L.settleTries : -1);
          onSettled();
        }
        return;
      }
      if (!L.started) return;
      var cf = coreFrame();
      var d = cf - L.lastCore;
      if (d === 0) return;                     // itération sans émulation (jeu en pause…)
      L.lastCore = cf;
      if (d !== 1) {                           // frames sautées / état rechargé : plus d'alignement
        requestResync('le cœur a avancé de ' + d + ' frames');
        return;
      }
      L.frame++;
      var f = L.frame;
      if (L.pending.length) {
        var target = f + DELAY;
        L.queue[target] = (L.queue[target] || []).concat(L.pending);
      }
      send({ rh_f: f, in: L.pending, rh_p: myPort(), rh_e: L.epoch });
      L.pending = [];

      if (f % HASH_EVERY === 0 && peerCount() > 0 && !window.RH_NP_NOHASH) {
        var st = getStateSafe();
        if (st) {
          var bh = blockHashes(st);
          L.myHash[f] = bh;
          send({ rh_h: bh, rh_hf: f, rh_e: L.epoch });
          checkHash(f);
        }
      }
    };

    function blockHashes(u8) {
      var out = [];
      for (var b = 0; b < u8.length; b += BLOCK) {
        var h = 2166136261, end = Math.min(u8.length, b + BLOCK);
        for (var i = b; i < end; i++) { h ^= u8[i]; h = Math.imul(h, 16777619); }
        out.push(h >>> 0);
      }
      return out;
    }

    // Compare les empreintes par bloc : quelques blocs de "bruit" sont tolérés
    // (compteurs internes de certains cœurs, sans effet sur le jeu) ; au-delà,
    // c'est une vraie divergence -> resynchronisation par l'hôte.
    function checkHash(f) {
      if (!(f in L.myHash) || !(f in L.peerHash)) return;
      var a = L.myHash[f], b = L.peerHash[f];
      delete L.myHash[f]; delete L.peerHash[f];
      var n = Math.max(a.length, b.length), diff = 0;
      for (var i = 0; i < n; i++) if (a[i] !== b[i]) diff++;
      dbg.checks[L.epoch + ':' + f] = diff;
      if (diff <= Math.floor(n * NOISE)) return;
      dbg.desyncs++;
      requestResync('divergence détectée à la frame ' + f + ' (' + diff + '/' + n + ' blocs)');
    }

    var lastResync = 0;
    function requestResync(why) {
      log('resynchronisation : ' + why);
      if (Date.now() - lastResync < 2000) return;
      lastResync = Date.now();
      if (np.owner) { toast('NETPLAY : resynchronisation…'); bootPeers(); }
      else send({ rh_resync: true, rh_e: L && L.epoch });
    }

    function onSettled() {
      L.lastCore = coreFrame();
      if (np.owner) { L.selfReady = true; maybeGo(); }
      else send({ rh_ready: true, rh_e: L.epoch });
    }

    function maybeGo() {
      if (!L || L.started || !L.selfReady || L.ready < peerCount()) return;
      send({ rh_go: true, rh_e: L.epoch });
      startNow();
      toast('NETPLAY : partie synchronisée — bon jeu !');
    }

    // ── Entrées locales : jamais appliquées immédiatement, planifiées à frame + DELAY
    np.simulateInput = function (player, index, value) {
      if (!em.isNetplay) return;
      if (player !== 0) return;       // seul le contrôleur local (joueur 1 du poste)
      if (index >= 24) return;        // pas de raccourcis save/load pendant le netplay
      if (!L) { rawInput(myPort(), index, value); return; }
      L.pending.push([myPort(), index, value]);
    };

    // ── Synchro (hôte) : état complet + départ commun
    function bootPeers() {
      if (!np.owner || !em.isNetplay) return;
      if (peerCount() === 0) { L = null; return; }
      var st = getStateSafe();
      if (!st) { setTimeout(bootPeers, 500); return; }
      epochCounter++;
      dbg.boots++;
      resetLockstep(epochCounter);
      L.bootHash = hashBytes(st);
      // loadState est DIFFÉRÉ par le cœur (appliqué au fil des itérations suivantes) :
      // l'hôte recharge donc son propre instantané pour suivre exactement le même
      // chemin que les invités, puis chacun attend que l'état soit réellement appliqué.
      internalLoad = true;
      try { em.gameManager.loadState(st); } catch (e) {}
      internalLoad = false;
      clearInputs();
      send({ rh_boot: st, rh_delay: DELAY, rh_e: epochCounter });
      log('état envoyé (' + (st.length / 1024).toFixed(0) + ' Ko, époque ' + epochCounter + ')');
    }

    function startNow() {
      L.started = true;
      L.lastCore = coreFrame();
      try { em.play(true); } catch (e) {}
    }

    np.dataMessage = function (data) {
      if (!data) return;

      if (data.rh_f !== undefined) {                 // entrées d'un pair
        if (!L || data.rh_e !== L.epoch) return;
        var key = 'p' + data.rh_p;
        if (!(key in L.peers) || data.rh_f > L.peers[key]) L.peers[key] = data.rh_f;
        if (data.in && data.in.length) {
          var target = data.rh_f + DELAY;
          L.queue[target] = (L.queue[target] || []).concat(data.in);
        }
        return;
      }

      if (data.rh_h !== undefined) {                 // empreinte d'un pair
        if (!L || data.rh_e !== L.epoch) return;
        L.peerHash[data.rh_hf] = data.rh_h;
        checkHash(data.rh_hf);
        return;
      }

      if (data.rh_boot && !np.owner) {               // invité : charger l'état de l'hôte
        try {
          var buf = data.rh_boot;
          var u8 = (buf instanceof Uint8Array) ? buf : new Uint8Array(buf);
          if (data.rh_delay) DELAY = data.rh_delay;
          resetLockstep(data.rh_e);
          L.bootHash = hashBytes(u8);
          internalLoad = true;
          em.gameManager.loadState(u8);
          internalLoad = false;
          clearInputs();
          dbg.boots++;
          var first = !np.__rhSynced;
          np.__rhSynced = true;
          if (first) toast('NETPLAY : synchronisé avec l\'hôte');
        } catch (e) { console.warn('[RetroHome] NetPlay boot :', e); }
        return;
      }

      if (data.rh_ready && np.owner) {               // hôte : tout le monde est prêt ?
        if (!L || data.rh_e !== L.epoch) return;
        L.ready++;
        maybeGo();
        return;
      }

      if (data.rh_go && !np.owner) {                 // invité : top départ
        if (L && data.rh_e === L.epoch) startNow();
        return;
      }

      if (data.rh_hello && np.owner) {               // un invité demande une synchro
        bootPeers();
        return;
      }

      if (data.rh_resync && np.owner) { requestResync('demandée par un invité'); }
    };

    // users-updated (arrivée/départ d'un joueur) déclenche np.sync côté hôte
    np.sync = function () {
      if (!np.owner) return;
      if (peerCount() === 0) { L = null; return; }
      bootPeers();
    };

    // Chargement / redémarrage lancé par l'hôte depuis l'interface -> resynchro des invités
    var internalLoad = false;
    var gmx = em.gameManager;
    if (gmx && !gmx.__rhWrapped) {
      gmx.__rhWrapped = true;
      ['loadState', 'quickLoad', 'restart'].forEach(function (name) {
        var orig = gmx[name];
        if (typeof orig !== 'function') return;
        gmx[name] = function () {
          var r = orig.apply(gmx, arguments);
          if (!internalLoad && em.isNetplay && np.owner && L) {
            setTimeout(function () { requestResync('l\'hôte a chargé / redémarré la partie'); }, 600);
          }
          return r;
        };
      });
    }

    // Rejoindre une room : le moteur n'envoie jamais le mot de passe et échoue en
    // silence -> on gère le mot de passe et on affiche les erreurs.
    function guid() {
      var S4 = function () { return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1); };
      return S4() + S4() + '-' + S4() + '-' + S4() + '-' + S4() + '-' + S4() + S4() + S4();
    }
    var JOIN_ERRORS = {
      'room not found': 'cette partie n\'existe plus',
      'wrong password': 'mot de passe incorrect',
      'room full': 'la partie est complète'
    };
    np.joinRoom = function (sessionid, roomName, password) {
      function go(pw) {
        np.playerID = guid();
        np.players = {};
        np.extra = {
          domain: window.location.host,
          game_id: em.config.gameId,
          room_name: roomName,
          player_name: np.name,
          userid: np.playerID,
          sessionid: sessionid
        };
        np.players[np.playerID] = np.extra;
        np.startSocketIO(function () {
          np.socket.emit('join-room', { extra: np.extra, password: pw || '' }, function (error, users) {
            if (error) {
              toast('NETPLAY : impossible de rejoindre — ' + (JOIN_ERRORS[error] || error));
              try { np.socket.disconnect(); } catch (e) {}
              return;
            }
            np.players = users;
            np.roomJoined(false, roomName, pw || '', sessionid);
          });
        });
      }
      if (typeof password === 'string') { go(password); return; }
      np.getOpenRooms().then(function (rooms) {
        var info = rooms && rooms[sessionid];
        if (info && info.password) {
          var pw = window.prompt('Cette partie est protégée. Mot de passe :', '');
          if (pw === null) return;
          go(pw);
        } else {
          go('');
        }
      }).catch(function () { go(''); });
    };

    var origRoom = np.roomJoined;
    np.roomJoined = function (isOwner, roomName, password, roomId) {
      origRoom.call(np, isOwner, roomName, password, roomId);
      np.__rhSynced = false;
      L = null;
      if (!isOwner) {
        toast('NETPLAY : connexion à la partie de l\'hôte…');
        var tries = 0;
        var iv = setInterval(function () {
          tries++;
          if (np.__rhSynced || !em.isNetplay || tries > 24) { clearInterval(iv); return; }
          if (tries === 6) toast('NETPLAY : toujours en attente de l\'hôte…');
          send({ rh_hello: true });  // relance seulement si l'hôte n'a pas encore synchronisé
        }, 2500);
      }
    };

    var origLeft = np.roomLeft;
    np.roomLeft = function () {
      L = null;
      return origLeft.apply(np, arguments);
    };
  }

  function hook(em) {
    if (em.__rhNetHook) return;
    em.__rhNetHook = true;

    if (typeof em.openNetplayMenu === 'function') {
      var oo = em.openNetplayMenu;
      em.openNetplayMenu = function () {
        oo.apply(em, arguments);
        try { autoFillNetplayName(em); } catch (e) {}
      };
    }
    if (typeof em.defineNetplayFunctions === 'function') {
      var od = em.defineNetplayFunctions.bind(em);
      em.defineNetplayFunctions = function () {
        od();
        try { applyNetplayPatch(em); } catch (e) { console.warn('[RetroHome] NetPlay patch :', e); }
      };
    }
    if (em.netplay && typeof em.netplay.simulateInput === 'function') {
      try { applyNetplayPatch(em); } catch (e) {}
    }
  }

  function ensure(em) {
    em = em || window.EJS_emulator;
    if (!em) return false;
    if (!em.__rhNetHook && typeof em.openNetplayMenu === 'function') hook(em);
    if (em.netplay && typeof em.netplay.simulateInput === 'function' && !em.netplay.__rhInputPatched) {
      try { applyNetplayPatch(em); } catch (e) {}
    }
    return !!em.__rhNetHook;
  }

  // Accroche chaque nouvelle instance d'émulateur, dès que le menu netplay existe.
  setInterval(function () { ensure(); }, 250);

  window.RHNetplay = { nick: netplayNick, autoFillName: autoFillNetplayName, ensure: ensure };
})();
