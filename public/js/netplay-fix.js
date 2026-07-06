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

    function myPort() {
      var i = np.getUserIndex(np.playerID);
      return (i < 0) ? (np.owner ? 0 : 1) : i;
    }

    // Envoi de l'état du jeu en BINAIRE (socket.io le transmet nativement,
    // sans l'exploser en JSON — indispensable pour les states de 100 Ko+).
    function sendState() {
      try {
        var st = em.gameManager && em.gameManager.getState();
        // getState() peut retourner une chaîne d'erreur : on ne l'envoie pas.
        if (st && st instanceof Uint8Array && st.length > 0) {
          np.sendMessage({ rh_state: st });
          return true;
        }
      } catch (e) {}
      return false;
    }

    // 1) Input local -> appliqué immédiatement à MON port + diffusé
    np.simulateInput = function (player, index, value) {
      if (!em.isNetplay) return;
      if (player !== 0) return; // seulement le contrôleur local
      var p = myPort();
      try { em.gameManager.functions.simulateInput(p, index, value); } catch (e) {}
      np.sendMessage({ rh_in: [p, index, value] });
    };

    // 2) Réception
    np.__rhSynced = false;
    np.dataMessage = function (data) {
      if (!data) return;
      if (data.rh_in) {
        try { em.gameManager.functions.simulateInput(data.rh_in[0], data.rh_in[1], data.rh_in[2]); } catch (e) {}
      }
      if (data.rh_state && !np.owner) {
        try {
          // Le binaire arrive en ArrayBuffer (navigateur) : on normalise.
          var buf = data.rh_state;
          var u8 = (buf instanceof Uint8Array) ? buf : new Uint8Array(buf);
          if (u8.length > 0) {
            em.gameManager.loadState(u8);
            np.__rhSynced = true;
            try { em.play(true); } catch (e) {}
          }
        } catch (e) { console.warn('[RetroHome] loadState:', e); }
      }
      if (data.rh_hello && np.owner) { sendState(); }
    };

    // 3) On neutralise l'ancienne synchro lockstep (cassée)
    np.sync = function () { if (np.owner) sendState(); };
    var frame = 0;
    if (em.Module) {
      em.Module.postMainLoop = function () {
        if (!em.isNetplay) return;
        if (np.owner) {
          frame++;
          if (frame % 600 === 0) sendState(); // resynchronisation ~toutes les 10 s
        }
      };
    }

    // 4) À l'entrée dans une room, l'invité réclame l'état de l'hôte
    //    avec RÉESSAIS jusqu'à réception du premier state (le jeu de l'hôte
    //    peut ne pas être prêt au moment du join).
    var origRoom = np.roomJoined;
    np.roomJoined = function (isOwner, roomName, password, roomId) {
      origRoom.call(np, isOwner, roomName, password, roomId);
      if (!isOwner) {
        np.__rhSynced = false;
        var tries = 0;
        var iv = setInterval(function () {
          tries++;
          if (np.__rhSynced || !em.isNetplay || tries > 20) { clearInterval(iv); return; }
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
