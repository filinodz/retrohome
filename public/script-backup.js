// script.js
let currentGames = [];
let consoles = [];
let currentGameSlug = null; // Variable pour stocker le slug du jeu courant (pour le lien direct)

// Chargement initial (modifié pour le lien direct)
async function loadGames() {
    try {
        const response = await fetch('api.php?action=getGames');
        currentGames = await response.json();
        displayGames(currentGames);
         currentGames.forEach(game => {
                updateAverageRatingDisplay(game);
         });

        // Vérifie si un jeu est spécifié dans l'URL
        const urlParams = new URLSearchParams(window.location.search);
        currentGameSlug = urlParams.get('jeu');

        if (currentGameSlug) {
            // Trouve le jeu correspondant et le démarre
            const gameToStart = currentGames.find(game => game.title.toLowerCase().replace(/\s+/g, '-') === currentGameSlug);
            if (gameToStart) {
                startGame(gameToStart.console_slug, gameToStart.rom_path, gameToStart.title);
            }
        }


    } catch (error) {
        console.error('Erreur lors du chargement des jeux:', error);
    }
}
// Load consoles (ajouté populateConsoleFilter)
async function loadConsoles() {
    try {
        const response = await fetch('api.php?action=getConsoles');
        consoles = await response.json();
        displayConsoleButtons();
        populateConsoleFilter(); // Remplit le selecteur de consoles

        // Affiche TOUS les jeux par défaut.
        displayGames(currentGames);


    } catch (error) {
        console.error('Erreur lors du chargement des consoles:', error);
    }
}

//Afficher les boutons
function displayConsoleButtons() {
    const consoleSelector = document.querySelector('.console-selector');
    consoleSelector.innerHTML = '';

    consoles.forEach(console => {
        const button = document.createElement('button');
        button.className = 'console-button';
        button.dataset.slug = console.slug;

        const logo = document.createElement('img');
        logo.src = console.logo;
        logo.alt = console.name + " Logo";
        logo.className = 'console-logo-button';
        button.appendChild(logo);
        button.addEventListener('click', () => {
             // Désactive le bouton de console précédemment actif
            document.querySelectorAll('.console-button').forEach(b => b.classList.remove('active'));
             // Active le bouton de console actuel
            button.classList.add('active');
              // Filtre les jeux par console sélectionnée
            displayGamesByConsole(console.slug);
            VideoPreview.hidePreview(); // Ferme la prévisualisation
        });
        consoleSelector.appendChild(button);
    });
}
//Fonction pour remplir le filtre des consoles
function populateConsoleFilter() {
    const consoleFilter = document.getElementById('console-filter');
    consoles.forEach(console => {
        const option = document.createElement('option');
        option.value = console.slug;
        option.textContent = console.name;
        consoleFilter.appendChild(option);
    });
}

//Afficher les jeux
function displayGames(games) {
    const gamesList = document.querySelector('#games-list');
    gamesList.innerHTML = ''; // Efface

    games.forEach(game => {
        const gameCard = document.createElement('div');
        gameCard.className = 'game-card';
        gameCard.dataset.gameId = game.id;

        gameCard.innerHTML = `
            <div class="console-header">
                <img src="${game.console_logo}" alt="${game.console_name}" class="console-logo">
            </div>
            <img src="${game.cover}" alt="${game.title}" class="game-cover">
            <div class="game-details">
                <h3 class="text-lg font-bold mb-2">${game.title}</h3>
                <p class="text-sm text-gray-400 mb-4">${game.description}</p>
                <div class="game-actions">
                    <span class="year text-sm text-cyan-400">${game.year}</span>
                    <button class="play-button" onclick="startGame('${game.console_slug}', '${game.rom_path}', '${game.title}')">
                        <i class="fas fa-play mr-2"></i>Jouer
                    </button>
                </div>
                 <div id="rating-container-${game.id}"></div>
                 <button class="preview-button" data-preview-url="${game.preview}">
                    <i class="fas fa-tv"></i> Preview
                 </button>
            </div>

        `;
          //Ajout du bouton favoris
            const favoriteButton = document.createElement('button');
            favoriteButton.className = 'favorite-button'; //Classe de base
            favoriteButton.innerHTML = '<i class="fas fa-heart"></i>';
            favoriteButton.addEventListener('click', (event) => {
               event.stopPropagation(); // Important! Empeche le click de se propager
                toggleFavorite(game.id, favoriteButton);
            });
             gameCard.querySelector('.console-header').appendChild(favoriteButton); // Ajout direct
        gamesList.appendChild(gameCard);
          fetch(`api.php?action=getRating&game_id=${game.id}`)
                .then(response => response.json())
                .then(userRating => {
                     const ratingContainer = document.getElementById(`rating-container-${game.id}`);
                     ratingContainer.appendChild(createRatingElement(game.id, userRating));
                });
                 // Mettre à jour l'affichage de la note moyenne
            updateAverageRatingDisplay(game);
            // Vérifiez si le jeu est un favori et mettez à jour le bouton
            checkIfFavorited(game.id, favoriteButton);
    });
     // Evenements Preview
     attachPreviewButtonEvents(); // Utilise la fonction séparée
     setupSlider(); // Important après l'ajout des cartes

}

