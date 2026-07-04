// script.js

// --- CORRECTIF AUDIO : registre global d'AudioContext ---
// On intercepte la création des AudioContext pour pouvoir les fermer entièrement
// à la fermeture du jeu (sinon le son de l'émulateur continue en arrière-plan).
window.__RH_AUDIO_CTXS = window.__RH_AUDIO_CTXS || [];
['AudioContext', 'webkitAudioContext'].forEach(function (name) {
    var Orig = window[name];
    if (!Orig || Orig.__rhPatched) return;
    var Patched = function () {
        var inst = Reflect.construct(Orig, Array.prototype.slice.call(arguments), Orig);
        try { window.__RH_AUDIO_CTXS.push(inst); } catch (e) {}
        return inst;
    };
    Patched.prototype = Orig.prototype;
    Patched.__rhPatched = true;
    try { window[name] = Patched; } catch (e) {}
});
function rhKillAllAudio() {
    var list = window.__RH_AUDIO_CTXS || [];
    for (var i = 0; i < list.length; i++) {
        try { if (list[i] && list[i].state !== 'closed') { list[i].suspend && list[i].suspend(); list[i].close && list[i].close(); } } catch (e) {}
    }
    list.length = 0;
    try {
        document.querySelectorAll('#game audio, #game video, #game-container audio, #game-container video')
            .forEach(function (el) { try { el.pause(); el.muted = true; el.src = ''; el.load && el.load(); } catch (e) {} });
    } catch (e) {}
}

// --- VARIABLES GLOBALES (DÉPLACÉES EN HAUT POUR ÉVITER LES ERREURS) ---
var netplaySocket = null; // 'var' empêche l'erreur si le script est rechargé
var netplayRoomId = null;
var currentGames = [];
var consoles = [];
var currentGameSlug = null;
var autoSaveIntervalId = null;
var currentViewMode = localStorage.getItem('viewMode') || 'grid';
const SERVER_IP = window.location.hostname;
const NETPLAY_PORT = 3000;
const PROTOCOL = window.location.protocol;
const NETPLAY_BASE_URL_GLOBAL = `${PROTOCOL}//${SERVER_IP}:${NETPLAY_PORT}`;

// --- NOUVEAUX PARAMÈTRES URL POUR NETPLAY ---
const urlParamsGlobal = new URLSearchParams(window.location.search);
if (urlParamsGlobal.has('room')) {
    netplayRoomId = urlParamsGlobal.get('room');
    console.log("[Netplay] Room ID trouvé dans l'URL :", netplayRoomId);
}

const AUTO_SAVE_INTERVAL = 60000; // 60 seconds
let currentPage = 1;
const gamesPerPage = 20;
let filteredGames = [];

function getAssetUrl(path) {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return SITE_URL + '/' + cleanPath;
}

// --- Fonction pour mettre à jour les stats ---
function updateStatsHeader() {
    console.log("Attempting to update stats header...");
    const gameCountEl = document.getElementById('stats-game-count');
    const consoleCountEl = document.getElementById('stats-console-count');

    if (gameCountEl) {
        gameCountEl.textContent = Array.isArray(currentGames) ? currentGames.length : '0';
    } else {
        console.warn("Element #stats-game-count not found in the DOM.");
    }

    if (consoleCountEl) {
        consoleCountEl.textContent = Array.isArray(consoles) ? consoles.length : '0';
    } else {
        console.warn("Element #stats-console-count not found in the DOM.");
    }
}

