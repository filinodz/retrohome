# RetroHome — serveur NetPlay

Petit serveur **Node.js + Socket.IO** qui relaie les parties NetPlay entre les joueurs d'un même réseau local.
Il implémente le protocole netplay d'EmulatorJS (`open-room`, `join-room`, `data-message`, `GET /list`) ;
la synchronisation des parties (lockstep déterministe) est assurée côté navigateur par `public/js/netplay-fix.js`.

## Démarrage

```bash
cd netplay-server
npm install      # la première fois seulement
npm start        # écoute sur le port 3000
```

Sous Windows, double-cliquez simplement sur `START_NETPLAY.bat` (à la racine de RetroHome) ;
sous Linux / macOS : `./START_NETPLAY.sh`.

Port personnalisé : `PORT=4000 npm start` (renseignez alors l'URL dans *Admin › Réglages › URL du serveur NetPlay*).

## Points d'accès

| Méthode | Chemin | Rôle |
|---|---|---|
| GET | `/health` | état du serveur (`version`, nombre de rooms) |
| GET | `/list?game_id=…` | rooms ouvertes pour un jeu (utilisé par EmulatorJS et la page Multiplayer) |
| Socket.IO | `open-room`, `join-room`, `data-message` | création / connexion / relais des données de jeu |

## Pare-feu

Autorisez le port **3000/TCP** en entrée sur la machine qui héberge le serveur, sinon vos amis ne verront pas les parties.
