<?php
require_once 'config.php';

// Redirect to login.php if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>RetroHome - Accueil</title>
    <link rel="icon" type="image/png" href="/assets/img/playstation.png"> <!-- Assure-toi que ce chemin est correct -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
     <!-- Animate.css pour les animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <!-- Bibliothèque idb (simplifie IndexedDB) -->
        <script src="https://cdn.jsdelivr.net/npm/idb@7/build/umd.js"></script>
    <!-- Votre CSS personnalisé -->
    <link rel="stylesheet" href="public/style.css">
     <!-- Styles additionnels (incluant animations logo) -->
     <style>
        /* --- Assurez-vous que les variables CSS sont définies dans style.css ou ici --- */
        /* Exemple de variables (à adapter à ton style.css) */
        :root {
            --primary: #64ffda;
            --secondary: #f4ff81;
            --accent: #ff4081;
            --background: #121212;
            --surface: #1e1e1e;
            --text-primary: #e0e0e0;
            --text-secondary: #b3b3b3;
            --border-color: #333333;
            --glow-color-primary: rgba(100, 255, 218, 0.4); /* Basé sur --primary */
            --glow-color-secondary: rgba(244, 255, 129, 0.4); /* Basé sur --secondary */
            --font-pixel: 'Press Start 2P', cursive; /* Assurez-vous que cette police est chargée */
            --font-body: 'Inter', sans-serif; /* Assurez-vous que cette police est chargée */
        }

        /* --- Styles pour le Logo Header --- */
        .logo-container {
            /* Utile pour positionnement relatif si besoin futur */
        }

        #header-logo {
            /* Taille initiale gérée par Tailwind (h-20 md:h-24 lg:h-28) */
            cursor: pointer;
            /* Halo lumineux initial subtil */
            filter: drop-shadow(0 0 5px var(--glow-color-primary, rgba(100, 255, 218, 0.4)))
                    drop-shadow(0 0 15px var(--glow-color-primary, rgba(100, 255, 218, 0.2)));
            /* Animation ambiante */
            animation: subtle-pulse-float 6s ease-in-out infinite alternate;
            /* Transition gérée par Tailwind (transition-all duration-300 ease-out) */
        }

        /* Effet au survol */
        #header-logo:hover {
            transform: scale(1.08) rotate(-2deg);
            /* Halo lumineux intensifié */
            filter: drop-shadow(0 0 10px var(--primary, #64ffda))
                    drop-shadow(0 0 25px var(--glow-color-primary, rgba(100, 255, 218, 0.6)))
                    drop-shadow(0 0 35px var(--glow-color-secondary, rgba(244, 255, 129, 0.3)));
        }

        /* Animation ambiante subtile */
        @keyframes subtle-pulse-float {
            0% {
                transform: translateY(0);
                filter: drop-shadow(0 0 5px var(--glow-color-primary, rgba(100, 255, 218, 0.4)))
                        drop-shadow(0 0 15px var(--glow-color-primary, rgba(100, 255, 218, 0.2)));
            }
            50% {
                filter: drop-shadow(0 0 6px var(--glow-color-primary, rgba(100, 255, 218, 0.5)))
                        drop-shadow(0 0 18px var(--glow-color-primary, rgba(100, 255, 218, 0.25)));
            }
            100% {
                transform: translateY(-4px);
                filter: drop-shadow(0 0 8px var(--glow-color-primary, rgba(100, 255, 218, 0.6)))
                        drop-shadow(0 0 22px var(--glow-color-primary, rgba(100, 255, 218, 0.3)));
            }
        }

        /* Délais pour animations décalées */
        .animate__delay-100ms { animation-delay: 0.1s; }
        .animate__delay-200ms { animation-delay: 0.2s; }
        .animate__delay-300ms { animation-delay: 0.3s; }
        .animate__delay-400ms { animation-delay: 0.4s; }
        .animate__delay-500ms { animation-delay: 0.5s; }

         /* Assurez-vous que les styles pour .pixel-font sont bien définis */
         .pixel-font {
             font-family: var(--font-pixel, 'Press Start 2P', cursive); /* Fallback */
         }
        :root { /* ... tes variables ... */ }
        .logo-container { /* ... */ }
        #header-logo { /* ... */ }
        #header-logo:hover { /* ... */ }
        @keyframes subtle-pulse-float { /* ... */ }
        .animate__delay-100ms { animation-delay: 0.1s; }
        .animate__delay-200ms { animation-delay: 0.2s; }
        /* ... autres délais ... */
        .pixel-font {
             font-family: var(--font-pixel, 'Press Start 2P', cursive); /* Fallback */
         }

        /* --- Styles pour la flèche du select (si besoin) --- */
        .console-filter-wrapper {
            position: relative; /* Nécessaire pour positionner la flèche */
        }
        /* Masque la flèche par défaut du navigateur sur certains navigateurs */
        #console-filter {
           /* appearance: none; - Déjà présent via Tailwind */
           /* background-image: none; */ /* Peut être nécessaire sur certains navigateurs */
        }
        /* Ajoute une flèche personnalisée */
        .console-filter-wrapper::after {
            content: '\f078'; /* Code Font Awesome pour chevron-down */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900; /* Solid style */
            position: absolute;
            top: 50%;
            right: 1rem; /* Ajuste la position (correspond à pr-4 sur le select + un peu d'espace) */
            transform: translateY(-50%);
            pointer-events: none; /* Pour que le clic passe au select */
            color: var(--text-secondary, #b3b3b3);
            font-size: 0.8rem; /* Ajuste la taille de la flèche */
        }
     </style>
</head>
<body class="bg-background text-text-primary font-body">
    <div class="container mx-auto px-4 pb-12">

        <header class="relative py-8 md:py-10 text-center border-b border-border-color mb-10 overflow-hidden">
            <!-- ... (ton header existant avec logo et menu profil) ... -->
             <!-- Logo Remplaçant H1 -->
             <div class="logo-container relative z-10">
                 <img src="/public/img/logo.png" alt="RetroHome Logo" id="header-logo"
                      class="mx-auto h-20 md:h-24 lg:h-28 w-auto mb-4 transition-all duration-300 ease-out
                             animate__animated animate__fadeInDown">
             </div>
            <p class="text-lg md:text-xl text-secondary animate__animated animate__fadeInUp animate__delay-100ms">
                Revivez vos classiques préférés
            </p>
            <!-- Profile Button -->
            <div class="absolute top-4 right-4 md:top-6 md:right-6 z-20">
                <!-- ... (ton bouton de profil et menu) ... -->
                 <div class="relative inline-block text-left">
                   <button type="button" id="menu-button" aria-expanded="false" aria-haspopup="true"
                           class="inline-flex items-center justify-center rounded-md shadow-sm px-4 py-2 bg-surface text-sm font-medium text-text-primary hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-background focus:ring-primary transition-colors duration-200">
                     <?= htmlspecialchars($_SESSION['username']) ?>
                     <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                       <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                     </svg>
                   </button>
                   <div id="profile-menu" tabindex="-1" role="menu" aria-orientation="vertical" aria-labelledby="menu-button"
                        class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-surface ring-1 ring-black ring-opacity-5 focus:outline-none hidden z-50">
                     <div class="py-1" role="none">
                       <a href="profile.php" role="menuitem" tabindex="-1" id="profile-link"
                          class="text-text-secondary block px-4 py-2 text-sm hover:bg-background hover:text-text-primary transition-colors duration-150">
                           Profil
                       </a>
                       <a href="#" role="menuitem" tabindex="-1" id="logout-btn"
                          class="text-text-secondary block px-4 py-2 text-sm hover:bg-background hover:text-text-primary transition-colors duration-150">
                           Déconnexion
                       </a>
                     </div>
                   </div>
                 </div>
            </div>
        </header>

        <!-- ==================== NOUVEAU HEADER STATISTIQUES ==================== -->
        <div id="stats-header" class="my-6 p-4 bg-surface rounded-lg shadow flex flex-col sm:flex-row justify-around items-center gap-4 text-center animate__animated animate__fadeInUp animate__delay-100ms">
            <div>
                <span class="block text-3xl font-bold text-primary pixel-font" id="stats-game-count">--</span>
                <span class="block text-sm text-text-secondary uppercase tracking-wider">Jeux Disponibles</span>
            </div>
            <div class="w-px h-12 bg-border-color hidden sm:block"></div> <!-- Séparateur vertical pour écrans > sm -->
            <div>
                <span class="block text-3xl font-bold text-secondary pixel-font" id="stats-console-count">--</span>
                <span class="block text-sm text-text-secondary uppercase tracking-wider">Consoles Actives</span>
            </div>
        </div>
        <!-- ================= FIN NOUVEAU HEADER STATISTIQUES ================= -->

        <!-- Console Selector -->
        <div class="console-selector my-8 flex justify-center flex-wrap gap-4 animate__animated animate__fadeInUp animate__delay-200ms">
            <!-- Contenu injecté par JS -->
        </div>

        <!-- Search and Filters -->
       <div class="filters-container my-8 flex flex-col sm:flex-row gap-4 justify-center items-center animate__animated animate__fadeInUp animate__delay-300ms">
            <!-- ... (ton input de recherche et select de console) ... -->
            <div class="search-box relative flex-grow w-full sm:w-auto sm:flex-grow-0">
                 <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-text-secondary pointer-events-none">
                     <i class="fas fa-search"></i>
                 </span>
                 <input type="text" id="game-search" placeholder="   Rechercher un jeu..."
                        class="w-full pl-10 pr-4 py-2 border border-border-color rounded-full bg-surface text-text-primary focus:ring-1 focus:ring-primary focus:border-primary transition-shadow duration-200 placeholder-text-secondary placeholder-opacity-50">
             </div>
             <div class="console-filter-wrapper relative flex-grow w-full sm:w-auto sm:flex-grow-0">
                 <select id="console-filter"
                         class="appearance-none w-full px-4 py-2 border border-border-color rounded-full bg-surface text-text-primary focus:ring-1 focus:ring-primary focus:border-primary transition-shadow duration-200 pr-8 cursor-pointer">
                     <option value="">Toutes les consoles</option>
                     <!-- Contenu injecté par JS -->
                 </select>
                 <!-- La flèche est ajoutée via CSS (cf <style> plus haut) -->
             </div>
        </div>

       <!-- Liste des jeux en grille -->
       <div class="games-list mt-10 animate__animated animate__fadeInUp animate__delay-400ms grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="games-list">
            <!-- Contenu injecté par JS (Ajout des classes grid pour un meilleur layout) -->
        </div>

        <!-- Conteneur pour lancer le jeu -->
        <div id="game-container" style="display: none;">
             <!-- Stylé par style.css -->
        </div>

       <!-- Conteneur pour la prévisualisation vidéo -->
        <div id="preview-container" class="fixed inset-0 bg-black bg-opacity-80 backdrop-blur-sm flex items-center justify-center z-40" style="display: none;">
           <!-- ... (ton conteneur de preview) ... -->
           <div id="preview-tv" class="relative w-11/12 md:w-3/4 lg:w-2/3 max-w-4xl animate__animated animate__zoomIn animate__faster">
               <div id="preview-screen" class="relative bg-black overflow-hidden rounded-lg shadow-lg aspect-video">
                   <video id="preview-video" class="w-full h-full object-cover" playsinline autoplay muted loop></video>
                   <div id="preview-game-title" class="absolute bottom-0 left-0 right-0 p-3 bg-gradient-to-t from-black/70 to-transparent text-white text-base md:text-lg font-semibold text-center truncate"></div>
               </div>
               <div class="tv-controls absolute -top-4 -right-4 z-10">
                   <button id="close-preview-btn"
                           class="text-white bg-red-600 hover:bg-red-700 rounded-full w-9 h-9 flex items-center justify-center text-xl shadow-md transition-transform transform hover:scale-110">
                           <i class="fas fa-times"></i>
                   </button>
               </div>
           </div>
        </div>

    </div> <!-- Fin .container -->

    <!-- Inclusion des scripts -->
    <script src="public/video-preview.js"></script>
    <script src="public/script.js"></script>
    <script>
        // --- Gestion Menu déroulant profil ---
        const menuButton = document.getElementById('menu-button');
        const profileMenu = document.getElementById('profile-menu');

        if (menuButton && profileMenu) {
            menuButton.addEventListener('click', (event) => {
                 event.stopPropagation(); // Empêche la fermeture immédiate par le listener document
                const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
                profileMenu.classList.toggle('hidden');
                // Ajouter/supprimer classes d'animation Animate.css
                if (profileMenu.classList.contains('hidden')) {
                    profileMenu.classList.remove('animate__animated', 'animate__fadeIn'); // Ou autre animation d'entrée
                } else {
                    profileMenu.classList.add('animate__animated', 'animate__fadeIn'); // Animation d'apparition
                     profileMenu.classList.remove('animate__fadeOut'); // Au cas où
                }
                menuButton.setAttribute('aria-expanded', !isExpanded);
            });

            // Fermer le menu si on clique ailleurs
            document.addEventListener('click', (event) => {
                if (!profileMenu.classList.contains('hidden') && !profileMenu.contains(event.target) && !menuButton.contains(event.target)) {
                     // Animation de fermeture
                     profileMenu.classList.remove('animate__fadeIn');
                     profileMenu.classList.add('animate__fadeOut', 'animate__faster');
                     profileMenu.addEventListener('animationend', () => {
                         profileMenu.classList.add('hidden');
                         profileMenu.classList.remove('animate__animated', 'animate__fadeOut', 'animate__faster'); // Nettoyer les classes
                     }, { once: true });
                    menuButton.setAttribute('aria-expanded', 'false');
                }
            });
             // Fermeture avec Echap
             document.addEventListener('keydown', (event) => {
                 if (event.key === 'Escape' && !profileMenu.classList.contains('hidden')) {
                     // Animation de fermeture
                     profileMenu.classList.remove('animate__fadeIn');
                     profileMenu.classList.add('animate__fadeOut', 'animate__faster');
                     profileMenu.addEventListener('animationend', () => {
                         profileMenu.classList.add('hidden');
                         profileMenu.classList.remove('animate__animated', 'animate__fadeOut', 'animate__faster');
                     }, { once: true });
                    menuButton.setAttribute('aria-expanded', 'false');
                 }
             });
        }

        // --- Gestion Déconnexion ---
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (event) => {
                event.preventDefault();
                // if (!confirm("Voulez-vous vraiment vous déconnecter ?")) return; // Confirmation optionnelle
                try {
                    // Optionnel: Afficher un indicateur de chargement
                    logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Déconnexion...';
                    logoutBtn.disabled = true;

                    const response = await fetch('api.php?action=logout');

                    if (response.ok) {
                        window.location.href = 'login.php';
                    } else {
                        console.error("Logout failed", response.status, response.statusText);
                        alert('La déconnexion a échoué. Erreur: ' + response.status);
                         // Réactiver le bouton si échec
                         logoutBtn.innerHTML = 'Déconnexion';
                         logoutBtn.disabled = false;
                    }
                } catch (error) {
                    console.error("Error during logout fetch:", error);
                    alert('Une erreur réseau est survenue lors de la déconnexion.');
                     // Réactiver le bouton si échec
                    logoutBtn.innerHTML = 'Déconnexion';
                    logoutBtn.disabled = false;
                }
            });
        }

         // --- Animation d'apparition de la preview ---
         // On peut aussi gérer l'animation de fermeture dans video-preview.js si besoin
         // Ce code suppose que video-preview.js ajoute/enlève la classe 'hidden' ou change le style 'display'
         const previewContainer = document.getElementById('preview-container');
         const previewTV = document.getElementById('preview-tv');
         if(previewContainer && previewTV) {
            // Observer les changements de style ou de classe pour déclencher l'animation
             const observer = new MutationObserver(mutations => {
                 mutations.forEach(mutation => {
                     if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                         const isHidden = previewContainer.style.display === 'none' || previewContainer.classList.contains('hidden');
                         if (!isHidden) {
                             // Va être montré: ajouter l'animation d'entrée
                             previewTV.classList.remove('animate__zoomOut', 'animate__faster'); // Nettoyer sortie
                             previewTV.classList.add('animate__animated', 'animate__zoomIn', 'animate__faster');
                         }
                         // L'animation de sortie pourrait être gérée au clic sur le bouton close
                     }
                 });
             });
             observer.observe(previewContainer, { attributes: true });

              // Animation de sortie au clic sur close
              const closePreviewBtn = document.getElementById('close-preview-btn');
              if(closePreviewBtn && window.VideoPreview && typeof window.VideoPreview.hidePreview === 'function') {
                 closePreviewBtn.addEventListener('click', (e) => {
                     e.preventDefault(); // Empêche comportement par défaut si c'est un lien
                     previewTV.classList.remove('animate__zoomIn');
                     previewTV.classList.add('animate__zoomOut', 'animate__faster');
                     previewTV.addEventListener('animationend', () => {
                          window.VideoPreview.hidePreview(); // Cache après l'animation
                          previewTV.classList.remove('animate__animated', 'animate__zoomOut', 'animate__faster'); // Nettoyer
                     }, { once: true });
                 });
              }
         }


    </script>

</body>
</html>