// --- Fonctions de chargement ---
async function loadGames() {
    console.log("Loading games...");
    try {
        const response = await fetch(`${SITE_URL}/api?action=getGames`);
        if (!response.ok) {
            console.error(`Failed to fetch games. Status: ${response.status}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const gamesData = await response.json();
        if (!Array.isArray(gamesData)) {
            throw new Error("Invalid games data format");
        }
        currentGames = gamesData;
        console.log(`Loaded ${currentGames.length} games.`);
        displayGames(currentGames);
        currentGames.forEach(game => updateGameCardState(game.id));
        handleDirectGameLaunch();

        return currentGames;

    } catch (error) {
        console.error('Error loading games:', error);
        const gamesList = document.querySelector('#games-list');
        if (gamesList) gamesList.innerHTML = `<p class="text-center text-accent col-span-full">Impossible de charger les jeux. Veuillez réessayer plus tard.</p>`;
        throw error;
    }
}

async function loadConsoles() {
    console.log("Loading consoles...");
    try {
        const response = await fetch(`${SITE_URL}/api?action=getConsoles`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const consolesData = await response.json();
        if (!Array.isArray(consolesData)) {
            throw new Error("Invalid consoles data format");
        }
        consoles = consolesData;
        displayConsoleButtons();
        populateConsoleFilter();

        return consoles;

    } catch (error) {
        console.error('Error loading consoles:', error);
        const consoleSelector = document.querySelector('.console-selector');
        if (consoleSelector) consoleSelector.innerHTML = '<p class="text-center text-accent">Erreur de chargement des consoles.</p>';
        throw error;
    }
}

// --- Gestion lancement direct ---
function handleDirectGameLaunch() {
    const urlParams = new URLSearchParams(window.location.search);
    const gameSlugFromUrl = urlParams.get('jeu');
    currentGameSlug = gameSlugFromUrl;

    if (gameSlugFromUrl) {
        console.log(`Attempting direct launch for slug: ${gameSlugFromUrl}`);
        const gameToStart = Array.isArray(currentGames)
            ? currentGames.find(game => game.title.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '') === gameSlugFromUrl)
            : null;

        if (gameToStart) {
            setTimeout(() => {
                const romPath = gameToStart.rom_path.startsWith('/') ? SITE_URL + gameToStart.rom_path : gameToStart.rom_path;
                startGame(gameToStart.console_slug, romPath, gameToStart.title);
            }, 150);
        } else {
            history.replaceState(null, "", window.location.pathname);
            currentGameSlug = null;
        }
        if (currentGameSlug) {
            updateGameCardState(currentGameSlug);
        }
    }

    // On s'assure que handleNetplayUrlParams est appelé après que tout soit stable
    setTimeout(handleNetplayUrlParams, 500);

    // AUTO-CONNECT LOBBY SOCKET REMOVED (Standard Server has no Lobby)
}

function handleNetplayUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const room = params.get('room');
    const jeu = params.get('jeu');

    if (room && jeu && !window.EJS_emulator) {
        console.log(`[Netplay] Auto-join room ${room} pour le jeu ${jeu}`);
        // On attend un peu que les jeux soient chargés
        const checkGamesInterval = setInterval(() => {
            if (currentGames.length > 0) {
                clearInterval(checkGamesInterval);
                const gameToStart = currentGames.find(game => game.title.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '') === jeu);
                if (gameToStart) {
                    const romPath = gameToStart.rom_path.startsWith('/') ? SITE_URL + gameToStart.rom_path : gameToStart.rom_path;
                    console.log(`[Netplay] Lancement du jeu ${gameToStart.title} en mode Room: ${room}`);
                    netplayRoomId = room;
                    startGame(gameToStart.console_slug, romPath, gameToStart.title);
                }
            }
        }, 200);
    }
}

// --- Mise à jour état carte jeu ---
function updateGameCardState(gameId) {
    const game = currentGames.find(g => g.id === gameId);
    if (!game) return;

    const gameCard = document.querySelector(`.game-card[data-game-id="${gameId}"]`);
    if (!gameCard) return;

    const favoriteButton = gameCard.querySelector('.favorite-button');
    if (favoriteButton) {
        checkIfFavorited(gameId, favoriteButton);
    }

    updateAverageRatingDisplay(game);

    fetch(`api?action=getRating&game_id=${gameId}`)
        .then(res => res.ok ? res.json() : Promise.reject(new Error(`Failed fetch rating for ${gameId}`)))
        .then(userRating => {
            const ratingContainer = gameCard.querySelector(`#rating-container-${gameId}`);
            if (ratingContainer) {
                ratingContainer.innerHTML = '';
                ratingContainer.appendChild(createRatingElement(gameId, userRating));
            }
        })
        .catch(error => console.error(error.message || `Error fetching user rating for game ${gameId}:`, error));
}


// --- Fonctions d'affichage ---
function displayConsoleButtons() {
    const consoleSelector = document.querySelector('.console-selector');
    if (!consoleSelector) return;
    consoleSelector.innerHTML = '';

    const allButton = document.createElement('button');
    allButton.className = 'glass active px-6 py-2 transition-all duration-300 transform hover:scale-105';
    allButton.style.borderRadius = '50px';
    allButton.innerHTML = `<i class="fas fa-th-large" style="font-size: 2rem; color: white;"></i>`;
    allButton.addEventListener('click', () => {
        document.querySelectorAll('.console-selector button').forEach(b => b.classList.remove('active', 'btn-neon'));
        allButton.classList.add('active', 'btn-neon');
        displayGames(currentGames);
        currentGames.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
    consoleSelector.appendChild(allButton);

    consoles.forEach(consoleData => {
        const button = document.createElement('button');
        button.className = 'glass px-4 py-2 flex items-center gap-2 transition-all duration-300 transform hover:scale-105';
        button.style.borderRadius = '50px';
        button.dataset.slug = consoleData.slug;

        const logo = document.createElement('img');
        logo.src = getAssetUrl(consoleData.logo);
        logo.alt = consoleData.name;
        logo.className = 'h-14 w-14 object-contain';

        button.appendChild(logo);

        button.addEventListener('click', () => {
            document.querySelectorAll('.console-selector button').forEach(b => b.classList.remove('active', 'btn-neon'));
            button.classList.add('active', 'btn-neon');
            displayGamesByConsole(consoleData.slug);
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

    filteredGames = gamesToDisplay || [];
    currentPage = 1;
    renderCurrentPage();
    renderPaginationControls();
}

function renderCurrentPage() {
    const gamesList = document.querySelector('#games-list');
    if (!gamesList) return;

    gamesList.style.opacity = '0';
    gamesList.style.transition = 'opacity 0.3s ease';

    setTimeout(() => {
        gamesList.innerHTML = '';

        if (!Array.isArray(filteredGames) || filteredGames.length === 0) {
            gamesList.innerHTML = `<p class="pixel-text text-center col-span-full" style="color: var(--text-muted); opacity: 0.5;">AUCUN_JEU_TROUVE</p>`;
            gamesList.style.opacity = '1';
            return;
        }

        const startIndex = (currentPage - 1) * gamesPerPage;
        const endIndex = startIndex + gamesPerPage;
        const gamesToShow = filteredGames.slice(startIndex, endIndex);

        gamesToShow.forEach(game => {
            const gameCard = document.createElement('div');
            gameCard.className = 'game-card glass animate__animated animate__fadeIn';
            gameCard.dataset.gameId = game.id;
            gameCard.innerHTML = `
            <img src="${getAssetUrl(game.cover)}" alt="${game.title}" loading="lazy">
            <div class="game-overlay game-details">
                <div class="pixel-text" style="color: var(--primary); font-size: 0.5rem; margin-bottom: 5px;">${game.console_name}</div>
                <h3 class="game-title" style="font-size: 0.9rem; margin: 0 0 10px 0; font-family: var(--font-heading); font-weight: 800; color: white;">${game.title}</h3>
                
                <div class="flex flex-col gap-2">
                    <div class="flex gap-2">
                        <button class="btn-neon play-button" data-game-id="${game.id}" style="padding: 8px 12px; font-size: 0.6rem; flex: 1;">JOUER</button>
                        <button class="favorite-button glass" data-game-id="${game.id}" style="width: 35px; border-radius: 8px; border: none; cursor: pointer; color: white;">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn-neon preview-button" data-preview-url="${game.preview ? (game.preview.startsWith('/') ? SITE_URL + game.preview : game.preview) : ''}" style="padding: 6px 10px; font-size: 0.5rem; flex: 1; background: var(--secondary); box-shadow: 0 0 10px rgba(0, 242, 255, 0.3);">PREVIEW</button>
                        <a href="${SITE_URL}/game/${game.id}" class="glass flex items-center justify-center" style="width: 35px; border-radius: 8px; color: white; text-decoration: none;">
                            <i class="fas fa-info-circle"></i>
                        </a>
                    </div>
                </div>
            </div>`;

            const playButton = gameCard.querySelector('.play-button');
            if (playButton) {
                playButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const romPath = game.rom_path.startsWith('/') ? SITE_URL + game.rom_path : game.rom_path;
                    startGame(game.console_slug, romPath, game.title);
                });
            }

            const favButton = gameCard.querySelector('.favorite-button');
            if (favButton) {
                favButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    toggleFavorite(game.id, favButton);
                });
            }

            const previewButton = gameCard.querySelector('.preview-button');
            if (previewButton && game.preview) {
                previewButton.addEventListener('click', (event) => {
                    event.stopPropagation();
                    VideoPreview.showPreview(getAssetUrl(game.preview), game.title);
                });
            } else if (previewButton) {
                previewButton.style.opacity = '0.3';
                previewButton.style.cursor = 'not-allowed';
                previewButton.disabled = true;
            }
            gamesList.appendChild(gameCard);
        });

        gamesToShow.forEach(game => updateGameCardState(game.id));
        gamesList.style.opacity = '1';
    }, 150);
}

// --- Pagination ---
function renderPaginationControls() {
    const paginationContainer = document.getElementById('pagination-container');
    if (!paginationContainer) return;

    paginationContainer.innerHTML = '';
    if (!filteredGames || filteredGames.length <= gamesPerPage) return;

    const totalPages = Math.ceil(filteredGames.length / gamesPerPage);

    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => changePage(currentPage - 1);
    paginationContainer.appendChild(prevBtn);

    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    if (endPage - startPage < maxPagesToShow - 1) startPage = Math.max(1, endPage - maxPagesToShow + 1);

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'pagination-btn' + (i === currentPage ? ' active' : '');
        pageBtn.textContent = i;
        pageBtn.onclick = () => changePage(i);
        paginationContainer.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => changePage(currentPage + 1);
    paginationContainer.appendChild(nextBtn);

    const pageInfo = document.createElement('span');
    pageInfo.style.marginLeft = '1rem';
    pageInfo.style.color = 'var(--text-secondary)';
    pageInfo.textContent = `Affichage ${((currentPage - 1) * gamesPerPage) + 1}-${Math.min(currentPage * gamesPerPage, filteredGames.length)} sur ${filteredGames.length}`;
    paginationContainer.appendChild(pageInfo);
}

function changePage(newPage) {
    const totalPages = Math.ceil(filteredGames.length / gamesPerPage);
    if (newPage < 1 || newPage > totalPages || newPage === currentPage) return;
    currentPage = newPage;
    renderCurrentPage();
    renderPaginationControls();
    const gamesList = document.querySelector('#games-list');
    if (gamesList) gamesList.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function displayGamesByConsole(consoleSlug) {
    const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);
    displayGames(filteredGames);
    filteredGames.forEach(game => updateGameCardState(game.id));
    VideoPreview.hidePreview();
}

// --- Event Listeners Filtres/Recherche ---
const consoleFilterSelect = document.getElementById('console-filter');
if (consoleFilterSelect) {
    consoleFilterSelect.addEventListener('change', function () {
        const consoleSlug = this.value;
        document.querySelectorAll('.console-button').forEach(b => b.classList.remove('active'));

        let gamesToUpdate = [];
        if (consoleSlug === "") {
            displayGames(currentGames);
            gamesToUpdate = currentGames;
        } else {
            const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);
            displayGames(filteredGames);
            gamesToUpdate = filteredGames;
        }
        gamesToUpdate.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
}

const gameSearchInput = document.getElementById('game-search');
if (gameSearchInput) {
    gameSearchInput.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase().trim();
        const selectedConsoleSlug = document.getElementById('console-filter').value;
        const filteredGames = currentGames.filter(game => {
            const titleMatch = game.title.toLowerCase().includes(searchTerm);
            const consoleMatch = (selectedConsoleSlug === "" || game.console_slug === selectedConsoleSlug);
            return titleMatch && consoleMatch;
        });
        displayGames(filteredGames);
        filteredGames.forEach(game => updateGameCardState(game.id));
        VideoPreview.hidePreview();
    });
}

// --- Fonctions Favoris ---
async function checkIfFavorited(gameId, button) {
    if (!button) return;
    try {
        const response = await fetch(`${SITE_URL}/api?action=isFavorite&game_id=${gameId}`);
        if (!response.ok) {
            button.classList.remove('favorited');
            button.innerHTML = '<i class="far fa-heart"></i>';
            return;
        }
        const data = await response.json();
        if (data.isFavorite) {
            button.classList.add('favorited');
            button.innerHTML = '<i class="fas fa-heart"></i>';
        } else {
            button.classList.remove('favorited');
            button.innerHTML = '<i class="far fa-heart"></i>';
        }
    } catch (error) {
        button.classList.remove('favorited');
        button.innerHTML = '<i class="far fa-heart"></i>';
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
    } else {
        button.classList.add('favorited');
        button.innerHTML = '<i class="fas fa-heart"></i>';
    }

    try {
        const response = await fetch(`${SITE_URL}/api?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}`
        });
        if (!response.ok) throw new Error(`API error: ${response.status}`);
    } catch (error) {
        if (isCurrentlyFavorited) {
            button.classList.add('favorited');
            button.innerHTML = '<i class="fas fa-heart"></i>';
        } else {
            button.classList.remove('favorited');
            button.innerHTML = '<i class="far fa-heart"></i>';
        }
        alert(`Erreur favoris : ${error.message}`);
    } finally {
        button.disabled = false;
    }
}

// --- Fonctions Notes ---
function createRatingElement(gameId, userRatingData) {
    const ratingDiv = document.createElement('div');
    ratingDiv.className = 'rating';
    ratingDiv.dataset.gameId = gameId;
    const currentRating = (userRatingData && typeof userRatingData.rating === 'number') ? userRatingData.rating : 0;

    for (let i = 5; i >= 1; i--) {
        const input = document.createElement('input');
        input.type = 'radio';
        input.id = `star${i}-${gameId}`;
        input.name = `rating-${gameId}`;
        input.value = i;
        input.checked = (currentRating === i);
        input.classList.add('sr-only');
        const label = document.createElement('label');
        label.htmlFor = `star${i}-${gameId}`;
        label.title = `${i} étoile(s)`;
        label.addEventListener('click', () => handleRatingClick(gameId, i));
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
        averageRatingTextContainer.textContent = `(${count} votes)`;
    } else {
        for (let i = 0; i < 5; i++) averageRatingStarsContainer.innerHTML += '<i class="far fa-star"></i>';
        averageRatingTextContainer.textContent = '(0 votes)';
    }
}
async function handleRatingClick(gameId, rating) {
    try {
        const response = await fetch(`${SITE_URL}/api?action=addRating`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}&rating=${rating}`
        });
        if (!response.ok) throw new Error("Erreur notation");
        const updatedData = await response.json();

        const gameIndex = currentGames.findIndex(g => g.id === gameId);
        if (gameIndex > -1) {
            currentGames[gameIndex].average_rating = updatedData.average_rating;
            currentGames[gameIndex].rating_count = updatedData.rating_count;
            updateAverageRatingDisplay(currentGames[gameIndex]);
        }
    } catch (error) {
        alert(`Erreur lors de la notation.`);
    }
}


// --- GESTION DU JEU ET NETPLAY ---
function closeGame() {
    const gameContainer = document.getElementById('game-container');

    // 1. Arrêt de l'auto-sauvegarde
    if (autoSaveIntervalId) {
        clearInterval(autoSaveIntervalId);
        autoSaveIntervalId = null;
    }

    // 2. COUPURE NETPLAY EXPLICITE (Ajout Critique)
    if (window.EJS_emulator && window.EJS_emulator.netplay && window.EJS_emulator.netplay.socket) {
        console.log("[Close] Déconnexion forcée du socket Netplay");
        window.EJS_emulator.netplay.socket.disconnect();
    }

    // 3. Tentative de fermeture de l'émulateur
    if (window.EJS_emulator) {
        try {
            if (typeof window.EJS_emulator.destroy === 'function') {
                window.EJS_emulator.destroy();
            } else if (typeof window.EJS_emulator.exit === 'function') {
                window.EJS_emulator.exit();
            }
        } catch (e) {
            console.warn("Avertissement fermeture émulateur:", e);
        }
    }

    // 3b. COUPURE AUDIO GARANTIE (correctif du bug "le son continue après avoir quitté")
    rhKillAllAudio();

    // 4. Reset total des variables globales
    window.EJS_emulator = null;
    netplaySocket = null; // Si vous l'utilisiez ailleurs
    // On garde netplayRoomId si on veut permettre un re-join rapide, sinon :
    // netplayRoomId = null; 

    // 5. Nettoyage du DOM
    if (gameContainer) {
        gameContainer.style.display = 'none';
        gameContainer.innerHTML = '';
    }
    document.body.style.overflow = '';

    // 6. Nettoyage URL
    const currentUrlParams = new URLSearchParams(window.location.search);
    if (currentUrlParams.has('jeu') || currentUrlParams.has('room')) {
        // On remet l'URL propre sans recharger la page
        history.pushState(null, "", window.location.pathname);
    }
    currentGameSlug = null;
}

function startGame(core, romUrl, gameName) {
    console.log(`[StartGame] Lancement de: ${gameName} (Core: ${core})`);

    // --- MISE A JOUR GLOBALE ---
    const slug = gameName.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
    currentGameSlug = slug;

    if (window.EJS_emulator) {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('jeu', slug);
        if (netplayRoomId) currentParams.set('room', netplayRoomId);
        if (currentParams.has('host')) currentParams.set('host', '1');
        window.location.href = `${window.location.pathname}?${currentParams.toString()}`;
        return;
    }

    // UI Setup
    const gameContainer = document.getElementById('game-container');
    if (!gameContainer) return;
    gameContainer.innerHTML = '';
    gameContainer.style.display = 'flex';
    gameContainer.style.flexDirection = 'column';
    gameContainer.style.background = '#000';

    // Contrôles
    const controlsBar = document.createElement('div');
    controlsBar.style.cssText = `width: 100%; height: 50px; display: flex; gap: 10px; padding: 5px 10px; background: #1a1a1a; border-bottom: 1px solid #333; flex-shrink: 0; z-index: 10;`;
    const backButton = document.createElement('button');
    backButton.innerHTML = `<i class="fas fa-times"></i> QUITTER`;
    backButton.onclick = (e) => { e.preventDefault(); closeGame(); };
    backButton.style.cssText = `flex: 1; background: #cc0000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;`;
    const netplayButton = document.createElement('button');
    netplayButton.innerHTML = `<i class="fas fa-network-wired"></i> NETPLAY`;
    netplayButton.onclick = (e) => { e.preventDefault(); openNetplayModal(); };
    netplayButton.style.cssText = `flex: 1; background: #0088cc; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;`;
    controlsBar.appendChild(backButton);
    controlsBar.appendChild(netplayButton);
    gameContainer.appendChild(controlsBar);

    const gameDiv = document.createElement('div');
    gameDiv.id = 'game';
    gameDiv.style.cssText = 'flex: 1; width: 100%; position: relative;';
    gameContainer.appendChild(gameDiv);
    document.body.style.overflow = 'hidden';

    // --- CONFIGURATION EMULATORJS ---
    window.EJS_player = '#game';
    window.EJS_gameName = gameName;
    window.EJS_gameUrl = romUrl;
    window.EJS_core = core;
    window.EJS_pathtodata = SITE_URL + '/data/';

    // --- FLAGS NETPLAY ---
    window.EJS_DEBUG_XX = true;
    window.EJS_EXPERIMENTAL_NETPLAY = true;

    // CRITIQUE : startOnLoaded = true force le jeu à démarrer SANS attendre le lobby
    window.EJS_startOnLoaded = true;

    // --- ACTIVATION DU MODULE (OBLIGATOIRE) ---
    // On doit définir ces variables pour que l'objet 'netplay' soit créé par l'émulateur
    window.EJS_netplayServer = NETPLAY_BASE_URL_GLOBAL + "/";
    window.EJS_netplayUrl = NETPLAY_BASE_URL_GLOBAL + "/";

    // --- ID UNIQUE ---
    const cleanId = gameName.toLowerCase().replace(/[^a-z0-9]/g, '');
    let hash = 0;
    for (let i = 0; i < cleanId.length; i++) hash = Math.imul(31, hash) + cleanId.charCodeAt(i) | 0;
    window.EJS_gameID = Math.abs(hash);

    // --- ICE SERVERS ---
    window.EJS_netplayICEServers = [
        { urls: "stun:stun.l.google.com:19302" },
        { urls: "stun:stun1.l.google.com:19302" }
    ];

    window.EJS_onGameStart = function () {
        console.log("[Netplay] Jeu démarré. Attente initialisation core...");
        if (netplayRoomId) {
            // On attend 2 secondes que l'émulateur ait fini sa connexion automatique interne
            // pour ensuite la "voler" ou la réutiliser
            setTimeout(connectToNetplay, 2000);
        }
    };

    setTimeout(() => {
        const script = document.createElement('script');
        script.src = window.EJS_pathtodata + 'loader.js';
        script.async = true;
        document.body.appendChild(script);
    }, 50);
}
// --- Navigation Back ---
window.addEventListener('popstate', function (event) {
    const gameContainer = document.getElementById('game-container');
    const isGameOpen = gameContainer && gameContainer.style.display !== 'none';
    const urlParams = new URLSearchParams(window.location.search);

    if (isGameOpen && !urlParams.has('jeu')) {
        closeGame();
    }
});

// --- Initialisation DOM ---
document.addEventListener('DOMContentLoaded', () => {
    const gameCountEl = document.getElementById('stats-game-count');
    const consoleCountEl = document.getElementById('stats-console-count');
    if (gameCountEl) gameCountEl.textContent = '--';
    if (consoleCountEl) consoleCountEl.textContent = '--';

    Promise.all([loadGames(), loadConsoles()])
        .then(([gamesResult, consolesResult]) => {
            updateStatsHeader();

            const gridBtn = document.getElementById('view-grid');
            const listBtn = document.getElementById('view-list');
            const gamesList = document.getElementById('games-list');

            function setViewMode(mode) {
                currentViewMode = mode;
                localStorage.setItem('viewMode', mode);
                if (mode === 'grid') {
                    gridBtn?.classList.add('active');
                    listBtn?.classList.remove('active');
                    gamesList?.classList.remove('view-list');
                } else {
                    listBtn?.classList.add('active');
                    gridBtn?.classList.remove('active');
                    gamesList?.classList.add('view-list');
                }
            }
            gridBtn?.addEventListener('click', () => setViewMode('grid'));
            listBtn?.addEventListener('click', () => setViewMode('list'));
            setViewMode(currentViewMode);

            // Menu Profil
            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if (confirm('Voulez-vous vraiment terminer la session ?')) {
                        try {
                            const response = await fetch(`${SITE_URL}/api?action=logout`);
                            if (response.ok) window.location.href = `${SITE_URL}/login`;
                        } catch (error) { console.error(error); }
                    }
                });
            }
        })
        .catch(error => {
            console.error("Init failed:", error);
            if (gameCountEl) gameCountEl.textContent = 'Erreur';
            if (consoleCountEl) consoleCountEl.textContent = 'Erreur';
        });
});

