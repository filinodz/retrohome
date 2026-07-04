const http = require('http');
const os = require('os');
const { Server } = require('socket.io');

const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3000;

// Retourne les adresses IPv4 locales (LAN) pour les afficher aux joueurs
function getLanAddresses() {
  const nets = os.networkInterfaces();
  const addrs = [];
  for (const name of Object.keys(nets)) {
    for (const net of nets[name] || []) {
      if (net.family === 'IPv4' && !net.internal) addrs.push(net.address);
    }
  }
  return addrs;
}

// Stockage en mémoire des salles : { "ROOM_ID": { "SOCKET_ID": { userData } } }
const rooms = {};

const httpServer = http.createServer((req, res) => {
  // 1. En-têtes CORS (Permissif pour le réseau local)
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  // Gestion des requêtes "Preflight" (OPTIONS)
  if (req.method === 'OPTIONS') {
    res.writeHead(204);
    res.end();
    return;
  }

  // Log pour vérifier que l'émulateur "voit" le serveur HTTP
  // console.log(`[HTTP] ${req.method} ${req.url}`);

  // 2. Endpoint: Liste des salles (/list)
  // Permet au Lobby d'afficher les parties en cours
  if (req.url.startsWith('/list') || (req.url === '/' && req.method === 'GET' && req.url.length > 1)) {
    const roomList = [];

    // On transforme l'objet rooms en tableau propre pour le JSON
    for (const [id, users] of Object.entries(rooms)) {
      // On ne liste que les rooms qui ont des joueurs
      if (Object.keys(users).length > 0) {
        roomList.push({
          id: id,
          count: Object.keys(users).length,
          users: Object.values(users) // Envoie les détails des joueurs
        });
      }
    }

    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(roomList));
    return;
  }

  // 3. Endpoint: Health Check (/health) — utilisé par le client pour tester le serveur
  if (req.url.startsWith('/health')) {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', rooms: Object.keys(rooms).length, uptime: process.uptime() }));
    return;
  }

  // 4. Health Check (Racine)
  // L'émulateur fait souvent un GET / pour vérifier si le serveur est en ligne
  if (req.url === '/' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Netplay Server Online');
    return;
  }

  // 404 pour le reste
  res.writeHead(404);
  res.end('');
});

// Configuration Socket.IO
const io = new Server(httpServer, {
  cors: {
    // Configuration spécifique pour votre réseau local
    origin: [
      "http://retrohome.local",
      "http://retrohome.local:80",
      "http://localhost",
      "*" // Fallback pour le dev
    ],
    methods: ["GET", "POST"],
    credentials: true
  }
});

io.on('connection', (socket) => {
  console.log(`[SYS] Nouveau Socket connecté : ${socket.id}`);

  // --- 1. REJOINDRE UNE SALLE (JOIN) ---
  socket.on('join-room', (data, callback) => {
    const extra = data.extra || {};
    const roomId = extra.sessionid; // L'ID de la room (ex: "XJ9D2")

    if (!roomId) {
      console.error(`[ERR] ${socket.id} a tenté de rejoindre sans 'sessionid'`);
      return;
    }

    // Le socket rejoint le canal Socket.IO spécifique
    socket.join(roomId);
    socket.roomId = roomId; // On attache l'ID room au socket pour le retrouver lors de la déco

    // Initialisation de la room si elle n'existe pas
    if (!rooms[roomId]) rooms[roomId] = {};

    // Stockage du joueur. 
    // IMPORTANT : On utilise socket.id comme clé pour pouvoir le supprimer facilement à la déconnexion
    const userId = socket.id;
    rooms[roomId][userId] = extra;

    console.log(`[JOIN] Room ${roomId} : Joueur ${extra.player_name} (${userId}) a rejoint.`);
    console.log(`       Joueurs actuels : ${Object.keys(rooms[roomId]).length}`);

    // A. Notifier les AUTRES joueurs qu'une nouvelle liste est dispo
    socket.to(roomId).emit('users-updated', rooms[roomId]);

    // B. Renvoyer la liste au joueur qui vient d'arriver (via le callback du client)
    if (callback) {
      callback(null, rooms[roomId]);
    }
  });

  // --- 2. RELAIS WEBRTC (SIGNALING) ---
  // C'est ici que les navigateurs s'échangent les infos pour se connecter en P2P.
  // Si ces logs n'apparaissent pas, le Netplay ne marchera jamais.

  socket.on('offer', (data) => {
    // Log pour debug
    console.log(`[SIG] OFFER de ${socket.id} -> Room ${socket.roomId}`);
    if (socket.roomId) {
      socket.to(socket.roomId).emit('offer', data);
    }
  });

  socket.on('answer', (data) => {
    console.log(`[SIG] ANSWER de ${socket.id} -> Room ${socket.roomId}`);
    if (socket.roomId) {
      socket.to(socket.roomId).emit('answer', data);
    }
  });

  socket.on('candidate', (data) => {
    // Les candidates sont nombreux, on log juste un petit point ou rien
    // console.log(`[SIG] ICE Candidate de ${socket.id}`);
    if (socket.roomId) {
      socket.to(socket.roomId).emit('candidate', data);
    }
  });

  // --- 3. RELAIS DATA (INPUTS) ---
  // Fallback : Si le P2P (WebRTC) échoue, EmulatorJS essaie de passer les inputs par le serveur.
  socket.on('data-message', (data) => {
    if (socket.roomId) {
      // Relayer à tout le monde SAUF l'envoyeur
      socket.to(socket.roomId).emit('data-message', data);
    }
  });

  // --- 4. DÉCONNEXION ---
  socket.on('disconnect', () => {
    const roomId = socket.roomId;

    // Si le socket avait rejoint une room
    if (roomId && rooms[roomId]) {
      console.log(`[DISC] ${socket.id} a quitté la room ${roomId}`);

      // Suppression propre de l'utilisateur
      delete rooms[roomId][socket.id];

      // On sort du canal Socket.IO
      socket.leave(roomId);

      // On vérifie s'il reste du monde
      const remainingPlayers = Object.keys(rooms[roomId]).length;

      if (remainingPlayers > 0) {
        // S'il reste des gens, on leur dit que la liste a changé
        io.to(roomId).emit('users-updated', rooms[roomId]);
        io.to(roomId).emit('user-disconnected', socket.id);
      } else {
        // Si la room est vide, on supprime l'objet room pour libérer la RAM
        console.log(`[CLEAN] Room ${roomId} vide. Suppression.`);
        delete rooms[roomId];
      }
    } else {
      console.log(`[DISC] Socket ${socket.id} déconnecté (Sans Room)`);
    }
  });
});

// Écoute sur 0.0.0.0 pour être accessible depuis les autres machines du LAN
httpServer.listen(PORT, '0.0.0.0', () => {
  const lan = getLanAddresses();
  console.log('==================================================');
  console.log(`  RetroHome NetPlay Server  —  PORT ${PORT}`);
  console.log('==================================================');
  console.log(`  Local   : http://localhost:${PORT}`);
  lan.forEach((ip) => console.log(`  Réseau  : http://${ip}:${PORT}   <-- à partager avec vos amis`));
  console.log('--------------------------------------------------');
  console.log('  Astuce : autorisez le port dans le pare-feu Windows.');
  console.log('  Les joueurs doivent être sur le même réseau LAN/Wi-Fi.');
  console.log('==================================================');
});