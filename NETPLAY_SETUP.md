# NetPlay Server Setup - EmulatorJS

## Quick Setup (LAN)

### 1. Installation

```bash
# Dans le dossier Retrohome
cd c:\wamp64\www\Retrohome

# Cloner le serveur NetPlay
git clone https://github.com/EmulatorJS/EmulatorJS-Netplay.git netplay-server

# Aller dans le dossier
cd netplay-server

# Installer les dépendances
npm install express socket.io cors
```

### 2. Démarrer le Serveur

```bash
# Lancer le serveur
node server.js
```

Le serveur démarre sur `http://localhost:3000`

### 3. Utilisation

1. **Joueur 1 (Hôte)** :
   - Ouvrir un jeu
   - Le bouton NetPlay apparaît dans EmulatorJS
   - Cliquer sur NetPlay pour créer une room
   - Partager le code room

2. **Joueur 2** :
   - Ouvrir le même jeu (même ROM)
   - Cliquer sur NetPlay
   - Entrer le code room
   - Rejoindre la partie

### 4. (Optionnel) PM2 pour Production

```bash
# Installer PM2
npm install -g pm2

# Lancer avec PM2
pm2 start server.js --name retrohome-netplay

# Voir les logs
pm2 logs retrohome-netplay

# Auto-start au redémarrage
pm2 startup
pm2 save
```

## Dépannage

### Port déjà utilisé
```bash
# Windows PowerShell
$env:PORT=4000; node server.js
```

### Connexion impossible
- Vérifier que le serveur est lancé (`http://localhost:3000`)
- Vérifier le pare-feu Windows
- Les deux joueurs doivent être sur le même réseau LAN

## Configuration Client

Le client est déjà configuré dans `script.js` :
```javascript
window.EJS_netplayUrl = 'ws://localhost:3000';
```

Pour jouer sur un autre PC du réseau LAN, remplacer `localhost` par l'IP locale :
```javascript
window.EJS_netplayUrl = 'ws://192.168.1.XXX:3000';  // IP du serveur
```