// --- NETPLAY FUNCTIONS ---
function openNetplayModal() {
    const modal = document.getElementById('netplay-modal');
    if (modal) modal.style.display = 'flex';

    // Pré-remplir le pseudo
    const savedNick = localStorage.getItem('netplay_nickname');
    const nickInput = document.getElementById('netplay-nickname');
    if (savedNick && nickInput) {
        nickInput.value = savedNick;
    }
}

function closeNetplayModal() {
    const modal = document.getElementById('netplay-modal');
    if (modal) modal.style.display = 'none';
    const roomInput = document.getElementById('room-name-input');
    const joinInput = document.getElementById('join-code-input');
    const codeDisplay = document.getElementById('room-code-display');
    if (roomInput) roomInput.value = '';
    if (joinInput) joinInput.value = '';
    if (codeDisplay) codeDisplay.style.display = 'none';
    updateNetplayStatus('');
}

function switchNetplayTab(tab) {
    document.querySelectorAll('.netplay-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.netplay-tab-content').forEach(c => c.classList.remove('active'));

    if (tab === 'host') {
        document.querySelector('.netplay-tab:first-child').classList.add('active');
        document.getElementById('netplay-host-tab').classList.add('active');
    } else {
        document.querySelector('.netplay-tab:last-child').classList.add('active');
        document.getElementById('netplay-join-tab').classList.add('active');
    }
    updateNetplayStatus('');
}