function attachPreviewButtonEvents() {
    document.querySelectorAll('.preview-button').forEach(button => {
        button.removeEventListener('click', previewButtonClickHandler); // Supprime les anciens
        button.addEventListener('click', previewButtonClickHandler);
    });
}

// Gestionnaire d'événements pour les boutons de prévisualisation (pour éviter les répétitions)
function previewButtonClickHandler(event) {
     event.stopPropagation();
    const button = event.currentTarget; // Utilise currentTarget
    const previewUrl = button.dataset.previewUrl;
    const gameTitle = button.closest('.game-card').querySelector('h3').textContent;

     //Si une prévisualisation est déjà ouverte pour le MÊME jeu, ne rien faire
     if (VideoPreview.currentPreviewUrl === previewUrl) {
        return;
    }

    VideoPreview.showPreview(previewUrl, gameTitle);
}

//Filtrage
function displayGamesByConsole(consoleSlug) {
    const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);
    displayGames(filteredGames);
    VideoPreview.hidePreview(); // Ferme la prévisualisation
}
//Change la console de jeux
document.getElementById('console-filter').addEventListener('change', function() {
    const consoleSlug = this.value;
    if (consoleSlug === "") {
        displayGames(currentGames); // Montre tous
    } else {
        displayGamesByConsole(consoleSlug);
    }
    VideoPreview.hidePreview();
});
//Filtre en recherche de titre de jeux
document.getElementById('game-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const filteredGames = currentGames.filter(game =>
        game.title.toLowerCase().includes(searchTerm)
    );
    displayGames(filteredGames);
    VideoPreview.hidePreview(); // Ferme la preview aussi
});

