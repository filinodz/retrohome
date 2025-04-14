<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$collectionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Valider l'ID
$userId = $_SESSION['user_id'];

if (!$collectionId) {
    header('Location: profile.php'); // Rediriger si pas d'ID valide
    exit();
}

// --- Récupérer les infos de la collection pour vérifier l'appartenance et afficher le titre ---
$collectionInfo = null;
$pageTitle = "Collection"; // Titre par défaut
$collectionDescription = ""; // Description par défaut
try {
    $stmt = $db->prepare("SELECT name, description, user_id FROM collections WHERE id = ?");
    $stmt->execute([$collectionId]);
    $collectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si la collection existe et appartient à l'utilisateur connecté
    if (!$collectionInfo || $collectionInfo['user_id'] != $userId) {
         // Rediriger vers le profil si la collection n'est pas trouvée ou n'appartient pas à l'utilisateur
         $_SESSION['profile_flash_message'] = ['type' => 'error', 'message' => 'Collection non trouvée ou accès non autorisé.'];
         header('Location: profile.php');
         exit();
    }
    $pageTitle = "Collection : " . htmlspecialchars($collectionInfo['name']);
    $collectionDescription = htmlspecialchars($collectionInfo['description'] ?? '');

} catch (PDOException $e) {
    error_log("Error fetching collection info for ID {$collectionId}: " . $e->getMessage());
    // Afficher une erreur ou rediriger ? Pour l'instant, on continue, le titre sera générique.
     $pageTitle = "Erreur Collection";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <!-- CSS (Principal + Styles Profil/Admin) -->
    <link rel="stylesheet" href="public/style.css">
     <!-- Styles spécifiques Collection (à copier/coller ici ou inclure via un fichier CSS) -->
    <link rel="stylesheet" href="admin/admin_style.css"> <!-- Réutilisation temporaire -->
     <style>
        /* Coller ici les styles CSS de la section 1 si non mis ailleurs */
        /* Styles Collection */
        .collection-container { max-width: 1280px; margin: 0 auto; padding: 1.5rem; }
        .collection-header { position: relative; margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color, #333); }
        .collection-header .title-description { text-align: center; max-width: 800px; margin: 0 auto; }
        #collection-title { font-size: clamp(1.8rem, 5vw, 2.5rem); color: var(--primary, #64ffda); margin-bottom: 0.5rem; font-family: var(--font-pixel, 'Press Start 2P', cursive); word-break: break-word; }
        #collection-description { font-size: 1rem; color: var(--text-secondary, #b3b3b3); margin-top: 0.5rem; max-width: 600px; margin-left: auto; margin-right: auto; }
        .back-profile-link { position: absolute; top: 0rem; left: 0rem; color: var(--text-secondary, #b3b3b3); text-decoration: none; font-size: 0.9rem; padding: 0.5rem 0.8rem; border-radius: 6px; transition: all 0.2s ease; border: 1px solid transparent; }
        .back-profile-link:hover { color: var(--text-primary, #e0e0e0); background-color: var(--surface, #1e1e1e); border-color: var(--border-color, #333); }
        .back-profile-link i { margin-right: 0.4rem; }
        /* Filtres */
        .collection-filters { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; align-items: center; justify-content: center; }
        .collection-filters .search-box { position: relative; min-width: 200px; max-width: 300px; flex-grow: 1; }
        .collection-filters .console-filter-wrapper { position: relative; min-width: 200px; max-width: 250px; flex-grow: 1; }
        .collection-filters .search-box input, .collection-filters .console-filter-wrapper select { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 1.5rem; background-color: var(--surface); color: var(--text-primary); font-size: 0.9rem; outline: none; width: 100%; }
        .collection-filters .search-box input { padding-left: 2.5rem; }
        .collection-filters .console-filter-wrapper select { appearance: none; padding-right: 2.5rem; cursor: pointer;}
        .collection-filters .search-box input:focus, .collection-filters .console-filter-wrapper select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--glow-color-primary, rgba(100, 255, 218, 0.3)); }
        .collection-filters .search-box .fa-search { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.9rem; pointer-events: none; }
        .collection-filters .console-filter-wrapper::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.8rem; pointer-events: none; }
        /* Grille Jeux */
        #collection-games-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .collection-game-card { background-color: var(--surface, #1e1e1e); border-radius: 8px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color, #333); overflow: hidden; position: relative; display: flex; flex-direction: column; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .collection-game-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3); }
        .collection-game-card img.game-cover { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-bottom: 1px solid var(--border-color, #333); }
        .collection-game-card .game-details { padding: 1rem; flex-grow: 1; display: flex; flex-direction: column; }
        .collection-game-card h4 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .collection-game-card p.console-name { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.8rem; flex-grow: 1; }
        .remove-game-btn { position: absolute; top: 0.6rem; right: 0.6rem; background-color: rgba(255, 64, 129, 0.7); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; font-size: 0.9rem; line-height: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .remove-game-btn:hover { background-color: var(--accent, #ff4081); transform: scale(1.1); box-shadow: 0 0 8px var(--glow-color-secondary); }
        /* Bouton Ajouter Jeux */
        .add-games-button-container { text-align: center; margin-top: 2rem; }
        #add-games-btn { background-color: var(--primary, #64ffda); color: var(--background, #121212); padding: 0.8rem 1.8rem; border-radius: 6px; font-size: 1rem; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        #add-games-btn:hover { opacity: 0.85; box-shadow: 0 4px 12px var(--glow-color-primary, rgba(100, 255, 218, 0.4)); transform: translateY(-2px); }
        /* Modal Ajout Jeux */
        #game-modal { backdrop-filter: blur(5px); z-index: 50; }
        #game-modal .modal-content { background-color: var(--surface, #1e1e1e); padding: 1.5rem; border-radius: 8px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color, #333); width: 90%; max-width: 700px; display: flex; flex-direction: column; max-height: 85vh; }
        #game-modal h3 { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 1.5rem; text-align: center; flex-shrink: 0; }
        #game-modal .modal-search-bar { margin-bottom: 1rem; flex-shrink: 0; }
        #game-modal input#modal-game-search { width: 100%; padding: 0.7rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: var(--background); color: var(--text-primary); font-size: 1rem; outline: none; }
        #game-modal input#modal-game-search:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--glow-color-primary); }
        #game-modal .modal-scroll-container { flex-grow: 1; overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1.5rem; scrollbar-width: thin; scrollbar-color: var(--border-color) var(--background); }
        #game-modal .modal-scroll-container::-webkit-scrollbar { width: 8px; } #game-modal .modal-scroll-container::-webkit-scrollbar-track { background: var(--background); } #game-modal .modal-scroll-container::-webkit-scrollbar-thumb { background-color: var(--border-color); border-radius: 4px; }
        #modal-games-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .modal-game-item { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border-color); background-color: rgba(0,0,0,0.1); border-radius: 4px; transition: background-color 0.2s ease; }
        .modal-game-item:last-child { border-bottom: none; } .modal-game-item:hover { background-color: rgba(255,255,255,0.05); }
        .modal-game-item .game-info-modal { display: flex; align-items: center; gap: 0.8rem; overflow: hidden; }
        .modal-game-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 3px; flex-shrink: 0; }
        .modal-game-item span { color: var(--text-primary); font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .modal-game-item span .console-name-modal { font-size: 0.8em; color: var(--text-secondary); margin-left: 0.3rem; }
        .modal-game-item .action-button { padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 4px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s ease; flex-shrink: 0; }
        .modal-game-item .add-game-btn { background-color: var(--primary); color: var(--background); } .modal-game-item .add-game-btn:hover { opacity: 0.8; }
        .modal-game-item .remove-game-btn-modal { background-color: var(--accent); color: var(--background); } .modal-game-item .remove-game-btn-modal:hover { opacity: 0.8; } /* Classe différente pour éviter conflit */
        #game-modal .modal-actions { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; flex-shrink: 0; }
        #close-modal-btn { /* Utilise les styles de .form-button.cancel-button */ }
        /* Responsive Collection */
        @media (max-width: 767px) {
            .collection-container { padding: 1rem; } .collection-header { margin-bottom: 1.5rem; padding-bottom: 1rem; } #collection-title { font-size: clamp(1.6rem, 6vw, 2rem); } #collection-description { font-size: 0.9rem; } .back-profile-link { top: 0.5rem; left: 0.5rem; font-size: 0.8rem; padding: 0.3rem 0.6rem; }
            .collection-filters { flex-direction: column; align-items: stretch; margin-bottom: 1.5rem; }
            #collection-games-container { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; } .collection-game-card .game-details { padding: 0.8rem; } .collection-game-card h4 { font-size: 1rem; } .collection-game-card p.console-name { font-size: 0.8rem; margin-bottom: 0.6rem; } .remove-game-btn { top: 0.4rem; right: 0.4rem; width: 26px; height: 26px; font-size: 0.8rem;}
            .add-games-button-container { margin-top: 1.5rem; } #add-games-btn { padding: 0.7rem 1.5rem; font-size: 0.9rem; }
            #game-modal .modal-content { padding: 1rem; max-height: 80vh; } #game-modal h3 { font-size: 1.3rem; margin-bottom: 1rem; } #game-modal .modal-scroll-container { max-height: 50vh; } .modal-game-item { padding: 0.5rem 0.6rem; gap: 0.5rem; } .modal-game-item img { width: 32px; height: 32px; } .modal-game-item span { font-size: 0.85rem; } .modal-game-item .action-button { padding: 0.3rem 0.6rem; font-size: 0.8rem; } #game-modal .modal-actions { padding-top: 0.8rem; } #close-modal-btn { padding: 0.6rem 1.2rem; font-size: 0.9rem; }
        }
        /* Placeholder simple */
        .loading-placeholder { text-align: center; padding: 2rem; color: var(--text-secondary); font-style: italic; grid-column: 1 / -1; } /* S'étend sur toute la grille */
        .loading-placeholder i { margin-right: 0.5rem; }
     </style>
</head>
<body class="bg-background text-text-primary font-body">
  <div class="collection-container"> <!-- Conteneur spécifique -->
    <header class="collection-header animate__animated animate__fadeInDown">
      <div class="title-description">
        <h1 id="collection-title" class="page-title">Chargement...</h1>
        <p id="collection-description" class="text-secondary"></p>
      </div>
        <a href="profile.php" class="back-profile-link" title="Retour au profil">
            <i class="fas fa-arrow-left"></i> Profil
        </a>
    </header>

    <div class="collection-filters my-6 flex flex-col sm:flex-row gap-4 justify-center items-center animate__animated animate__fadeInUp animate__delay-100ms">
        <div class="search-box relative flex-grow w-full sm:w-auto sm:flex-grow-0">
             <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-text-secondary pointer-events-none"><i class="fas fa-search"></i></span>
            <input type="text" id="game-search" placeholder="Filtrer les jeux..." class="w-full">
        </div>
        <div class="console-filter-wrapper relative flex-grow w-full sm:w-auto sm:flex-grow-0">
            <label for="console-filter" class="sr-only">Filtrer par console</label>
            <select id="console-filter" class="w-full">
              <option value="">Toutes les consoles</option>
            </select>
        </div>
    </div>

    <div id="collection-games-container" class="animate__animated animate__fadeInUp animate__delay-200ms">
         <div class="loading-placeholder">
              <i class="fas fa-spinner fa-spin"></i>Chargement des jeux...
         </div>
    </div>

    <div class="add-games-button-container animate__animated animate__fadeInUp animate__delay-300ms">
        <button id="add-games-btn">
         <i class = "fas fa-plus-circle mr-2"></i> Ajouter/Gérer les jeux
        </button>
    </div>

  </div> <!-- Fin .collection-container -->

    <div id="game-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center p-4">
        <div class="modal-content w-full max-w-3xl animate__animated animate__faster">
            <h3>Ajouter/Supprimer des jeux</h3>
            <div class="modal-search-bar">
                 <label for="modal-game-search" class="sr-only">Rechercher un jeu à ajouter/supprimer</label>
                 <input type="text" id="modal-game-search" placeholder="Rechercher dans tous les jeux...">
            </div>
            <div class="modal-scroll-container">
               <div id="modal-games-list">
                    <div class="loading-placeholder py-8"> 
                        <i class="fas fa-spinner fa-spin"></i>Chargement des jeux...
                    </div>
               </div>
            </div>
            <div class="modal-actions">
              <button id="close-modal-btn" type="button" class="form-button cancel-button">Fermer</button>
            </div>
          </div>
      </div>


  <script>
      // Passer l'ID de la collection au JS
      const collectionId = <?= json_encode($collectionId) ?>;
      const collectionName = <?= json_encode($collectionInfo['name'] ?? 'Collection') ?>; // Passer aussi le nom
  </script>
  <script src="collection.js"></script>
   <script>
        // Script JS interne pour gérer les animations du modal (similaire à profile.php)
        document.addEventListener('DOMContentLoaded', () => {
             const gameModal = document.getElementById('game-modal');
             const gameModalContent = gameModal ? gameModal.querySelector('.modal-content') : null;
             const addGamesBtn = document.getElementById('add-games-btn');
             const closeModalBtn = document.getElementById('close-modal-btn');

             if(gameModal && gameModalContent && addGamesBtn && closeModalBtn) {
                 const openModal = () => {
                    gameModal.classList.remove('hidden');
                    gameModal.classList.add('flex');
                    gameModalContent.classList.remove('animate__zoomOut');
                    gameModalContent.classList.add('animate__animated', 'animate__zoomIn', 'animate__faster');
                 };

                 const closeModal = () => {
                     gameModalContent.classList.remove('animate__zoomIn');
                     gameModalContent.classList.add('animate__zoomOut', 'animate__faster');
                     gameModalContent.addEventListener('animationend', () => {
                          gameModal.classList.add('hidden');
                          gameModal.classList.remove('flex');
                          gameModalContent.classList.remove('animate__animated', 'animate__zoomOut', 'animate__faster');
                          // Optionnel: Vider la recherche et la liste du modal
                          const modalSearch = document.getElementById('modal-game-search');
                          const modalList = document.getElementById('modal-games-list');
                          if(modalSearch) modalSearch.value = '';
                          // if(modalList) modalList.innerHTML = '<div class="loading-placeholder py-8"><i class="fas fa-spinner fa-spin"></i>Chargement...</div>'; // Remettre placeholder ?
                     }, {once: true});
                 };

                addGamesBtn.addEventListener('click', openModal);
                closeModalBtn.addEventListener('click', closeModal);

                // Fermer si clic extérieur
                gameModal.addEventListener('click', (event) => {
                    if (event.target === gameModal) closeModal();
                });
                 // Fermer avec Echap
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !gameModal.classList.contains('hidden')) closeModal();
                });

             } else {
                console.error("Éléments du modal d'ajout de jeu manquants.");
             }
        });
    </script>
</body>
</html>