// Dans script.js, remplacez la gestion des sockets dans joinNetplayRoom (et createNetplayRoom)

// Simplified Netplay Logic for Standard Server
function createNetplayRoom() {
    // Génération d'un ID de room aléatoire coté client (Standard EJS Server ne gère pas la création explicite)
    const roomId = Math.random().toString(36).substring(2, 8).toUpperCase();
    netplayRoomId = roomId;

    const codeEl = document.getElementById('generated-code');
    const displayEl = document.getElementById('room-code-display');
    if (codeEl) codeEl.textContent = roomId;
    if (displayEl) displayEl.style.display = 'block';

    updateNetplayStatus(`✅ Code généré: ${roomId}`, 'success');

    // Ajout du bouton de lancement manuel
    const hostTab = document.getElementById('netplay-host-tab');
    let startBtn = document.getElementById('netplay-start-btn');

    if (!startBtn && hostTab) {
        startBtn = document.createElement('button');
        startBtn.id = 'netplay-start-btn';
        startBtn.className = 'btn-neon';
        startBtn.style.marginTop = '15px';
        startBtn.style.width = '100%';
        startBtn.innerHTML = '<i class="fas fa-play"></i> LANCER LA PARTIE';
        startBtn.onclick = () => {
            reloadGameWithNetplay(true);
        };
        hostTab.appendChild(startBtn);
    }
}

