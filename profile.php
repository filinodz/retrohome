<?php
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id']; // Or get from URL parameter if viewing other profiles
$username = $_SESSION['username']; // Get username from session

// --- Récupération données (si besoin, sinon tout via JS/API) ---
// Pas besoin de récupérer les favoris/collections ici si tout est chargé par JS

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil de <?= htmlspecialchars($username) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <!-- CSS (Principal + Styles spécifiques Profil/Admin) -->
    <link rel="stylesheet" href="public/style.css">
    <!-- Ajouter ici les styles CSS de la section précédente (ou s'assurer qu'ils sont dans style.css) -->
    <link rel="stylesheet" href="admin/admin_style.css"> <!-- Temporaire pour réutiliser les styles admin -->
    <style>
        /* --- Styles spécifiques à la page de profil si nécessaire --- */
        /* ... (Copier/Coller les styles CSS de la section 1 ici si non mis dans style.css) ... */
         /* Styles Page Profil */
        .profile-container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .profile-header { text-align: center; margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color, #333); position: relative; }
        .profile-header .page-title { font-size: clamp(1.8rem, 5vw, 2.5rem); color: var(--primary, #64ffda); margin-bottom: 0.5rem; font-family: var(--font-pixel, 'Press Start 2P', cursive); }
        .back-home-link { position: absolute; top: 1rem; left: 1rem; color: var(--text-secondary, #b3b3b3); text-decoration: none; font-size: 0.9rem; padding: 0.5rem 0.8rem; border-radius: 6px; transition: all 0.2s ease; border: 1px solid transparent; }
        .back-home-link:hover { color: var(--text-primary, #e0e0e0); background-color: var(--surface, #1e1e1e); border-color: var(--border-color, #333); }
        .back-home-link i { margin-right: 0.4rem; }
        .profile-menu-button-container { position: absolute; top: 1rem; right: 1rem; }
        /* Styles pour #menu-button et #profile-menu (supposés dans style.css ou admin_style.css) */
        /* Section d'informations */
        .profile-section { background-color: var(--surface, #1e1e1e); border-radius: 8px; padding: 1.5rem 2rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color, #333); margin-bottom: 2rem; }
        .profile-section h3.section-title { font-size: 1.5rem; font-weight: 700; color: var(--primary, #64ffda); margin-bottom: 1.2rem; padding-bottom: 0.6rem; border-bottom: 1px solid var(--border-color, #333); font-family: var(--font-pixel, 'Press Start 2P', cursive); }
        .profile-section p { color: var(--text-secondary, #b3b3b3); margin-bottom: 0.8rem; font-size: 1rem; line-height: 1.6; }
        .profile-section p strong { color: var(--text-primary, #e0e0e0); font-weight: 600; margin-right: 0.5rem; }
        .profile-section p span { color: var(--text-primary, #e0e0e0); }
        /* Liste des favoris */
        #profile-favorites { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; min-height: 100px; }
        .favorite-game-card { background-color: var(--background, #121212); border-radius: 6px; padding: 1rem; border: 1px solid var(--border-color, #333); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; align-items: center; }
        .favorite-game-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
        .favorite-game-card .cover-wrapper { flex-shrink: 0; margin-right: 1rem; width: 64px; height: 64px; overflow: hidden; border-radius: 4px; }
        .favorite-game-card .cover-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .favorite-game-card .game-info { flex-grow: 1; min-width: 0; }
        .favorite-game-card h4 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .favorite-game-card p { font-size: 0.875rem; color: var(--text-secondary, #b3b3b3); margin-bottom: 0; }
        .no-favorite-message { font-style: italic; color: var(--text-secondary); text-align: center; padding: 2rem 1rem; grid-column: 1 / -1; }
        /* Section Collections */
        .collections-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .collections-header .section-title { border-bottom: none; margin-bottom: 0; font-size: 1.8rem; }
        #create-collection-btn { background-color: var(--primary, #64ffda); color: var(--background, #121212); padding: 0.6rem 1.2rem; border-radius: 6px; font-size: 0.9rem; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; gap: 0.4rem; }
        #create-collection-btn:hover { opacity: 0.85; box-shadow: 0 4px 10px var(--glow-color-primary, rgba(100, 255, 218, 0.3)); transform: translateY(-1px); }
        #create-collection-btn i { margin-right: 0.3rem; }
        #collections-container.collection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .collection-card { background-color: var(--surface, #1e1e1e); border-radius: 8px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color, #333); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; display: flex; flex-direction: column; cursor: pointer; position: relative; }
        .collection-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3); }
        .collection-card h3 { font-size: 1.3rem; font-weight: 700; color: var(--primary, #64ffda); margin-bottom: 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .collection-card p.collection-description { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 2.6em; flex-grow: 1; }
        .collection-games-list { margin-top: auto; padding-top: 0.8rem; border-top: 1px solid var(--border-color, #333); max-height: 150px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: var(--border-color) var(--background); }
        .collection-games-list::-webkit-scrollbar { width: 6px; } .collection-games-list::-webkit-scrollbar-track { background: var(--background); } .collection-games-list::-webkit-scrollbar-thumb { background-color: var(--border-color); border-radius: 3px; }
        .collection-games-list p.no-games-message { font-size: 0.85rem; font-style: italic; color: var(--text-secondary); text-align: center; padding: 0.5rem 0; }
        .collection-game { display: flex; align-items: center; margin-bottom: 0.6rem; } .collection-game:last-child { margin-bottom: 0; }
        .collection-game img { width: 32px; height: 32px; object-fit: cover; margin-right: 0.75rem; border-radius: 3px; flex-shrink: 0; }
        .collection-game span { font-size: 0.85rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .delete-collection-btn { background: none; border: none; color: rgba(255, 64, 129, 0.6); font-size: 1rem; cursor: pointer; transition: color 0.2s ease, transform 0.15s ease; padding: 0.4rem; line-height: 1; position: absolute; top: 0.8rem; right: 0.8rem; }
        .delete-collection-btn:hover { color: var(--accent, #ff4081); transform: scale(1.1); }
        /* Modal Création Collection */
        #collection-modal { backdrop-filter: blur(5px); z-index: 50; }
        #collection-modal .modal-content { background-color: var(--surface, #1e1e1e); padding: 2rem; border-radius: 8px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color, #333); width: 90%; max-width: 500px; }
        #collection-modal h3 { font-size: 1.5rem; font-weight: 700; color: var(--primary, #64ffda); margin-bottom: 1.5rem; text-align: center; }
        #collection-modal input[type="text"], #collection-modal textarea { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--border-color, #333); border-radius: 6px; background-color: var(--background, #121212); color: var(--text-primary, #e0e0e0); font-size: 1rem; margin-bottom: 1rem; outline: none; }
        #collection-modal input[type="text"]:focus, #collection-modal textarea:focus { border-color: var(--primary, #64ffda); box-shadow: 0 0 0 3px var(--glow-color-primary, rgba(100, 255, 218, 0.3)); }
        #collection-modal textarea { min-height: 100px; resize: vertical; }
        #collection-modal .form-actions { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end; }
        /* Utilisation des styles .form-button existants (à définir si pas déjà fait) */
         .form-button { display: inline-flex; align-items: center; padding: 0.7rem 1.5rem; border-radius: 6px; font-size: 1rem; font-weight: 700; text-decoration: none; transition: all 0.2s ease-in-out; border: 1px solid transparent; cursor: pointer; }
         .form-button.submit-button { background-color: var(--primary, #64ffda); color: var(--background, #121212); border-color: var(--primary, #64ffda); }
         .form-button.submit-button:hover { opacity: 0.85; box-shadow: 0 4px 10px var(--glow-color-primary, rgba(100, 255, 218, 0.3)); transform: translateY(-1px); }
         .form-button.cancel-button { background-color: transparent; color: var(--text-secondary, #b3b3b3); border-color: var(--border-color, #333); }
         .form-button.cancel-button:hover { background-color: var(--surface, #1e1e1e); color: var(--text-primary, #e0e0e0); border-color: var(--text-secondary, #b3b3b3); }
         /* Responsive */
        @media (max-width: 767px) {
            .profile-container { padding: 1rem; } .profile-header { margin-bottom: 2rem; padding-bottom: 1rem; } .profile-header .page-title { font-size: clamp(1.6rem, 6vw, 2rem); } .back-home-link { top: 0.5rem; left: 0.5rem; font-size: 0.8rem; padding: 0.3rem 0.6rem;} .profile-menu-button-container { top: 0.5rem; right: 0.5rem; }
            .main-profile-grid { display: flex; flex-direction: column; gap: 1.5rem; } /* Maintenir l'empilement */
            .profile-section { padding: 1rem 1.2rem; margin-bottom: 1.5rem;} .profile-section h3.section-title { font-size: 1.3rem; margin-bottom: 1rem;} .profile-section p { font-size: 0.9rem; margin-bottom: 0.6rem;}
            #profile-favorites { grid-template-columns: 1fr; gap: 0.8rem; } .favorite-game-card { padding: 0.8rem; } .favorite-game-card .cover-wrapper { width: 48px; height: 48px; margin-right: 0.8rem; } .favorite-game-card h4 { font-size: 1rem; } .favorite-game-card p { font-size: 0.8rem; }
            .collections-header { margin-bottom: 1rem; } .collections-header .section-title { font-size: 1.5rem; } #create-collection-btn { padding: 0.5rem 1rem; font-size: 0.85rem; }
            #collections-container.collection-grid { grid-template-columns: 1fr; gap: 1rem; } .collection-card { padding: 1rem; } .collection-card h3 { font-size: 1.1rem; } .collection-card p.collection-description { font-size: 0.85rem; min-height: 2.4em;} .delete-collection-btn { top: 0.5rem; right: 0.5rem; font-size: 0.9rem; }
            #collection-modal .modal-content { padding: 1.5rem; width: 95%; } #collection-modal h3 { font-size: 1.3rem; } #collection-modal .form-actions button { padding: 0.6rem 1.2rem; font-size: 0.9rem; }
        }

    </style>
</head>
<body class="bg-background text-text-primary font-body">
    <div class="profile-container"> <!-- Conteneur spécifique -->
        <header class="profile-header">
            <h1 class="page-title animate__animated animate__fadeInDown">Profil de <?= htmlspecialchars($username) ?></h1>

             <!-- Bouton de retour à l'accueil -->
             <a href="index.php" class="back-home-link animate__animated animate__fadeInLeft" title="Retour à l'accueil">
                 <i class="fas fa-arrow-left"></i> Accueil
            </a>

            <!-- Menu Profil (similaire à index.php) -->
            <div class="profile-menu-button-container animate__animated animate__fadeInRight">
              <div class="relative inline-block text-left">
                <button type="button" id="menu-button" aria-expanded="false" aria-haspopup="true"
                        class="inline-flex items-center justify-center rounded-md shadow-sm px-4 py-2 bg-surface text-sm font-medium text-text-primary hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-background focus:ring-primary transition-colors duration-200">
                  <?= htmlspecialchars($username) ?>
                  <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                  </svg>
                </button>

                <div id="profile-menu" tabindex="-1" role="menu" aria-orientation="vertical" aria-labelledby="menu-button"
                     class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-surface ring-1 ring-black ring-opacity-5 focus:outline-none hidden z-50">
                  <div class="py-1" role="none">
                    <!-- Lien Profil (déjà sur la page profil, donc optionnel) -->
                    <!-- <a href="profile.php" role="menuitem" ...>Profil</a> -->
                    <a href="#" role="menuitem" tabindex="-1" id="logout-btn"
                       class="text-text-secondary block px-4 py-2 text-sm hover:bg-background hover:text-text-primary transition-colors duration-150">
                       <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                  </div>
                </div>
              </div>
            </div>
        </header>

       <!-- Grid principale pour Info + Favoris -->
       <div class="main-profile-grid grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">

            <!-- Section Informations Profil -->
            <div class="profile-section animate__animated animate__fadeInUp">
                <h3 class="section-title">Informations</h3>
                <p><strong>Nom d'utilisateur :</strong> <span id="profile-username" class="placeholder-glow">Chargement...</span></p>
                <p><strong>Email :</strong> <span id="profile-email" class="placeholder-glow">Chargement...</span></p>
                 <p><strong>Membre depuis :</strong> <span id="profile-created-at" class="placeholder-glow">Chargement...</span></p>
                 <p><strong>Jeux favoris :</strong> <span id="profile-favorite-count" class="placeholder-glow">-</span></p>
                 <p><strong>Jeux notés :</strong><span id="profile-rating-count" class="placeholder-glow">-</span></p>
            </div>

            <!-- Section Liste des Favoris -->
             <div class="profile-section animate__animated animate__fadeInUp animate__delay-100ms">
                   <h3 class="section-title">Mes Favoris</h3>
                    <div id="profile-favorites" class="profile-favorites-grid">
                         <div class="loading-placeholder text-center py-4 text-text-secondary italic grid-column-full">
                              <i class="fas fa-spinner fa-spin mr-2"></i>Chargement des favoris...
                         </div>

                    </div>
             </div>

        </div> <!-- Fin Grid Info + Favoris -->

       <!-- Section Collections -->
         <div class="mt-8 animate__animated animate__fadeInUp animate__delay-200ms">
              <div class="collections-header">
                 <h2 class="section-title">Mes Collections</h2>
                 <button id="create-collection-btn" title="Créer une nouvelle collection">
                     <i class="fas fa-plus"></i> Créer
                 </button>
             </div>

             <div id="collections-container" class="collection-grid">

                    <div class="loading-placeholder text-center py-4 text-text-secondary italic grid-column-full">
                         <i class="fas fa-spinner fa-spin mr-2"></i>Chargement des collections...
                    </div>

             </div>
          </div>

    </div> <!-- Fin .profile-container -->

    <!-- Modal Création Collection -->
     <div id="collection-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center p-4">

         <div class="modal-content w-full max-w-md animate__animated animate__zoomIn animate__faster">
             <h3>Créer une nouvelle collection</h3>

             <div class="form-group">
                 <label for="collection-name" class="sr-only">Nom de la collection</label>
                 <input type="text" id="collection-name" placeholder="Nom de la collection *" required class="modal-input">
            </div>

             <div class="form-group">
                 <label for="collection-description" class="sr-only">Description</label>
                 <textarea id="collection-description" placeholder="Description (facultatif)" class="modal-input"></textarea>
             </div>

             <div class="form-actions">
               <button id="cancel-collection-btn" type="button" class="form-button cancel-button">Annuler</button>
               <button id="save-collection-btn" type="button" class="form-button submit-button">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
             </div>
         </div>
     </div>

    <script src="profile.js"></script>

     <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Gestion Menu déroulant profil ---
        const menuButton = document.getElementById('menu-button');
        const profileMenu = document.getElementById('profile-menu');

        if (menuButton && profileMenu) {
            menuButton.addEventListener('click', (event) => {
                 event.stopPropagation();
                const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
                profileMenu.classList.toggle('hidden');
                if (!profileMenu.classList.contains('hidden')) {
                    profileMenu.classList.remove('animate__fadeOut', 'animate__faster');
                    profileMenu.classList.add('animate__animated', 'animate__fadeIn', 'animate__faster');
                }
                menuButton.setAttribute('aria-expanded', !isExpanded);
            });

            document.addEventListener('click', (event) => {
                if (!profileMenu.classList.contains('hidden') && !profileMenu.contains(event.target) && !menuButton.contains(event.target)) {
                     profileMenu.classList.remove('animate__fadeIn');
                     profileMenu.classList.add('animate__fadeOut', 'animate__faster');
                     profileMenu.addEventListener('animationend', () => { profileMenu.classList.add('hidden'); profileMenu.classList.remove('animate__animated','animate__fadeOut','animate__faster'); }, { once: true });
                    menuButton.setAttribute('aria-expanded', 'false');
                }
            });
             document.addEventListener('keydown', (event) => {
                 if (event.key === 'Escape' && !profileMenu.classList.contains('hidden')) {
                     profileMenu.classList.remove('animate__fadeIn');
                     profileMenu.classList.add('animate__fadeOut', 'animate__faster');
                      profileMenu.addEventListener('animationend', () => { profileMenu.classList.add('hidden'); profileMenu.classList.remove('animate__animated','animate__fadeOut','animate__faster'); }, { once: true });
                    menuButton.setAttribute('aria-expanded', 'false');
                 }
             });
        }

        // --- Gestion Déconnexion ---
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (event) => {
                event.preventDefault();
                // if (!confirm("Voulez-vous vraiment vous déconnecter ?")) return;
                const originalText = logoutBtn.innerHTML;
                logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Déco...';
                logoutBtn.disabled = true;
                try {
                    const response = await fetch('api.php?action=logout');
                    if (response.ok) { window.location.href = 'login.php'; }
                    else {
                         console.error("Logout failed", response.status, response.statusText);
                         alert('La déconnexion a échoué.');
                         logoutBtn.innerHTML = originalText; logoutBtn.disabled = false;
                     }
                } catch (error) {
                    console.error("Error during logout fetch:", error);
                    alert('Erreur réseau déconnexion.');
                     logoutBtn.innerHTML = originalText; logoutBtn.disabled = false;
                }
            });
        }

         // --- Gestion Ouverture/Fermeture Modal Collection ---
         const modal = document.getElementById('collection-modal');
         const modalContent = modal ? modal.querySelector('.modal-content') : null;
         const createBtn = document.getElementById('create-collection-btn');
         const cancelBtn = document.getElementById('cancel-collection-btn');
         const saveBtn = document.getElementById('save-collection-btn'); // Le bouton save est géré dans profile.js

         if(modal && modalContent && createBtn && cancelBtn) {
            createBtn.addEventListener('click', () => {
                 modal.classList.remove('hidden');
                 modal.classList.add('flex'); // Remplacer display:flex
                 modalContent.classList.remove('animate__zoomOut');
                 modalContent.classList.add('animate__animated', 'animate__zoomIn', 'animate__faster');
            });

             const closeModal = () => {
                 modalContent.classList.remove('animate__zoomIn');
                 modalContent.classList.add('animate__zoomOut', 'animate__faster');
                 modalContent.addEventListener('animationend', () => {
                      modal.classList.add('hidden');
                      modal.classList.remove('flex');
                      modalContent.classList.remove('animate__animated', 'animate__zoomOut', 'animate__faster');
                      // Nettoyer le formulaire (géré aussi dans profile.js)
                      document.getElementById('collection-name').value = '';
                      document.getElementById('collection-description').value = '';
                 }, {once: true});
             };

            cancelBtn.addEventListener('click', closeModal);

            // Fermer si on clique en dehors du contenu du modal
             modal.addEventListener('click', (event) => {
                 if (event.target === modal) { // Si le clic est sur le fond et non sur le contenu
                     closeModal();
                 }
             });
              // Fermer avec Echap
             document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                     closeModal();
                }
             });

         } else {
             console.error("Éléments du modal de collection manquants.");
         }

    }); // Fin DOMContentLoaded
    </script>
</body>
</html>