document.addEventListener('DOMContentLoaded', () => {
    // --- Vérification initiale des éléments ---
    const elements = {
        title: document.getElementById('collection-title'),
        description: document.getElementById('collection-description'),
        gamesContainer: document.getElementById('collection-games-container'),
        gameSearchInput: document.getElementById('game-search'),
        consoleFilterSelect: document.getElementById('console-filter'),
        addGamesBtn: document.getElementById('add-games-btn'),
        gameModal: document.getElementById('game-modal'),
        modalContent: document.querySelector('#game-modal .modal-content'), // Pour animation
        modalSearchInput: document.getElementById('modal-game-search'),
        modalGamesList: document.getElementById('modal-games-list'),
        closeModalBtn: document.getElementById('close-modal-btn')
    };

    // Vérifie si tous les éléments principaux existent
    if (!elements.title || !elements.description || !elements.gamesContainer || !elements.gameSearchInput ||
        !elements.consoleFilterSelect || !elements.addGamesBtn || !elements.gameModal || !elements.modalContent ||
        !elements.modalSearchInput || !elements.modalGamesList || !elements.closeModalBtn) {
        console.error("Erreur: Un ou plusieurs éléments essentiels sont manquants sur la page collection.php.");
        // Afficher un message d'erreur à l'utilisateur ?
        document.body.innerHTML = '<p style="color:red; text-align:center; padding: 2rem;">Erreur lors de l\'initialisation de la page de collection.</p>';
        return; // Arrêter l'exécution
    }

    let allLibraryGames = []; // Cache pour tous les jeux (pour filtrage modal côté client)
    let collectionGameIds = new Set(); // IDs des jeux DANS la collection actuelle (pour vérif rapide)
    let debounceTimer; // Pour la recherche dans le modal

    // --- Fonctions de Chargement ---

    async function loadCollectionDetails() {
        elements.title.textContent = 'Chargement...'; // Placeholder initial
        try {
            const response = await fetch(`api.php?action=getCollection&collection_id=${collectionId}`);
            if (!response.ok) {
                // Gérer le cas où l'API renvoie une erreur (ex: collection non trouvée/non autorisée)
                 const errorData = await response.json().catch(() => ({})); // Essayer de lire l'erreur JSON
                throw new Error(errorData.error || `HTTP error ${response.status}`);
            }
            const collectionData = await response.json();

            elements.title.textContent = collectionData.name || 'Collection sans nom';
            elements.description.textContent = collectionData.description || '';
            document.title = `Collection : ${collectionData.name || 'Inconnue'}`;

        } catch (error) {
            console.error("Error loading collection details:", error);
            elements.title.textContent = 'Erreur chargement';
            // Rediriger ou afficher message plus clair ?
            elements.description.textContent = `Impossible de charger les détails (${error.message}).`;
        }
    }

    async function loadCollectionGames() {
        const placeholder = elements.gamesContainer.querySelector('.loading-placeholder');
        if (!placeholder) { // Mettre un placeholder si la zone est vide
             elements.gamesContainer.innerHTML = '<div class="loading-placeholder col-span-full"><i class="fas fa-spinner fa-spin mr-2"></i>Chargement des jeux...</div>';
        }

        try {
            const response = await fetch(`api.php?action=getCollectionGames&collection_id=${collectionId}`);
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);
            const games = await response.json();

            elements.gamesContainer.innerHTML = ''; // Vider le conteneur (enlève le placeholder)
            collectionGameIds.clear(); // Vider l'ancien set

            if (games.length === 0) {
                elements.gamesContainer.innerHTML = '<p class="loading-placeholder col-span-full text-text-secondary italic">Cette collection est vide. Ajoutez des jeux !</p>';
                return;
            }

            games.forEach(game => {
                collectionGameIds.add(game.id); // Ajouter l'ID au Set
                const card = createCollectionGameCard(game);
                elements.gamesContainer.appendChild(card);
            });
             // Appliquer les filtres actuels après chargement
             filterCollectionGamesUI();

        } catch (error) {
            console.error("Error loading collection games:", error);
            elements.gamesContainer.innerHTML = '<p class="loading-placeholder col-span-full text-red-400">Erreur lors du chargement des jeux de la collection.</p>';
        }
    }

     // Fonction pour créer une carte de jeu de la collection
     function createCollectionGameCard(game) {
         const card = document.createElement('div');
         // Utiliser les classes CSS définies pour .collection-game-card
         card.className = 'collection-game-card animate__animated animate__fadeIn';
         card.dataset.gameId = game.id;
         // Stocker le slug de la console pour le filtre
         card.dataset.consoleSlug = game.console_slug || '';

         card.innerHTML = `
             <img src="${game.cover || 'public/img/default_cover.png'}" alt="${game.title}" class="game-cover" loading="lazy"> {/* Ajouter lazy loading */}
             <div class="game-details">
                 <h4>${game.title || 'Titre inconnu'}</h4>
                 <p class="console-name">${game.console_name || 'Console inconnue'}</p>
                 {/* Autres éléments si nécessaire */}
             </div>
             <button class="remove-game-btn" title="Retirer de la collection" data-game-id="${game.id}">
                 <i class="fas fa-trash-alt"></i> {/* Icône plus explicite */}
             </button>
         `;
         // Ajouter l'écouteur pour supprimer
         card.querySelector('.remove-game-btn').addEventListener('click', (event) => {
             event.stopPropagation();
             if (!confirm(`Retirer "${game.title}" de cette collection ?`)) return;
             removeGameFromCollection(collectionId, game.id, card); // Passer la carte pour la supprimer de l'UI
         });
         return card;
     }


    async function loadConsolesForFilter() {
        try {
            const response = await fetch('api.php?action=getConsoles');
            if (!response.ok) throw new Error('Failed to load consoles');
            const consoles = await response.json();
            // Vider les anciennes options (sauf la première)
            elements.consoleFilterSelect.innerHTML = '<option value="">Toutes les consoles</option>';
            consoles.forEach(console => {
               const option = document.createElement('option');
                option.value = console.slug; // Utiliser le slug pour filtrer
                option.textContent = console.name;
                elements.consoleFilterSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading consoles for filter:', error);
             // Optionnel: afficher une erreur dans le select?
             elements.consoleFilterSelect.innerHTML = '<option value="">Erreur chargement</option>';
        }
    }

    // --- Fonctions de Filtrage ---

    function filterCollectionGamesUI() {
        const searchTerm = elements.gameSearchInput.value.toLowerCase();
        const consoleSlug = elements.consoleFilterSelect.value; // Le slug est dans la value de l'option

        const gameCards = elements.gamesContainer.querySelectorAll('.collection-game-card');
        let visibleCount = 0;

        gameCards.forEach(card => {
            // Vérifier si les éléments existent avant d'accéder à textContent
            const titleElement = card.querySelector('h4');
            const consoleElement = card.querySelector('p.console-name'); // Plus spécifique
            const cardConsoleSlug = card.dataset.consoleSlug || ''; // Lire depuis data attribute

            const title = titleElement ? titleElement.textContent.toLowerCase() : '';
            // const consoleName = consoleElement ? consoleElement.textContent.toLowerCase() : ''; // On utilise le slug maintenant

            const titleMatch = title.includes(searchTerm);
            const consoleMatch = consoleSlug === '' || cardConsoleSlug === consoleSlug; // Comparer les slugs

            if (titleMatch && consoleMatch) {
                 card.style.display = 'flex'; // Ou 'block' selon ton CSS pour .collection-game-card
                 visibleCount++;
            } else {
                 card.style.display = 'none';
            }
        });

         // Afficher un message si aucun jeu ne correspond après filtrage
         let noResultMessage = elements.gamesContainer.querySelector('.no-filter-result');
         if(visibleCount === 0 && gameCards.length > 0) { // Si des cartes existent mais aucune n'est visible
             if (!noResultMessage) {
                 noResultMessage = document.createElement('p');
                 noResultMessage.className = 'loading-placeholder col-span-full text-text-secondary italic no-filter-result';
                 elements.gamesContainer.appendChild(noResultMessage);
             }
             noResultMessage.textContent = 'Aucun jeu ne correspond à vos critères de filtre.';
         } else if (noResultMessage) {
            noResultMessage.remove(); // Enlever le message si des jeux sont visibles
         }
    }

    // --- Fonctions Modal ---

    function showGameModal() {
        elements.gameModal.classList.remove('hidden');
        elements.gameModal.classList.add('flex');
        elements.modalContent.classList.remove('animate__zoomOut');
        elements.modalContent.classList.add('animate__animated', 'animate__zoomIn', 'animate__faster');
        // Charger la liste complète des jeux (une seule fois si possible)
         if (allLibraryGames.length === 0) {
            loadModalGamesList(); // Charge la liste initiale
         } else {
            displayModalGamesList(allLibraryGames); // Affiche la liste cachée
         }
         elements.modalSearchInput.focus(); // Focus sur la recherche
    }

    function hideGameModal() { // Fonction pour JS interne (animation)
         elements.modalContent.classList.remove('animate__zoomIn');
         elements.modalContent.classList.add('animate__zoomOut', 'animate__faster');
         elements.modalContent.addEventListener('animationend', () => {
              elements.gameModal.classList.add('hidden');
              elements.gameModal.classList.remove('flex');
              elements.modalContent.classList.remove('animate__animated', 'animate__zoomOut', 'animate__faster');
              elements.modalSearchInput.value = ''; // Vider recherche
              // Optionnel : vider la liste ? Non, garder en cache est mieux.
         }, {once: true});
     }

    // Charge la liste complète des jeux pour le modal (idéalement une seule fois)
    async function loadModalGamesList() {
        const placeholder = elements.modalGamesList.querySelector('.loading-placeholder');
         if (!placeholder) {
            elements.modalGamesList.innerHTML = '<div class="loading-placeholder py-8"><i class="fas fa-spinner fa-spin"></i>Chargement...</div>';
         }

        try {
            // **Optimisation:** Charger TOUS les jeux une fois
            const response = await fetch('api.php?action=getGames'); // Pas de filtre ici
             if (!response.ok) throw new Error('Failed to fetch all games');
            allLibraryGames = await response.json();
            displayModalGamesList(allLibraryGames); // Afficher la liste complète

        } catch (error) {
            console.error("Error loading all games for modal:", error);
            elements.modalGamesList.innerHTML = '<p class="loading-placeholder text-red-400">Erreur chargement jeux.</p>';
        }
    }

    // Affiche (et filtre si searchTerm fourni) les jeux dans le modal
    function displayModalGamesList(gamesToDisplay, searchTerm = '') {
        const placeholder = elements.modalGamesList.querySelector('.loading-placeholder');
        if (placeholder) placeholder.remove();
        elements.modalGamesList.innerHTML = ''; // Vider la liste actuelle

        const lowerSearchTerm = searchTerm.toLowerCase();
        const filteredGames = gamesToDisplay.filter(game =>
            game.title.toLowerCase().includes(lowerSearchTerm)
        );

        if (filteredGames.length === 0) {
             elements.modalGamesList.innerHTML = `<p class="loading-placeholder italic text-text-secondary">${searchTerm ? 'Aucun jeu correspondant trouvé.' : 'Bibliothèque vide.'}</p>`;
            return;
        }

        filteredGames.forEach(game => {
            const isAdded = collectionGameIds.has(game.id); // Utilise le Set pour vérifier
            const item = document.createElement('div');
            item.className = 'modal-game-item';
            // Utilisation des classes CSS définies
            item.innerHTML = `
                <div class="game-info-modal">
                    <img src="${game.cover || 'public/img/default_cover.png'}" alt="${game.title}">
                    <span>
                        ${game.title}
                        <span class="console-name-modal">(${game.console_name || 'N/A'})</span>
                    </span>
                </div>
                <button class="action-button ${isAdded ? 'remove-game-btn-modal' : 'add-game-btn'}" data-game-id="${game.id}" title="${isAdded ? 'Retirer de la collection' : 'Ajouter à la collection'}">
                    <i class="fas ${isAdded ? 'fa-minus-circle' : 'fa-plus-circle'}"></i>
                    <span class="hidden sm:inline ml-1">${isAdded ? 'Retirer' : 'Ajouter'}</span>
                </button>
            `;
            // Ajout écouteur pour ajouter/retirer
            item.querySelector('.action-button').addEventListener('click', (e) => {
                 toggleGameInCollection(game.id, e.currentTarget);
             });
            elements.modalGamesList.appendChild(item);
        });
    }

    // Fonction Debounce pour la recherche
    function debounce(func, delay) {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    }

    // Fonction de recherche avec debounce pour le modal
    const searchModalGamesDebounced = debounce((term) => {
        displayModalGamesList(allLibraryGames, term); // Filtre côté client
    }, 300); // Délai de 300ms


    // --- Fonctions API Ajout/Suppression ---

    async function toggleGameInCollection(gameId, buttonElement) {
        const isAdding = buttonElement.classList.contains('add-game-btn');
        const action = isAdding ? 'addGameToCollection' : 'removeGameFromCollection';
        const originalIcon = buttonElement.innerHTML; // Sauvegarde icône/texte

        buttonElement.disabled = true;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; // Indicateur chargement

        try {
            const response = await fetch(`api.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `collection_id=${collectionId}&game_id=${gameId}`
            });

            if (!response.ok) {
                 const data = await response.json().catch(() => ({}));
                 throw new Error(data.error || `Erreur ${response.status}`);
            }

            // Succès : Mettre à jour l'UI du bouton et le Set d'IDs
            if (isAdding) {
                 buttonElement.classList.remove('add-game-btn');
                 buttonElement.classList.add('remove-game-btn-modal');
                 buttonElement.innerHTML = '<i class="fas fa-minus-circle"></i><span class="hidden sm:inline ml-1">Retirer</span>';
                 buttonElement.title = 'Retirer de la collection';
                 collectionGameIds.add(gameId);
                 // Optionnel : Ajouter dynamiquement la carte à la grille principale ?
                 // addGameCardToPage(gameId); // Fonction à créer qui récupère les détails du jeu ajouté
                 loadCollectionGames(); // Recharger la grille principale pour simplicité
            } else {
                 buttonElement.classList.remove('remove-game-btn-modal');
                 buttonElement.classList.add('add-game-btn');
                 buttonElement.innerHTML = '<i class="fas fa-plus-circle"></i><span class="hidden sm:inline ml-1">Ajouter</span>';
                 buttonElement.title = 'Ajouter à la collection';
                 collectionGameIds.delete(gameId);
                 // Supprimer dynamiquement la carte de la grille principale
                  const cardToRemove = elements.gamesContainer.querySelector(`.collection-game-card[data-game-id="${gameId}"]`);
                  if (cardToRemove) {
                       cardToRemove.classList.add('animate__animated', 'animate__fadeOut');
                       cardToRemove.addEventListener('animationend', () => cardToRemove.remove(), {once: true});
                  }
                  // Vérifier si la collection est vide après suppression
                  if(collectionGameIds.size === 0) {
                     elements.gamesContainer.innerHTML = '<p class="loading-placeholder col-span-full text-text-secondary italic">Cette collection est vide.</p>';
                  }
            }

        } catch (error) {
            console.error(`Erreur lors de ${action}:`, error);
            alert(`Erreur: ${error.message}`);
            buttonElement.innerHTML = originalIcon; // Restaurer bouton si erreur
        } finally {
            buttonElement.disabled = false;
        }
    }

    // Fonction pour retirer un jeu depuis la carte de la collection principale
     async function removeGameFromCollection(collectionId, gameId, cardElement) {
         // La confirmation est déjà dans l'écouteur du bouton
         const button = cardElement.querySelector('.remove-game-btn');
         const originalIcon = button.innerHTML;
         button.disabled = true;
         button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

         try {
             const response = await fetch('api.php?action=removeGameFromCollection', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: `collection_id=${collectionId}&game_id=${gameId}`
             });
             if (!response.ok) {
                 const data = await response.json().catch(()=>{});
                 throw new Error(data.error || `Erreur ${response.status}`);
             }
             // Succès : supprimer la carte de l'UI avec animation
             collectionGameIds.delete(gameId); // Mettre à jour le Set
             cardElement.classList.add('animate__animated', 'animate__fadeOut');
             cardElement.addEventListener('animationend', () => cardElement.remove(), {once: true});
              // Vérifier si vide après suppression
              if(collectionGameIds.size === 0) {
                 elements.gamesContainer.innerHTML = '<p class="loading-placeholder col-span-full text-text-secondary italic">Cette collection est vide.</p>';
              }

         } catch (error) {
              console.error("Erreur lors de la suppression du jeu:", error);
              alert(`Erreur suppression: ${error.message}`);
              button.innerHTML = originalIcon; // Restaurer bouton
              button.disabled = false;
         }
     }


    // --- Écouteurs d'événements ---
    elements.addGamesBtn.addEventListener('click', showGameModal);
    elements.closeModalBtn.addEventListener('click', hideGameModal);

    // Filtres de la page principale
    elements.gameSearchInput.addEventListener('input', filterCollectionGamesUI);
    elements.consoleFilterSelect.addEventListener('change', filterCollectionGamesUI);

    // Recherche dans le modal (avec debounce)
    elements.modalSearchInput.addEventListener('input', () => {
        searchModalGamesDebounced(elements.modalSearchInput.value);
    });

    // --- Chargements Initiaux ---
    loadCollectionDetails();
    loadCollectionGames(); // Charge jeux collection + met à jour le Set collectionGameIds
    loadConsolesForFilter();
    // Le chargement de allLibraryGames se fait maintenant au premier clic sur "Ajouter jeux"

}); // Fin DOMContentLoaded