function joinNetplayRoom() {
    const codeInput = document.getElementById('join-code-input');
    const code = codeInput ? codeInput.value.trim().toUpperCase() : '';

    if (!code) {
        updateNetplayStatus('⚠️ Veuillez entrer un code', 'warning');
        return;
    }

    netplayRoomId = code;
    updateNetplayStatus(`✅ Code enregistré. Lancement...`, 'success');

    setTimeout(() => {
        closeNetplayModal();
        reloadGameWithNetplay(false);
    }, 1000);
}

// =========================================================
// FONCTIONS UTILITAIRES NETPLAY (À AJOUTER À LA FIN)
// =========================================================

function updateNetplayStatus(message, type = '') {
    const statusEl = document.getElementById('netplay-status');
    if (statusEl) {
        // Debug: Show GameID if available
        const gameIdInfo = window.EJS_gameID ? ` [GameID: ${window.EJS_gameID}]` : '';
        statusEl.textContent = message + gameIdInfo;

        // Reset des classes
        statusEl.className = 'netplay-status';

        // Ajout de la classe spécifique
        if (type) statusEl.classList.add('status-' + type);

        // Styles de secours (au cas où le CSS ne charge pas)
        statusEl.style.marginTop = '10px';
        statusEl.style.textAlign = 'center';
        statusEl.style.fontWeight = 'bold';

        if (type === 'error') statusEl.style.color = '#ff4444'; // Rouge
        else if (type === 'success') statusEl.style.color = '#00C851'; // Vert
        else if (type === 'warning') statusEl.style.color = '#ffbb33'; // Orange
        else if (type === 'info') statusEl.style.color = '#33b5e5'; // Bleu
        else statusEl.style.color = '#ffffff'; // Blanc
    } else {
        console.log('[Netplay Status]', message);
    }
}

