// script.js
let currentGames = [];
let consoles = [];
let currentGameSlug = null;
let autoSaveIntervalId = null;
const AUTO_SAVE_INTERVAL = 60000; // 60 seconds

// --- Fonction pour mettre à jour les stats ---
function updateStatsHeader() {
    console.log("Attempting to update stats header..."); // Log pour vérifier l'appel
    const gameCountEl = document.getElementById('stats-game-count');
    const consoleCountEl = document.getElementById('stats-console-count');

    // Vérifie si les éléments existent avant de tenter de les mettre à jour
    if (gameCountEl) {
        // Utilise la longueur du tableau chargé, ou 0 si currentGames n'est pas un tableau valide
        gameCountEl.textContent = Array.isArray(currentGames) ? currentGames.length : '0';
        console.log(`Updated game count: ${gameCountEl.textContent}`);
    } else {
        console.warn("Element #stats-game-count not found in the DOM.");
    }

    if (consoleCountEl) {
        // Utilise la longueur du tableau chargé, ou 0 si consoles n'est pas un tableau valide
        consoleCountEl.textContent = Array.isArray(consoles) ? consoles.length : '0';
        console.log(`Updated console count: ${consoleCountEl.textContent}`);
    } else {
        console.warn("Element #stats-console-count not found in the DOM.");
    }
}

