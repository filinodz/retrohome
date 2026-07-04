/*
 * RetroHome — Serveur NetPlay (LAN)
 * ---------------------------------------------------------------
 * Implémente le protocole netplay OFFICIEL d'EmulatorJS (relais serveur).
 * Événements Socket.IO : open-room, join-room, data-message, disconnect.
 * Endpoint HTTP : GET /list?game_id=… -> liste des rooms ouvertes.
 *
 * Note : le filtrage par "domain" est volontairement IGNORÉ afin que
 * l'hôte (ex. localhost) et un ami (ex. 192.168.x.x) se voient malgré
 * des URLs d'accès différentes sur le même LAN.
 */
const http = require('http');
const os = require('os');
const { Server } = require('socket.io');

const PORT = process.env.PORT ? parseInt(process.env.PORT, 10) : 3000;

// rooms[sessionid] = { room_name, max, password, game_id, players: { userid: extra } }
const rooms = {};

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

function playerCount(room) {
  return room ? Object.keys(room.players).length : 0;
}

const httpServer = http.createServer((req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }

  const url = new URL(req.url, 'http://localhost');

  // Liste des rooms ouvertes pour un jeu donné (format attendu par EmulatorJS).
  if (url.pathname === '/list') {
    const gameId = url.searchParams.get('game_id');
    const out = {};
    for (const [sid, room] of Object.entries(rooms)) {
      // Même jeu uniquement (domaine ignoré volontairement pour le LAN).
      if (gameId && String(room.game_id) !== String(gameId)) continue;
      out[sid] = {
        room_name: room.room_name,
        current: playerCount(room),
        max: room.max,
        game_id: room.game_id   // permet au lobby RetroHome de retrouver le jeu
      };
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(out));
    return;
  }

  if (url.pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', rooms: Object.keys(rooms).length, uptime: process.uptime() }));
    return;
  }

  if (url.pathname === '/' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('RetroHome NetPlay Server Online');
    return;
  }

  res.writeHead(404);
  res.end('');
});

const io = new Server(httpServer, {
  cors: { origin: '*', methods: ['GET', 'POST'] }
});

io.on('connection', (socket) => {
  console.log(`[SYS] Socket connecté : ${socket.id}`);

  // --- CRÉER UNE ROOM (hôte) ---
  socket.on('open-room', (data, callback) => {
    const extra = (data && data.extra) || {};
    const sid = extra.sessionid;
    if (!sid) { if (callback) callback('missing sessionid'); return; }
    if (rooms[sid]) { if (callback) callback('room already exists'); return; }

    rooms[sid] = {
      room_name: extra.room_name || 'Room',
      max: parseInt(data.maxPlayers, 10) || 2,
      password: data.password || '',
      game_id: extra.game_id,
      players: {}
    };
    rooms[sid].players[extra.userid] = extra;

    socket.join(sid);
    socket.data.sid = sid;
    socket.data.userid = extra.userid;

    console.log(`[OPEN] Room "${rooms[sid].room_name}" (${sid}) par ${extra.player_name}`);
    if (callback) callback(null);
  });

  // --- REJOINDRE UNE ROOM (invité) ---
  socket.on('join-room', (data, callback) => {
    const extra = (data && data.extra) || {};
    const sid = extra.sessionid;
    const room = sid && rooms[sid];

    if (!room) { if (callback) callback('room not found'); return; }
    if (room.password && data.password !== room.password) { if (callback) callback('wrong password'); return; }
    if (playerCount(room) >= room.max) { if (callback) callback('room full'); return; }

    room.players[extra.userid] = extra;
    socket.join(sid);
    socket.data.sid = sid;
    socket.data.userid = extra.userid;

    console.log(`[JOIN] ${extra.player_name} a rejoint "${room.room_name}" (${playerCount(room)}/${room.max})`);

    // Renvoyer la liste des joueurs à l'invité, et notifier tout le monde.
    if (callback) callback(null, room.players);
    io.to(sid).emit('users-updated', room.players);
  });

  // --- RELAIS DES DONNÉES DE JEU ---
  socket.on('data-message', (data) => {
    const sid = socket.data.sid;
    if (sid) socket.to(sid).emit('data-message', data);
  });

  // --- DÉCONNEXION ---
  socket.on('disconnect', () => {
    const sid = socket.data.sid;
    const userid = socket.data.userid;
    if (!sid || !rooms[sid]) return;

    delete rooms[sid].players[userid];
    socket.leave(sid);

    if (playerCount(rooms[sid]) > 0) {
      io.to(sid).emit('users-updated', rooms[sid].players);
      console.log(`[LEAVE] ${userid} a quitté ${sid}`);
    } else {
      delete rooms[sid];
      console.log(`[CLEAN] Room ${sid} vide, supprimée.`);
    }
  });
});

httpServer.listen(PORT, '0.0.0.0', () => {
  const lan = getLanAddresses();
  console.log('==================================================');
  console.log(`  RetroHome NetPlay Server  —  PORT ${PORT}`);
  console.log('==================================================');
  console.log(`  Local   : http://localhost:${PORT}`);
  lan.forEach((ip) => console.log(`  Réseau  : http://${ip}:${PORT}   <-- à partager avec vos amis`));
  console.log('--------------------------------------------------');
  console.log('  Autorisez le port dans le pare-feu Windows.');
  console.log('  Les joueurs doivent être sur le même réseau LAN/Wi-Fi.');
  console.log('==================================================');
});