// Fonction requise pour redémarrer le jeu avec les paramètres dans l'URL
function reloadGameWithNetplay(isHost = false) {
    // 1. Récupération Slug
    if (!currentGameSlug) {
        const urlParams = new URLSearchParams(window.location.search);
        currentGameSlug = urlParams.get('jeu');
    }

    // 2. Récupération Room
    if (!netplayRoomId) {
        const codeInput = document.getElementById('join-code-input');
        if (codeInput && codeInput.value) netplayRoomId = codeInput.value;
        else {
            const urlParams = new URLSearchParams(window.location.search);
            netplayRoomId = urlParams.get('room');
        }
    }

    // 3. Action
    if (currentGameSlug && netplayRoomId) {
        console.log(`[Netplay] Rechargement... Host:${isHost}, Room:${netplayRoomId}, Jeu:${currentGameSlug}`);

        let newUrl = `${window.location.pathname}?jeu=${currentGameSlug}&room=${netplayRoomId}`;
        if (isHost) newUrl += '&host=1';

        window.location.href = newUrl;
    } else {
        console.error("Impossible de redémarrer. Données manquantes :", { slug: currentGameSlug, room: netplayRoomId });
        alert("Erreur Netplay : Le jeu n'est pas identifié. Essayez de rafraîchir la page et de relancer le jeu.");
    }
}
let connectionAttempts = 0;