//Gestion des favoris
async function checkIfFavorited(gameId, button) {

        try {
            const favorites = await (await fetch('api.php?action=getFavorites')).json();
            const isFavorited = favorites.some(game => game.id === gameId); // Utilisez some()

            if (isFavorited) {
                button.classList.remove('favorite-button');
                button.classList.add('favorited-button'); // Change la classe pour le style
            } else {
                button.classList.remove('favorited-button');
                button.classList.add('favorite-button');
            }
        } catch (error) {
            console.error("Erreur lors de la vérification des favoris:", error);
        }
}
//Fonction Note
 function createRatingElement(gameId, userRating) {
        const ratingDiv = document.createElement('div');
        ratingDiv.className = 'rating';
        ratingDiv.dataset.gameId = gameId;

        for (let i = 5; i >= 1; i--) {
            const input = document.createElement('input');
            input.type = 'radio';
            input.id = `star${i}-${gameId}`;
            input.name = `rating-${gameId}`;
            input.value = i;
            if (userRating && userRating.rating == i) {
                input.checked = true;
            }
            input.addEventListener('click', () => handleRatingClick(gameId, i));

            const label = document.createElement('label');
            label.htmlFor = `star${i}-${gameId}`;

            ratingDiv.appendChild(input);
            ratingDiv.appendChild(label);
        }
        return ratingDiv;
    }
   function updateAverageRatingDisplay(game) {
        const gameCard = document.querySelector(`.game-card[data-game-id="${game.id}"]`);

        if (gameCard) {
           const averageRatingDiv = gameCard.querySelector('.average-rating');
            if(averageRatingDiv){
                averageRatingDiv.innerHTML = `(${game.rating_count})`;
                const starsDiv = gameCard.querySelector('.average-rating-stars');
                if(starsDiv){
                    starsDiv.innerHTML = ''; // Clear previous stars
                    const fullStars = Math.floor(game.average_rating);
                    for(let i = 0; i < fullStars; i++){
                       const star = document.createElement('div');
                        star.className = 'average-rating-star';
                        starsDiv.appendChild(star);
                    }
                }
            }
        }
    }
    //Fonction Click Rating
   async function handleRatingClick(gameId, rating) {
        const response = await fetch('api.php?action=addRating', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}&rating=${rating}`
        });

        if (response.ok) {
            // Mettre à jour la note moyenne localement (pour éviter un appel API)
            const game = currentGames.find(g => g.id === gameId);
             if(game){
                //Recharger les jeux pour mettre à jour la note moyenne.
                //C'est pas optimal, il faudrait recalculer localement.
                loadGames().then(() => {
                    displayGamesByConsole(game.console_slug);
                });

             }


        } else {
            const data = await response.json();
            alert(data.error || 'Erreur lors de la notation.');
        }
    }

    //Fonction toggle Favoris
    async function toggleFavorite(gameId, button) {
        // Déterminez l'action en fonction de la classe actuelle du bouton
       let action = button.classList.contains('favorited-button') ? 'removeFavorite' : 'addFavorite';

        const response = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `game_id=${gameId}`
        });
        if(response.ok){
            //Inversez l'état du bouton
           button.classList.toggle('favorite-button');
           button.classList.toggle('favorited-button');
        } else {
            const data = await response.json();
            alert(data.error || "Erreur lors de la gestion des favoris.");
        }

    }

// Démarrer le jeu (modifié pour le plein écran et la sauvegarde)
function startGame(core, romUrl, gameName) {
    const gameContainer = document.getElementById('game-container');
    gameContainer.innerHTML = ''; // Nettoie le conteneur
    gameContainer.style.display = 'block';

    // Bouton de retour
    const backButton = document.createElement('button');
    backButton.className = 'back-button';
    backButton.innerHTML = '<i class="fas fa-times mr-2"></i> Retour';
    backButton.onclick = () => { // Fonction fléchée pour garder le contexte
        gameContainer.style.display = 'none';
        gameContainer.innerHTML = '';
        document.body.style.overflow = '';

        if (window.EJS_emulator) {
            window.EJS_emulator.pause();
            window.EJS_emulator.exit();
        }
         history.pushState(null, "", `/`); //Modifie l'URL, retirer le parametre
    };
    gameContainer.appendChild(backButton);

    const gameDiv = document.createElement('div');
    gameDiv.id = 'game';
    gameDiv.style.width = '100%';
    gameDiv.style.height = '100%';
    gameContainer.appendChild(gameDiv);

    document.body.style.overflow = 'hidden';

     //Adaptez le slug du jeu pour l'URL
     const gameSlug = gameName.toLowerCase().replace(/\s+/g, '-'); // Remplace les espaces par des tirets
     history.pushState({game: gameSlug}, "", `?jeu=${gameSlug}`); //Modifie l'URL


    // Génère une clé de sauvegarde UNIQUE
    const saveKey = `save_${core}_${romUrl.replace(/[^a-zA-Z0-9]/g, '_')}`;


    window.EJS_player = '#game';
    window.EJS_gameName = gameName;
    window.EJS_biosUrl = '/data/bios/scph5501.bin';
    window.EJS_gameUrl = romUrl;
    window.EJS_core = core;
    window.EJS_language = 'fr';
    window.EJS_pathtodata = '/data/';
    window.EJS_startOnLoaded = false; // Important: on le démarre manuellement
    window.EJS_Buttons = {
        loadState: true,
        saveState: true
    };

    // Sauvegarde automatique (CORRIGÉ)
    window.EJS_onSaveState = function(nes) {
        localStorage.setItem(saveKey, nes);
    };

     // Chargement de la sauvegarde (CORRIGÉ, dans EJS_onGameStart)
    window.EJS_onGameStart = function() {
        const savedState = localStorage.getItem(saveKey);
        if (savedState) {
            window.EJS_emulator.loadState(savedState); // Charge l'état
        }
        window.EJS_emulator.start(); // Démarre l'émulateur
    };


    const script = document.createElement('script');
    script.src = '/data/loader.js';
    document.body.appendChild(script);
}
// Gère la fermeture du jeu si on change l'URL manuellement (navigation arrière/avant)
window.addEventListener('popstate', function(event){
   if(!event.state || !event.state.game){
      // Ferme le jeu et réinitialise
       const gameContainer = document.getElementById('game-container');
       gameContainer.style.display = 'none';
       gameContainer.innerHTML = ''; // Nettoie aussi à la fermeture
       document.body.style.overflow = ''; // Réactive le scroll
   }
});

//Fonction Slider
function setupSlider(){
        const slider = document.querySelector('.games-list');
        const prevButton = document.querySelector('.slider-button.prev');
        const nextButton = document.querySelector('.slider-button.next');

        prevButton.addEventListener('click', () => {
           slider.scrollBy({left: -320, behavior: 'smooth'}); //Fait défiler d'une largeur de carte de jeu
        });
        nextButton.addEventListener('click', () => {
            slider.scrollBy({left: 320, behavior: 'smooth'});
        });
}

// Initialisation
loadGames().then(loadConsoles);