const http = require('http');
const { Server } = require('socket.io');

const PORT = 3000;

const httpServer = http.createServer((req, res) => {
    // CORS pour HTTP standard
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Netplay Server Running');
});

const io = new Server(httpServer, {
    cors: {
        origin: "*", // Autoriser toutes les origines pour le dev
        methods: ["GET", "POST"]
    }
});

const rooms = {};

io.on('connection', (socket) => {
    console.log(`[SYS] Nouveau socket connecté : ${socket.id}`);

    // --- LOGIQUE NETPLAY PRINCIPALE ---

    socket.on('join-room', (data, callback) => {
        // Validation des données entrantes
        const extra = data.extra || {};
        const roomId = extra.sessionid;

        if (!roomId) {
            console.error(`[ERR] ${socket.id} a tenté de rejoindre sans Room ID !`);
            return;
        }

        console.log(`[JOIN] ${extra.player_name} (${socket.id}) rejoint la Room: ${roomId}`);

        socket.join(roomId);
        socket.roomId = roomId; // Stockage pratique sur l'objet socket

        if (!rooms[roomId]) rooms[roomId] = {};

        // On stocke les infos du joueur
        rooms[roomId][socket.id] = extra;

        // 1. Notifier tout le monde dans la room (sauf soi-même) qu'un joueur arrive
        socket.to(roomId).emit('users-updated', rooms[roomId]);

        // 2. Renvoyer la liste complète au joueur qui vient d'arriver (via callback)
        if (callback) {
            callback(null, rooms[roomId]);
        }

        console.log(`[ROOM] État de la room ${roomId} :`, Object.keys(rooms[roomId]).length, "joueurs");
    });

    // --- RELAIS WEBRTC (SIGNALING) ---
    // C'est ici que la magie P2P se prépare. Si ça échoue ici, pas de sync.

    socket.on('offer', (data) => {
        console.log(`[WebRTC] OFFER de ${socket.id} vers la room ${socket.roomId}`);
        socket.to(socket.roomId).emit('offer', data);
    });

    socket.on('answer', (data) => {
        console.log(`[WebRTC] ANSWER de ${socket.id} vers la room ${socket.roomId}`);
        socket.to(socket.roomId).emit('answer', data);
    });

    socket.on('candidate', (data) => {
        // Les candidats ICE sont nombreux, on log juste un point pour ne pas spammer
        // console.log(`[WebRTC] ICE Candidate relais.`); 
        socket.to(socket.roomId).emit('candidate', data);
    });

    // --- RELAIS INPUTS (INPUT SYNC) ---
    // Utilisé une fois le P2P établi ou en fallback
    socket.on('data-message', (data) => {
        if (socket.roomId) {
            socket.to(socket.roomId).emit('data-message', data);
        }
    });

    // --- DECONNEXION ---
    socket.on('disconnect', () => {
        const roomId = socket.roomId;
        console.log(`[DISC] ${socket.id} déconnecté.`);

        if (roomId && rooms[roomId]) {
            // Supprimer le joueur de la liste
            delete rooms[roomId][socket.id];

            // Informer les survivants
            socket.to(roomId).emit('users-updated', rooms[roomId]);
            socket.to(roomId).emit('user-disconnected', socket.id);

            // Si la room est vide, on nettoie la variable globale
            if (Object.keys(rooms[roomId]).length === 0) {
                console.log(`[CLEAN] Room ${roomId} vide, suppression.`);
                delete rooms[roomId];
            }
        }
    });
});

httpServer.listen(PORT, () => {
    console.log(`✅ NETPLAY SERVER PRÊT SUR LE PORT ${PORT}`);
    console.log(`   (En attente de connexions WebSocket...)`);
});