function connectToNetplay() {
    console.log("[ConnectToNetplay] Démarrage du hooking...");

    // 1. Attente de l'objet (cette fois il va apparaître car on a mis EJS_netplayServer)
    if (!window.EJS_emulator || !window.EJS_emulator.netplay) {
        if (!window.netplayRetryCount) window.netplayRetryCount = 0;
        window.netplayRetryCount++;
        if (window.netplayRetryCount < 20) {
            console.log(`[Netplay] Module en chargement (${window.netplayRetryCount}/20)...`);
            setTimeout(connectToNetplay, 500);
            return;
        } else {
            alert("Erreur: Le module Netplay refuse de charger. Vérifiez chrome://flags pour HTTP.");
            return;
        }
    }

    const netplay = window.EJS_emulator.netplay;
    console.log("[Netplay] Module trouvé !", netplay);

    // 2. NETTOYAGE (HIJACKING)
    // Si l'émulateur s'est connecté tout seul au "Lobby" général, on le déconnecte
    if (netplay.socket && netplay.socket.connected) {
        console.log("[Netplay] Déconnexion du socket par défaut...");
        netplay.socket.disconnect();
        // On supprime l'instance pour forcer une recréation propre
        netplay.socket = null;
    }

    // 3. IDENTITÉ
    const urlParams = new URLSearchParams(window.location.search);
    const isHost = urlParams.has('host') || document.getElementById('netplay-start-btn') !== null;
    let pseudo = localStorage.getItem('netplay_nickname') || "Player_" + Math.floor(Math.random() * 1000);

    netplay.name = pseudo;
    netplay.playerID = pseudo + "_" + Date.now().toString(36);

    updateNetplayStatus("Connexion à la Room " + netplayRoomId + "...", "info");

    // 4. NOUVELLE CONNEXION CIBLÉE
    netplay.socket = io(NETPLAY_BASE_URL_GLOBAL, {
        reconnection: true,
        transports: ['websocket', 'polling']
    });

    netplay.socket.on('connect', () => {
        console.log(`[Netplay] ✅ SOCKET CONNECTÉ (Custom Room) ! ID: ${netplay.socket.id}`);

        // Clean old listeners
        const events = ['data-message', 'offer', 'answer', 'candidate', 'users-updated'];
        events.forEach(evt => netplay.socket.off(evt));

        // Pontage WebRTC
        netplay.socket.on('offer', (d) => { if (netplay.onOffer) netplay.onOffer(d); });
        netplay.socket.on('answer', (d) => { if (netplay.onAnswer) netplay.onAnswer(d); });
        netplay.socket.on('candidate', (d) => { if (netplay.onCandidate) netplay.onCandidate(d); });
        netplay.socket.on('data-message', (d) => { if (netplay.onDataMessage) netplay.onDataMessage(d); });

        netplay.socket.on('users-updated', (users) => {
            console.log("[Netplay] Joueurs dans la room:", users);
            netplay.players = users;
            const count = Object.keys(users).length;
            if (count > 1) updateNetplayStatus(`Joueurs: ${count}. Synchro en cours...`, "success");
            else updateNetplayStatus("En attente d'un adversaire...", "warning");
        });

        // Join Room
        const extraData = {
            domain: window.location.hostname,
            game_id: window.EJS_gameID,
            room_name: "Room_" + netplayRoomId,
            player_name: netplay.name,
            userid: netplay.playerID,
            sessionid: netplayRoomId
        };
        netplay.players = {};
        netplay.players[netplay.playerID] = extraData;

        netplay.socket.emit("join-room", { extra: extraData }, (err, currentUsers) => {
            if (err) return console.error("Erreur Join:", err);

            console.log("[Netplay] Room rejointe.");
            netplay.players = currentUsers;

            // Démarrage P2P
            try {
                netplay.roomJoined(isHost, "Lan", "", netplayRoomId);
                console.log(`[Netplay] Moteur P2P lancé (Host: ${isHost})`);
            } catch (e) { console.error("Crash roomJoined:", e); }
        });
    });
}