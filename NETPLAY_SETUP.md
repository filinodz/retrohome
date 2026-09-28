# NetPlay — guide rapide

Le serveur NetPlay est **inclus** dans `netplay-server/` : rien à cloner.

## Démarrer le serveur (PC hôte)

- **Windows** : double-cliquez sur `START_NETPLAY.bat`
- **Linux / macOS** : `./START_NETPLAY.sh`
- **Manuel** : `cd netplay-server && npm install && npm start`

Vérification : `http://localhost:3000/health` doit répondre `{"status":"ok","version":3,…}`.
Ouvrez le port **3000/TCP** dans le pare-feu pour les autres machines du réseau.

## Jouer à deux

1. **Hôte** : ouvrez un jeu → **NETPLAY** → *Create a room* (mot de passe facultatif).
2. **Ami** (même réseau) : ouvrez le site via l'IP de l'hôte, page **Multiplayer** → **Rejoindre**
   (ou ouvrez le même jeu → **NETPLAY** → *Join*).
3. Chacun contrôle son joueur (hôte = joueur 1, ami = joueur 2), en même temps.

## Dépannage

| Symptôme | Solution |
|----------|----------|
| « Serveur NetPlay hors-ligne » sur la page Multiplayer | le serveur n'est pas lancé, ou le port 3000 est bloqué par le pare-feu |
| La room n'apparaît pas chez l'ami | l'ami doit ouvrir **le même jeu** (même titre) et être sur le même réseau |
| « en attente de l'autre joueur… » | la connexion de l'autre joueur est coupée ou son onglet est en arrière-plan |
| Anciens fichiers en cache après une mise à jour | Ctrl+F5 sur chaque poste, puis relancez le serveur |

Détails techniques : section *NetPlay* du [README](README.md).