// --- Fonctions de chargement ---
async function loadGames() {
    console.log("Loading games...");
    try {
        const response = await fetch('api.php?action=getGames');
        if (!response.ok) {
            console.error(`Failed to fetch games. Status: ${response.status}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const gamesData = await response.json();
        if (!Array.isArray(gamesData)) {
             console.error("Invalid data received for games. Expected an array.");
             throw new Error("Invalid games data format");
        }
        currentGames = gamesData; // Met à jour la variable globale
        console.log(`Loaded ${currentGames.length} games.`);
        displayGames(currentGames); // Affiche les jeux

        // Met à jour les états après l'affichage
        currentGames.forEach(game => updateGameCardState(game.id));

        // Gère le lancement direct via URL après chargement
        handleDirectGameLaunch();

        return currentGames; // Retourne les données pour Promise.all

    } catch (error) {
        console.error('Error loading games:', error);
        const gamesList = document.querySelector('#games-list');
        if(gamesList) gamesList.innerHTML = '<p class="text-center text-accent col-span-full">Impossible de charger les jeux. Veuillez réessayer plus tard.</p>';
        // Propager l'erreur pour Promise.all
        throw error;
    }
}

async function loadConsoles() {
    console.log("Loading consoles...");
    try {
        const response = await fetch('api.php?action=getConsoles');
         if (!response.ok) {
            console.error(`Failed to fetch consoles. Status: ${response.status}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const consolesData = await response.json();
         if (!Array.isArray(consolesData)) {
             console.error("Invalid data received for consoles. Expected an array.");
             throw new Error("Invalid consoles data format");
        }
        consoles = consolesData; // Met à jour la variable globale
        console.log(`Loaded ${consoles.length} consoles.`);
        displayConsoleButtons();
        populateConsoleFilter();

        return consoles; // Retourne les données pour Promise.all

    } catch (error) {
        console.error('Error loading consoles:', error);
         const consoleSelector = document.querySelector('.console-selector');
         if(consoleSelector) consoleSelector.innerHTML = '<p class="text-center text-accent">Erreur de chargement des consoles.</p>';
        // Propager l'erreur pour Promise.all
         throw error;
    }
}

// --- Nouvelle fonction pour gérer le lancement direct ---
function handleDirectGameLaunch() {
    const urlParams = new URLSearchParams(window.location.search);
    const gameSlugFromUrl = urlParams.get('jeu');
    currentGameSlug = gameSlugFromUrl; // Met à jour même si pas trouvé, pour popstate

    if (gameSlugFromUrl) {
        console.log(`Attempting direct launch for slug: ${gameSlugFromUrl}`);
        // Assure-toi que currentGames est bien un tableau
        const gameToStart = Array.isArray(currentGames)
            ? currentGames.find(game => game.title.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '') === gameSlugFromUrl)
            : null;

        if (gameToStart) {
            console.log(`Found game for direct launch: ${gameToStart.title}`);
            // Utilise setTimeout pour s'assurer que le DOM est stable après l'affichage initial
            setTimeout(() => startGame(gameToStart.console_slug, gameToStart.rom_path, gameToStart.title), 150);
        } else {
            console.warn(`Game with slug "${gameSlugFromUrl}" not found for direct launch.`);
             // Nettoie l'URL seulement si le jeu n'est pas trouvé
             history.replaceState(null, "", window.location.pathname);
             currentGameSlug = null; // Réinitialise car le jeu n'a pas été lancé
        }
    }
}

// --- Nouvelle fonction pour mettre à jour l'état d'une carte de jeu ---
function updateGameCardState(gameId) {
    const game = currentGames.find(g => g.id === gameId);
    if (!game) return; // Jeu non trouvé dans les données

    const gameCard = document.querySelector(`.game-card[data-game-id="${gameId}"]`);
    if (!gameCard) return; // Carte non trouvée dans le DOM

    // 1. Favoris
    const favoriteButton = gameCard.querySelector('.favorite-button');
    if (favoriteButton) {
        checkIfFavorited(gameId, favoriteButton);
    }

    // 2. Note moyenne
    updateAverageRatingDisplay(game); // Utilise l'objet game complet

    // 3. Note utilisateur
    fetch(`api.php?action=getRating&game_id=${gameId}`)
        .then(res => res.ok ? res.json() : Promise.reject(new Error(`Failed fetch rating for ${gameId}`)))
        .then(userRating => {
            const ratingContainer = gameCard.querySelector(`#rating-container-${gameId}`);
            if (ratingContainer) {
                ratingContainer.innerHTML = ''; // Clear previous
                ratingContainer.appendChild(createRatingElement(gameId, userRating));
            }
        })
        .catch(error => console.error(error.message || `Error fetching user rating for game ${gameId}:`, error));
}


// --- Fonctions d'affichage (displayConsoleButtons, populateConsoleFilter, displayGames) ---
// (Le code de ces fonctions reste identique à ta version précédente)
function displayConsoleButtons() {
    const consoleSelector = document.querySelector('.console-selector');
    if (!consoleSelector) return;
    consoleSelector.innerHTML = '';
    const allButton = document.createElement('button');
    allButton.className = 'console-button active px-4 py-2 rounded-md transition-all duration-200 ease-in-out transform hover:scale-105 shadow hover:shadow-lg';
    allButton.innerHTML = '<span class="font-semibold text-sm">Toutes</span>';
    allButton.addEventListener('click', () => {
        document.querySelectorAll('.console-button').forEach(b => b.classList.remove('active'));
        allButton.classList.add('active');
        document.getElementById('console-filter').value = "";
        displayGames(currentGames); // Affiche tous les jeux
        // Met à jour l'état de toutes les cartes affichées
        currentGames.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
    consoleSelector.appendChild(allButton);

    consoles.forEach(consoleData => {
        const button = document.createElement('button');
        button.className = 'console-button px-4 py-2 rounded-md transition-all duration-200 ease-in-out transform hover:scale-105 shadow hover:shadow-lg flex items-center';
        button.dataset.slug = consoleData.slug;
        const logo = document.createElement('img');
        logo.src = consoleData.logo;
        logo.alt = consoleData.name + " Logo";
        logo.className = 'console-logo-button h-8 w-8 object-contain';
        button.appendChild(logo);
        button.addEventListener('click', () => {
            document.querySelectorAll('.console-button').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            document.getElementById('console-filter').value = consoleData.slug;
            displayGamesByConsole(consoleData.slug); // Affiche et met à jour l'état
            VideoPreview.hidePreview();
        });
        consoleSelector.appendChild(button);
    });
}

function populateConsoleFilter() {
    const consoleFilter = document.getElementById('console-filter');
    if (!consoleFilter) return;
    const firstOption = consoleFilter.options[0];
    consoleFilter.innerHTML = '';
    consoleFilter.appendChild(firstOption);
    consoles.forEach(consoleData => {
        const option = document.createElement('option');
        option.value = consoleData.slug;
        option.textContent = consoleData.name;
        consoleFilter.appendChild(option);
    });
}

function displayGames(gamesToDisplay) {
    const gamesList = document.querySelector('#games-list');
    if (!gamesList) return;
    gamesList.innerHTML = '';

    if (!Array.isArray(gamesToDisplay) || gamesToDisplay.length === 0) {
        gamesList.innerHTML = '<p class="text-center text-secondary col-span-full">Aucun jeu trouvé.</p>';
        return;
    }

    gamesToDisplay.forEach(game => {
        const gameCard = document.createElement('div');
        gameCard.className = 'game-card';
        gameCard.dataset.gameId = game.id;
        gameCard.innerHTML = `
            <div class="console-header">
                <img src="${game.console_logo}" alt="${game.console_name}" class="console-logo">
            </div>
            <img src="${game.cover}" alt="${game.title}" class="game-cover" loading="lazy">
            <div class="game-details">
                <h3 class="game-title">${game.title}</h3>
                <p class="game-description">${game.description || 'Pas de description.'}</p>
                 <div class="game-info-bottom">
                    <div class="rating-section mb-2">
                        <div class="average-rating-container">
                            <span class="average-rating-stars"></span>
                            <span class="average-rating text-xs ml-1"></span>
                        </div>
                         <div id="rating-container-${game.id}" class="user-rating-stars mt-1"></div>
                    </div>
                    <div class="game-actions">
                        <span class="year">${game.year || ''}</span>
                        <div class="action-buttons">
                             <button class="favorite-button" title="Ajouter aux favoris"><i class="far fa-heart"></i></button>
                             <button class="play-button" title="Jouer à ${game.title}"><i class="fas fa-play mr-1"></i>Jouer</button>
                        </div>
                    </div>
                    ${game.preview ? `<button class="preview-button mt-3" data-preview-url="${game.preview}" title="Voir la prévisualisation"><i class="fas fa-tv mr-1"></i> Prévisualisation</button>` : ''}
                 </div>
            </div>`;

        const playButton = gameCard.querySelector('.play-button');
        if(playButton) {
            playButton.addEventListener('click', () => startGame(game.console_slug, game.rom_path, game.title));
        }
        const favoriteButton = gameCard.querySelector('.favorite-button');
        if(favoriteButton){
            favoriteButton.addEventListener('click', (event) => {
                event.stopPropagation();
                toggleFavorite(game.id, favoriteButton);
            });
        }
        const previewButton = gameCard.querySelector('.preview-button');
        if (previewButton) {
            previewButton.addEventListener('click', (event) => {
                 event.stopPropagation();
                 const previewUrl = previewButton.dataset.previewUrl;
                 const gameTitle = game.title; // Utilise directement l'objet game
                 if (VideoPreview.currentPreviewUrl === previewUrl && !document.getElementById('preview-container').style.display === 'none') return;
                 VideoPreview.showPreview(previewUrl, gameTitle);
            });
        }
        gamesList.appendChild(gameCard);
        // L'état sera mis à jour par l'appelant (loadGames, displayGamesByConsole, etc.)
    });
}

// --- Fonction de filtrage par console ---
function displayGamesByConsole(consoleSlug) {
    const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);
    displayGames(filteredGames);
    // Met à jour l'état des cartes affichées
    filteredGames.forEach(game => updateGameCardState(game.id));
    VideoPreview.hidePreview();
}

// --- Event Listeners pour filtres et recherche ---
// (Le code de ces listeners reste identique, mais ils appellent maintenant updateGameCardState après displayGames)
const consoleFilterSelect = document.getElementById('console-filter');
if (consoleFilterSelect) {
    consoleFilterSelect.addEventListener('change', function() {
        const consoleSlug = this.value;
        document.querySelectorAll('.console-button').forEach(b => b.classList.remove('active'));
        const correspondingButton = document.querySelector(`.console-button[data-slug="${consoleSlug}"]`);
        const allButton = document.querySelector('.console-button:not([data-slug])');

        let gamesToUpdate = [];
        if (consoleSlug === "") {
            if(allButton) allButton.classList.add('active');
            displayGames(currentGames);
            gamesToUpdate = currentGames; // Met à jour toutes les cartes
        } else {
            if (correspondingButton) correspondingButton.classList.add('active');
            const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);
            displayGames(filteredGames);
            gamesToUpdate = filteredGames; // Met à jour seulement les cartes filtrées
        }
        // Met à jour l'état après affichage
        gamesToUpdate.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
}

const gameSearchInput = document.getElementById('game-search');
if (gameSearchInput) {
    gameSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const selectedConsoleSlug = document.getElementById('console-filter').value;
        const filteredGames = currentGames.filter(game => {
            const titleMatch = game.title.toLowerCase().includes(searchTerm);
            const consoleMatch = (selectedConsoleSlug === "" || game.console_slug === selectedConsoleSlug);
            return titleMatch && consoleMatch;
        });
        displayGames(filteredGames);
        // Met à jour l'état après affichage
        filteredGames.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
}

// --- Fonctions Favoris (checkIfFavorited, toggleFavorite) ---
// (Le code reste identique)
async function checkIfFavorited(gameId, button) {
    if (!button) return;
    try {
        const response = await fetch(`api.php?action=isFavorite&game_id=${gameId}`);
         if (!response.ok) {
            // Ne pas bloquer si cet appel échoue, juste logguer
            console.warn(`Failed to check favorite status for game ${gameId} - Status: ${response.status}`);
            // S'assurer que le bouton a un état par défaut
            button.classList.remove('favorited');
            button.innerHTML = '<i class="far fa-heart"></i>';
            button.title = "Ajouter aux favoris";
            return;
         }
        const data = await response.json();
        if (data.isFavorite) { // Vérifie la propriété retournée par l'API
            button.classList.add('favorited');
            button.innerHTML = '<i class="fas fa-heart"></i>';
            button.title = "Retirer des favoris";
        } else {
            button.classList.remove('favorited');
            button.innerHTML = '<i class="far fa-heart"></i>';
            button.title = "Ajouter aux favoris";
        }
    } catch (error) {
        console.error(`Error checking favorite status for game ${gameId}:`, error);
        // État par défaut en cas d'erreur réseau
        button.classList.remove('favorited');
        button.innerHTML = '<i class="far fa-heart"></i>';
        button.title = "Ajouter aux favoris";
    }
}
async function toggleFavorite(gameId, button) {
    if (!button) return;
    const isCurrentlyFavorited = button.classList.contains('favorited');
    const action = isCurrentlyFavorited ? 'removeFavorite' : 'addFavorite';

    button.disabled = true;
     if (isCurrentlyFavorited) {
         button.classList.remove('favorited');
         button.innerHTML = '<i class="far fa-heart"></i>';
          button.title = "Ajouter aux favoris";
     } else {
         button.classList.add('favorited');
         button.innerHTML = '<i class="fas fa-heart"></i>';
         button.title = "Retirer des favoris";
     }

    try {
        const response = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}`
        });
        if (!response.ok) throw new Error(`API error: ${response.status}`);
        console.log(`Game ${gameId} ${action} successful.`);
        // L'UI est déjà à jour en cas de succès
    } catch (error) {
        console.error(`Error during ${action} for game ${gameId}:`, error);
         // Revert UI change on failure
         if (isCurrentlyFavorited) { // Échec de 'remove', donc remettre favori
             button.classList.add('favorited');
             button.innerHTML = '<i class="fas fa-heart"></i>';
              button.title = "Retirer des favoris";
         } else { // Échec de 'add', donc remettre non-favori
             button.classList.remove('favorited');
             button.innerHTML = '<i class="far fa-heart"></i>';
             button.title = "Ajouter aux favoris";
         }
         alert(`Erreur lors de la mise à jour des favoris. (${error.message})`);
    } finally {
         button.disabled = false;
    }
}

// --- Fonctions Notes (createRatingElement, updateAverageRatingDisplay, handleRatingClick) ---
// (Le code reste identique)
function createRatingElement(gameId, userRatingData) {
    const ratingDiv = document.createElement('div');
    ratingDiv.className = 'rating';
    ratingDiv.dataset.gameId = gameId;
    // Assure que userRatingData existe et a la propriété rating
    const currentRating = (userRatingData && typeof userRatingData.rating === 'number') ? userRatingData.rating : 0;

    for (let i = 5; i >= 1; i--) {
        const input = document.createElement('input');
        input.type = 'radio';
        input.id = `star${i}-${gameId}`;
        input.name = `rating-${gameId}`;
        input.value = i;
        input.checked = (currentRating === i); // Comparaison stricte
        input.classList.add('sr-only');
        const label = document.createElement('label');
        label.htmlFor = `star${i}-${gameId}`;
        label.title = `${i} étoile${i > 1 ? 's' : ''}`;
        label.addEventListener('click', () => handleRatingClick(gameId, i)); // Pas besoin de vérifier checked ici
        ratingDiv.appendChild(input);
        ratingDiv.appendChild(label);
    }
    return ratingDiv;
}
function updateAverageRatingDisplay(game) {
    const gameCard = document.querySelector(`.game-card[data-game-id="${game.id}"]`);
    if (!gameCard) return;
    const averageRatingStarsContainer = gameCard.querySelector('.average-rating-stars');
    const averageRatingTextContainer = gameCard.querySelector('.average-rating');
    if (!averageRatingStarsContainer || !averageRatingTextContainer) return;

    averageRatingStarsContainer.innerHTML = '';
    const average = parseFloat(game.average_rating) || 0;
    const count = parseInt(game.rating_count) || 0;

    if (count > 0) {
        const fullStars = Math.floor(average);
        const halfStar = average % 1 >= 0.5;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        for (let i = 0; i < fullStars; i++) averageRatingStarsContainer.innerHTML += '<i class="fas fa-star"></i>';
        if (halfStar) averageRatingStarsContainer.innerHTML += '<i class="fas fa-star-half-alt"></i>';
        for (let i = 0; i < emptyStars; i++) averageRatingStarsContainer.innerHTML += '<i class="far fa-star"></i>';
        averageRatingTextContainer.textContent = `(${count} vote${count > 1 ? 's' : ''})`;
    } else {
        for (let i = 0; i < 5; i++) averageRatingStarsContainer.innerHTML += '<i class="far fa-star"></i>';
        averageRatingTextContainer.textContent = '(0 votes)';
    }
}
async function handleRatingClick(gameId, rating) {
    console.log(`Rating ${rating} clicked for game ${gameId}`);
    // Optimistic UI update (optional but improves perceived speed)
    const ratingContainer = document.querySelector(`#rating-container-${gameId}`);
    if (ratingContainer) {
        const inputs = ratingContainer.querySelectorAll('input[type="radio"]');
        inputs.forEach(input => input.checked = (parseInt(input.value) === rating));
    }

    try {
        const response = await fetch('api.php?action=addRating', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}&rating=${rating}`
        });
        if (!response.ok) {
             const errorData = await response.json().catch(() => ({ error: 'Erreur inconnue lors de la notation.' }));
             throw new Error(errorData.error || `HTTP error ${response.status}`);
        }
        const updatedData = await response.json();
        console.log("Rating successful, API returned:", updatedData);
        const gameIndex = currentGames.findIndex(g => g.id === gameId);
        if (gameIndex > -1) {
            currentGames[gameIndex].average_rating = updatedData.average_rating;
            currentGames[gameIndex].rating_count = updatedData.rating_count;
            updateAverageRatingDisplay(currentGames[gameIndex]);
        }
        // L'UI utilisateur est déjà à jour (optimistic ou sera rechargée si erreur)
    } catch (error) {
        console.error("Erreur lors de l'envoi de la note:", error);
        alert(`Erreur lors de la notation: ${error.message}`);
        // Revert/refetch user rating on failure
         fetch(`api.php?action=getRating&game_id=${gameId}`)
            .then(res => res.ok ? res.json() : Promise.reject('Failed refetch'))
            .then(userRating => {
                const container = document.querySelector(`#rating-container-${gameId}`);
                if (container) {
                    container.innerHTML = '';
                    container.appendChild(createRatingElement(gameId, userRating));
                }
            }).catch(()=>{ console.error("Failed to refetch user rating after error.")});
    }
}


// --- Fonctions de gestion du jeu (closeGame, startGame) ---
function closeGame() {
    const gameContainer = document.getElementById('game-container');
    if (!gameContainer || gameContainer.style.display === 'none') return;
    console.log("Attempting to close game...");

    if (autoSaveIntervalId) {
        clearInterval(autoSaveIntervalId);
        autoSaveIntervalId = null;
        console.log("Auto-save interval cleared.");
    }

    if (window.EJS_emulator) {
        try {
            console.log("Pausing and exiting emulator...");
            window.EJS_emulator.pause();
            window.EJS_emulator.exit(); // Appeler exit
            console.log("Emulator pause and exit called.");
        } catch (e) {
            console.warn("Could not properly exit emulator:", e);
        }
        // Nettoyage global des variables EJS
        window.EJS_emulator = null;
        window.EJS_biosUrl = undefined; // Utiliser undefined ou null
        window.EJS_gameUrl = undefined;
        window.EJS_core = undefined;
        window.EJS_onSaveState = undefined;
        window.EJS_onGameStart = undefined;
        // etc.
    }

    gameContainer.style.display = 'none';
    gameContainer.innerHTML = '';
    document.body.style.overflow = '';

    const currentUrlParams = new URLSearchParams(window.location.search);
    if (currentUrlParams.get('jeu')) {
        history.pushState(null, "", window.location.pathname);
        console.log("Cleaned game slug from URL.");
    }
    currentGameSlug = null;
     console.log("Game closed.");
}

function startGame(core, romUrl, gameName) {
    const gameContainer = document.getElementById('game-container');
    if (!gameContainer) {
        console.error("Game container not found!");
        return;
    }
    closeGame(); // Ferme jeu précédent et nettoie les variables/intervalles

    console.log(`Starting game: ${gameName} (Core: ${core}, ROM: ${romUrl})`);
    gameContainer.innerHTML = ''; // Assure un conteneur vide
    gameContainer.style.display = 'flex';
    gameContainer.style.alignItems = 'center';
    gameContainer.style.justifyContent = 'center';

    const backButton = document.createElement('button');
    backButton.className = 'back-button';
    backButton.innerHTML = '<i class="fas fa-times mr-2"></i> Fermer';
    backButton.title = "Retourner à la liste des jeux";
    backButton.onclick = closeGame;
    gameContainer.appendChild(backButton);

    const gameDiv = document.createElement('div');
    gameDiv.id = 'game'; // EJS cible cet ID
    gameDiv.style.width = '100%'; // Prend toute la largeur du container flex
    gameDiv.style.height = '100%'; // Prend toute la hauteur
    gameDiv.style.maxWidth = '100vw';
    gameDiv.style.maxHeight = 'calc(100vh - 50px)'; // Laisse place pour bouton retour
    gameContainer.appendChild(gameDiv);

    document.body.style.overflow = 'hidden';

    // Mise à jour URL et slug courant
    const gameSlug = gameName.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    history.pushState({ game: gameSlug }, gameName, `?jeu=${gameSlug}`);
    currentGameSlug = gameSlug;

    const safeRomName = romUrl.substring(romUrl.lastIndexOf('/') + 1).replace(/[^a-zA-Z0-9_.-]/g, '_');
    const saveKey = `ejs_save_${core}_${safeRomName}`;
    console.log("Using save key:", saveKey);

    // --- Configuration EJS ---
    window.EJS_player = '#game';
    window.EJS_gameName = gameName;
    window.EJS_gameUrl = romUrl;
    window.EJS_core = core;
    window.EJS_language = 'fr';
    window.EJS_pathtodata = '/data/'; // **VÉRIFIE CE CHEMIN**
    window.EJS_startOnLoaded = true;
    window.EJS_Buttons = { loadState: true, saveState: true, cheat: false, fullscreen: true, pause: true, reset: true, mute: true, volume: true, quickSave: true, quickLoad: true };
    window.EJS_Settings = { 'volume': 0.5 };
     // window.EJS_biosUrl = ''; // Décommente si nécessaire

     // --- Gestion Sauvegarde/Chargement ---

    // *** EJS_onSaveState : CORRIGÉ pour gérer Uint8Array dans data.state ***
    window.EJS_onSaveState = async (data) => {
        // Utilise les variables globales EJS car le scope de startGame n'est pas garanti ici
        const currentGameName = window.EJS_gameName || 'Unknown Game';
        const currentCore = window.EJS_core || 'unknown_core';
        const currentRomUrl = window.EJS_gameUrl || '';

        console.log(`EJS_onSaveState triggered for ${currentGameName}.`);
        console.log("Received data raw:", data); // Garde ce log utile

        let saveDataUint8Array = null;

        // 1. Vérifie si data est un objet et contient la propriété 'state' qui est un Uint8Array
        if (data && typeof data === 'object' && data.state instanceof Uint8Array) {
            console.log("Found Uint8Array in 'data.state' property.");
            saveDataUint8Array = data.state;
        }
        // Optionnel : Gérer d'autres formats si EJS change encore
        // else if (data instanceof Blob) { ... } // Si jamais il revient à envoyer un Blob
        else {
            console.error("EJS_onSaveState received unexpected data format or 'data.state' is not a Uint8Array. Type:", typeof data, "Content:", data);
        }

        // 2. Vérifie si on a bien récupéré le Uint8Array
        if (!saveDataUint8Array) {
            console.error("Failed to extract valid save data (Uint8Array) from the received data. Save cancelled.");
            alert("Erreur interne : Impossible d'extraire les données de sauvegarde (format inattendu).");
            return;
        }

        // --- Création du Blob à partir du Uint8Array ---
        let actualBlob = null;
        try {
            // Crée un Blob à partir du Uint8Array. Le type MIME n'est pas crucial ici, mais 'application/octet-stream' est générique.
            actualBlob = new Blob([saveDataUint8Array], { type: 'application/octet-stream' });
            console.log(`Created Blob from Uint8Array (size: ${(actualBlob.size / 1024).toFixed(2)} KB)`);
        } catch (e) {
            console.error("Error creating Blob from Uint8Array:", e);
            alert("Erreur interne : Impossible de préparer les données de sauvegarde.");
            return;
        }

        // --- Suite de la logique avec le Blob créé ---
        console.log(`Processing created Blob...`);
        const reader = new FileReader();

        reader.onloadend = function() {
            const base64data = reader.result;
            if (!base64data) {
                 console.error("FileReader failed to read Blob as base64.");
                 alert("Erreur interne : Échec de la lecture des données de sauvegarde.");
                 return;
            }
            console.log(`Base64 data generated (length: ${base64data.length}). Saving to localStorage...`);

            // Recalcule la clé de sauvegarde ici pour être sûr
            const safeRomName = currentRomUrl.substring(currentRomUrl.lastIndexOf('/') + 1).replace(/[^a-zA-Z0-9_.-]/g, '_');
            const saveKey = `ejs_save_${currentCore}_${safeRomName}`;
            console.log("Using save key for saving:", saveKey);

            try {
                localStorage.setItem(saveKey, base64data);
                console.log(`Saved state successfully to localStorage for key: ${saveKey}`);
            } catch (e) {
                console.error(`Error saving state to localStorage (key: ${saveKey}):`, e);
                if (e.name === 'QuotaExceededError') {
                     alert("Erreur de sauvegarde : L'espace de stockage local est plein.");
                } else {
                     alert(`Erreur lors de la sauvegarde de l'état: ${e.message}`);
                }
            }
        };

        reader.onerror = function(error) {
             console.error("FileReader error during save state processing:", error);
             alert("Erreur lors de la préparation de la sauvegarde (FileReader).");
        };

        // Utilise le Blob qu'on a créé
        reader.readAsDataURL(actualBlob);
    };

    window.EJS_onGameStart = () => {
        console.log(`EJS_onGameStart triggered for ${gameName}. Checking for saved state (key: ${saveKey})...`);
        const savedStateBase64 = localStorage.getItem(saveKey);

        if (savedStateBase64) {
            console.log(`Found saved state (length: ${savedStateBase64.length}). Attempting to load...`);
            // Vérifie si EJS_emulator est prêt (il devrait l'être ici)
            if (window.EJS_emulator && typeof window.EJS_emulator.loadState === 'function') {
                try {
                    // Conversion Base64 vers Blob
                    fetch(savedStateBase64)
                        .then(res => {
                            if (!res.ok) throw new Error(`Fetch base64 failed: ${res.statusText}`);
                            return res.blob();
                        })
                        .then(blob => {
                            console.log(`Blob (size: ${(blob.size / 1024).toFixed(2)} KB) created. Calling EJS_emulator.loadState...`);
                            window.EJS_emulator.loadState(blob);
                            console.log("EJS_emulator.loadState called."); // Ne garantit pas le succès, mais l'appel a été fait.
                        })
                        .catch(e => {
                            console.error("Error converting base64 state to Blob or calling loadState:", e);
                            alert("Impossible de charger l'état de sauvegarde précédent. Le fichier est peut-être corrompu ou incompatible.");
                        });
                } catch (e) {
                     console.error("Unexpected error during state loading process:", e);
                     alert("Une erreur inattendue est survenue lors du chargement de la sauvegarde.");
                }
            } else {
                console.warn("EJS_emulator or EJS_emulator.loadState not available at EJS_onGameStart.");
            }
        } else {
            console.log("No saved state found in localStorage.");
        }

        // --- Démarrage de l'auto-sauvegarde ---
        if (autoSaveIntervalId) { // Clear au cas où (ne devrait pas arriver avec closeGame)
            clearInterval(autoSaveIntervalId);
        }
        console.log(`Starting auto-save interval (${AUTO_SAVE_INTERVAL / 1000} seconds)`);
        autoSaveIntervalId = setInterval(() => {
            // Vérifie si l'émulateur est toujours actif et a la fonction saveState
            if (window.EJS_emulator && typeof window.EJS_emulator.saveState === 'function') {
                console.log("Auto-saving state via interval...");
                try {
                    window.EJS_emulator.saveState(); // Déclenche EJS_onSaveState
                } catch (e) {
                     console.error("Error triggering auto-save via EJS_emulator.saveState():", e);
                     // Optionnel: Arrêter l'intervalle si saveState échoue ?
                }
            } else {
                 console.warn("Auto-save interval: EJS_emulator or saveState not found, stopping interval.");
                 clearInterval(autoSaveIntervalId); // Arrête l'intervalle si l'émulateur n'est plus là
                 autoSaveIntervalId = null;
            }
        }, AUTO_SAVE_INTERVAL);
    };

    // --- Chargement script EJS ---
    const oldScript = document.querySelector('script[src$="loader.js"]');
    if (oldScript) oldScript.remove();
    const script = document.createElement('script');
    script.src = '/data/loader.js'; // **VÉRIFIE CE CHEMIN**
    script.async = true;
    script.onerror = () => { // Ajout gestion erreur chargement EJS
        console.error("Failed to load EJS loader.js. Emulator will not work.");
        alert("Erreur critique : Impossible de charger le moteur d'émulation. Vérifiez le chemin vers loader.js et votre connexion.");
        closeGame(); // Ferme la tentative de lancement
    }
    document.body.appendChild(script);
}

// --- Gestion Popstate (Navigation arrière/avant) ---
window.addEventListener('popstate', function(event){
   const gameContainer = document.getElementById('game-container');
   const isGameOpen = gameContainer && gameContainer.style.display !== 'none';
   const urlParams = new URLSearchParams(window.location.search);
   const urlHasGame = urlParams.has('jeu');

   console.log(`Popstate event: isGameOpen=${isGameOpen}, urlHasGame=${urlHasGame}`);

   if (isGameOpen && !urlHasGame) {
       console.log("Popstate: Game was open, URL changed. Closing game.");
       closeGame();
   }
   // Optionnel : si l'URL a un jeu mais qu'il n'est pas ouvert (ex: nav avant), on pourrait le relancer.
   // else if (!isGameOpen && urlHasGame) {
   //     console.log("Popstate: Game not open, but URL has game slug. Attempting relaunch...");
   //     handleDirectGameLaunch(); // Tente de relancer basé sur l'URL actuelle
   // }
});

// --- Initialisation au chargement du DOM ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded and parsed.");
    // Initialise les stats à '--'
    const gameCountEl = document.getElementById('stats-game-count');
    const consoleCountEl = document.getElementById('stats-console-count');
    if (gameCountEl) gameCountEl.textContent = '--';
    if (consoleCountEl) consoleCountEl.textContent = '--';

    // Charge jeux et consoles en parallèle, met à jour les stats une fois les deux terminés
    Promise.all([loadGames(), loadConsoles()])
        .then(([gamesResult, consolesResult]) => {
            // Ce bloc s'exécute seulement si les deux promesses réussissent
            console.log("Both games and consoles loaded successfully.");
            // Met à jour les stats avec les données finales
            updateStatsHeader();
            console.log("Initialisation terminée.");
             // Si un lancement direct était prévu, il a déjà été géré dans loadGames
             // Sauf si on veut être sûr qu'il se lance APRES les consoles (moins probable)
             // handleDirectGameLaunch(); // Déplacé dans loadGames pour l'avoir plus tôt
        })
        .catch(error => {
            // Ce bloc s'exécute si l'une ou l'autre des promesses échoue
            console.error("Initialization failed during Promise.all:", error);
            // Met à jour les stats pour indiquer l'erreur
            if (gameCountEl) gameCountEl.textContent = 'Erreur';
            if (consoleCountEl) consoleCountEl.textContent = 'Erreur';
            // Affiche un message global si nécessaire
            const container = document.querySelector('.container');
            if(container && !container.querySelector('.error-message')) { // Evite messages multiples
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message text-center text-accent pixel-font my-4';
                errorDiv.innerHTML = '<h1>Erreur critique</h1><p class="text-secondary font-body">Impossible de charger les données nécessaires. Veuillez vérifier votre connexion et réessayer.</p>';
                container.prepend(errorDiv); // Ajoute au début du container
            }
        });
});