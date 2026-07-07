/*
 * RetroHome — Correctif NETPLAY (autonome, chargé sur toutes les pages de jeu)
 * ---------------------------------------------------------------------------
 * 1) INPUTS : la synchro de contrôles native d'EmulatorJS est CASSÉE
 *    (commentaire "control syncing - broken" dans le moteur). On la remplace
 *    par un modèle simple et fonctionnel pour le LAN :
 *      • chaque input est appliqué localement à SON port + diffusé aux autres ;
 *      • l'hôte renvoie périodiquement l'état complet pour resynchroniser.
 * 2) PSEUDO : rempli automatiquement depuis le compte connecté (window.RETROHOME_USER).
 *
 * Ce module ne définit aucun global de type startGame/closeGame : il s'installe
 * tout seul et surveille l'apparition de l'émulateur, quel que soit le lanceur
 * (script.js sur l'index, ou emulator.js sur la page de jeu).
 */
(function () {
  'use strict';

  function netplayNick() {
    if (typeof window.RETROHOME_USER === 'string' && window.RETROHOME_USER.trim()) {
      return window.RETROHOME_USER.trim().slice(0, 20);
    }
    return localStorage.getItem('netplay_nickname') || ('Player' + Math.floor(1000 + Math.random() * 9000));
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
        localStorage.setItem('netplay_nickname', nick);
        submit.click();
        clearInterval(iv);
      }
      if (tries > 30) clearInterval(iv);
    }, 100);
  }

  function applyNetplayPatch(em) {
    var np = em.netplay;
    if (!np || np.__rhInputPatched) return;
    np.__rhInputPatched = true;

    console.log('[RetroHome] netplay-fix v4 (lockstep déterministe) actif');

    // ── Paramètres du lockstep ──────────────────────────────────────
    var DELAY = 5;            // délai d'input en frames (~83 ms à 60 fps)
    var REBOOT_MS = 120000;   // resync complet de sécurité (2 min)
    var STALL_MS = 4000;      // au-delà : on considère le pair perdu

    // ── État du lockstep ────────────────────────────────────────────
    var L = null; // null = lockstep inactif (jeu libre)
    function resetLockstep(epoch) {
      L = {
        epoch: epoch,      // n° de synchro : les bundles d'une autre époque sont ignorés
        frame: 0,          // frames exécutées depuis la synchro
        pending: [],       // inputs locaux capturés pendant la frame courante
        queue: {},         // queue[f] = [[port,index,value],…] à appliquer avant la frame f
        peers: {},         // port pair -> dernière frame annoncée
        blockedSince: 0,
        freeRun: false     // true = pair perdu, on ne bloque plus
      };
    }

    function toast(msg) {
      try { em.displayMessage(msg, 4000); } catch (e) {}
      console.log('[RetroHome NetPlay] ' + msg);
    }

    function myPort() {
      var i = np.getUserIndex(np.playerID);
      return (i < 0) ? (np.owner ? 0 : 1) : i;
    }

    function peerCount() {
      var n = 0;
      for (var k in np.players) { if (k !== np.playerID) n++; }
      return n;
    }

    function rawInput(port, index, value) {
      try { em.gameManager.functions.simulateInput(port, index, value); } catch (e) {}
    }

    function getStateSafe() {
      try {
        var st = em.gameManager && em.gameManager.getState();
        if (st && st instanceof Uint8Array && st.length > 0) return st;
      } catch (e) {}
      return null;
    }

    // La frame suivante (L.frame+1) peut-elle s'exécuter ?
    // Il faut les bundles de TOUS les pairs jusqu'à la frame (L.frame+1-DELAY).
    function minPeerFrame() {
      var m = Infinity;
      for (var k in L.peers) { if (L.peers[k] < m) m = L.peers[k]; }
      return (m === Infinity) ? -1 : m;
    }

    function applyQueued(f) {
      var list = L.queue[f];
      if (list) {
        for (var i = 0; i < list.length; i++) rawInput(list[i][0], list[i][1], list[i][2]);
        delete L.queue[f];
      }
    }

    function advanceGate() {
      if (!L || !em.isNetplay) return;
      var needed = L.frame + 1 - DELAY;
      var ok = L.freeRun || needed <= 0 || peerCount() === 0 || minPeerFrame() >= needed;
      if (ok) {
        applyQueued(L.frame + 1);
        L.blockedSince = 0;
        try { em.play(true); } catch (e) {}
      } else {
        if (!L.blockedSince) L.blockedSince = Date.now();
        try { em.pause(true); } catch (e) {}
        if (Date.now() - L.blockedSince > STALL_MS) {
          toast('NETPLAY : joueur distant injoignable — jeu libéré');
          L.freeRun = true;
          try { em.play(true); } catch (e) {}
        }
      }
    }

    // ── 1) Capture des inputs locaux (pas d'application immédiate :
    //       ils sont planifiés à frame+DELAY, comme chez le pair) ─────
    np.simulateInput = function (player, index, value) {
      if (!em.isNetplay) return;
      if (player !== 0) return;      // uniquement le contrôleur local
      if (index >= 24) return;       // pas de hotkeys save/load en netplay
      if (!L) { rawInput(myPort(), index, value); return; } // pas encore synchronisé
      L.pending.push([myPort(), index, value]);
    };

    // ── 2) Boucle : après chaque frame, publier ses inputs et ouvrir
    //       (ou fermer) la porte de la frame suivante ─────────────────
    var lastReboot = Date.now();
    if (em.Module) {
      em.Module.postMainLoop = function () {
        if (!em.isNetplay || !L) return;
        L.frame++;
        // publier le bundle de cette frame (même vide : il sert d'accusé)
        var msg = { rh_f: L.frame, in: L.pending };
        if (L.pending.length) {
          var target = L.frame + DELAY;
          L.queue[target] = (L.queue[target] || []).concat(L.pending);
        }
        L.pending = [];
        try { np.sendMessage(msg); } catch (e) {}
        // resync complet périodique (filet de sécurité anti-dérive)
        if (np.owner && Date.now() - lastReboot > REBOOT_MS && peerCount() > 0) {
          lastReboot = Date.now();
          bootPeers(true);
          return;
        }
        advanceGate();
      };
    }

    // ── 3) Protocole de démarrage : état complet + départ simultané ──
    var bootReady = 0;
    var epochCounter = 0;
    function bootPeers(silent) {
      if (!np.owner) return;
      var st = getStateSafe();
      if (!st) { setTimeout(function () { bootPeers(silent); }, 700); return; }
      try { em.pause(true); } catch (e) {}
      epochCounter++;
      resetLockstep(epochCounter);
      bootReady = 0;
      np.sendMessage({ rh_boot: st, rh_delay: DELAY, rh_e: epochCounter });
      if (!silent) toast('NETPLAY : synchronisation des joueurs…');
      console.log('[RetroHome NetPlay] boot envoyé (' + (st.length / 1024).toFixed(0) + ' Ko, époque ' + epochCounter + ')');
    }

    np.__rhSynced = false;
    np.dataMessage = function (data) {
      if (!data) return;

      // bundle d'inputs par frame (le cœur du lockstep)
      if (data.rh_f !== undefined && L) {
        if (data.rh_e !== L.epoch) return; // bundle d'une ancienne synchro : ignoré
        var senderKey = 'p' + data.rh_p;
        L.peers[senderKey] = Math.max(L.peers[senderKey] || 0, data.rh_f);
        if (data.in && data.in.length) {
          var target = data.rh_f + DELAY;
          L.queue[target] = (L.queue[target] || []).concat(data.in);
        }
        advanceGate();
        return;
      }

      // démarrage / resync complet (invité)
      if (data.rh_boot && !np.owner) {
        try {
          var buf = data.rh_boot;
          var u8 = (buf instanceof Uint8Array) ? buf : new Uint8Array(buf);
          try { em.pause(true); } catch (e) {}
          em.gameManager.loadState(u8);
          if (data.rh_delay) DELAY = data.rh_delay;
          resetLockstep(data.rh_e);
          np.sendMessage({ rh_ready: true });
          var first = !np.__rhSynced;
          np.__rhSynced = true;
          if (first) toast('NETPLAY : synchronisé avec l\'hôte (' + (u8.length / 1024).toFixed(0) + ' Ko)');
        } catch (e) { console.warn('[RetroHome] boot:', e); }
        return;
      }

      // l'invité est prêt -> quand tous le sont, top départ
      if (data.rh_ready && np.owner && L) {
        bootReady++;
        if (bootReady >= peerCount()) {
          np.sendMessage({ rh_go: true });
          try { em.play(true); } catch (e) {}
        }
        return;
      }

      // top départ (invité)
      if (data.rh_go && !np.owner && L) {
        try { em.play(true); } catch (e) {}
        return;
      }

      // demande de synchro d'un invité
      if (data.rh_hello && np.owner) {
        console.log('[RetroHome NetPlay] demande de synchro reçue');
        lastReboot = Date.now();
        bootPeers(false);
      }
    };

    // ── 4) Marquer chaque bundle : port émetteur + époque courante ──
    var origSend = np.sendMessage;
    np.sendMessage = function (data) {
      if (data && data.rh_f !== undefined) {
        data.rh_p = myPort();
        if (L) data.rh_e = L.epoch;
      }
      return origSend.call(np, data);
    };

    // ── 5) sync natif (users-updated côté hôte) -> boot complet ─────
    np.sync = function () {
      if (np.owner) { lastReboot = Date.now(); bootPeers(false); }
    };

    // ── 6) Arrivée dans la room ──────────────────────────────────────
    var origRoom = np.roomJoined;
    np.roomJoined = function (isOwner, roomName, password, roomId) {
      origRoom.call(np, isOwner, roomName, password, roomId);
      np.__rhSynced = false;
      L = null; // le lockstep démarre au boot
      if (!isOwner) {
        toast('NETPLAY : connexion à la partie de l\'hôte…');
        var tries = 0;
        var iv = setInterval(function () {
          tries++;
          if (np.__rhSynced || !em.isNetplay || tries > 24) { clearInterval(iv); return; }
          if (tries === 12) toast('NETPLAY : toujours en attente de l\'hôte…');
          try { np.sendMessage({ rh_hello: true }); } catch (e) {}
        }, 2500);
        try { np.sendMessage({ rh_hello: true }); } catch (e) {}
      }
    };
  }

  function hook(em) {
    if (em.__rhNetHook) return;
    em.__rhNetHook = true;

    // Auto-remplissage du pseudo à l'ouverture du menu netplay
    if (typeof em.openNetplayMenu === 'function') {
      var oo = em.openNetplayMenu;
      em.openNetplayMenu = function () {
        oo.apply(em, arguments);
        try { autoFillNetplayName(em); } catch (e) {}
      };
    }
    // Correctif d'inputs : appliqué chaque fois que les fonctions netplay sont (re)définies
    if (typeof em.defineNetplayFunctions === 'function') {
      var od = em.defineNetplayFunctions.bind(em);
      em.defineNetplayFunctions = function () {
        od();
        try { applyNetplayPatch(em); } catch (e) { console.warn('[RetroHome] netplay patch:', e); }
      };
    }
    // Si le menu a déjà été ouvert avant le hook
    if (em.netplay && typeof em.netplay.simulateInput === 'function') {
      try { applyNetplayPatch(em); } catch (e) {}
    }
  }

  // Surveillance persistante : accroche chaque nouvelle instance d'émulateur.
  setInterval(function () {
    var em = window.EJS_emulator;
    if (em && !em.__rhNetHook &&
        (typeof em.defineNetplayFunctions === 'function' || typeof em.openNetplayMenu === 'function')) {
      hook(em);
    }
  }, 500);

  window.RHNetplay = { nick: netplayNick, autoFillName: autoFillNetplayName };
})();
