// functions.js - Version simplifiée compatible avec le nouveau server.js

// Cette fonction est appelée au démarrage du Netplay
// Comme nous avons déplacé la logique "Room" dans server.js,
// on laisse celle-ci vide pour éviter les conflits, ou on l'utilise pour des logs.
exports.start = function(io, rooms, nofusers, dev) {
    if(dev) console.log("[Functions] Module chargé avec succès.");
    
    // On pourrait mettre ici de la logique globale supplémentaire si besoin
    // Mais server.js gère déjà : create-room, join-room et le relais des données de jeu.
};

// Cette fonction est requise par la route '/list' dans server.js
// Elle permet de nettoyer les arguments d'une URL
exports.transformArgs = function(url) {
    var args = {};
    var idx = url.indexOf('?');
    if (idx != -1) {
        var s = url.slice(idx + 1);
        var parts = s.split('&');
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].split('=');
            if (p[0]) {
                args[p[0]] = p[1] || "";
            }
        }
    }
    return args;
};