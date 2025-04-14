// script.js (for index.php) -  Most of your existing logic, adapted.
let currentGames = [];
let consoles = [];

   // Fonction pour charger les jeux
   async function loadGames() {
       try {
           const response = await fetch('api.php?action=getGames');
           currentGames = await response.json();
            // Mettre à jour les étoiles de notation moyenne ici
            currentGames.forEach(game => {
                updateAverageRatingDisplay(game);
            });

       } catch (error) {
           console.error('Erreur lors du chargement des jeux:', error);
       }
   }


   // Fonction pour charger les consoles
   async function loadConsoles() {
       try {
           const response = await fetch('api.php?action=getConsoles');
           consoles = await response.json();
           displayConsoleButtons(); // Affiche les boutons après le chargement
           if (consoles.length > 0) {
               displayGamesByConsole(consoles[0].slug); // Affiche les jeux de la première console
           }
       } catch (error) {
           console.error('Erreur lors du chargement des consoles:', error);
       }
   }

   //Fonction pour afficher les boutons de console
    function displayConsoleButtons() {
        const consoleSelector = document.querySelector('.console-selector');
        consoleSelector.innerHTML = ''; // Efface les boutons précédents

        consoles.forEach(console => {
            const button = document.createElement('button');
            button.className = 'console-button';
            button.dataset.slug = console.slug;
            button.onclick = () => {
                document.querySelectorAll('.console-button').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                displayGamesByConsole(console.slug);
            };

            const logo = document.createElement('img');
            logo.src = console.logo;
            logo.alt = console.name + " Logo";
            logo.className = 'console-logo-button'; // Classe pour styliser le logo

            button.appendChild(logo);
            consoleSelector.appendChild(button);
        });

        if (consoles.length > 0) {
            consoleSelector.firstChild.classList.add('active');
        }
    }

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

   // Fonction pour afficher les jeux par console
    function displayGamesByConsole(consoleSlug) {
        const gamesList = document.querySelector('#games-list');
        const filteredGames = currentGames.filter(game => game.console_slug === consoleSlug);

        gamesList.innerHTML = ''; // Efface les jeux précédents

        filteredGames.forEach(game => {
            const gameCard = document.createElement('div');
            gameCard.className = 'game-card';
            gameCard.dataset.gameId = game.id; // Important pour lier la carte au jeu
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
             //Récupérez et affichez la note de l'utilisateur si connecté

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

        setupSlider();
    }

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


   // Fonction pour démarrer le jeu
    function startGame(core, romUrl, gameName) {

       const gameContainer = document.getElementById('game-container');
       gameContainer.innerHTML = ''; // Nettoie le conteneur

       const backButton = document.createElement('button');
       backButton.className = 'fixed top-4 left-4 z-50 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg';
       backButton.innerHTML = '<i class="fas fa-times mr-2"></i>Retour';
       backButton.onclick = () => {
           gameContainer.style.display = 'none';
           gameContainer.innerHTML = ''; // Important: nettoyer aussi à la fermeture
           // Réactiver la scrollbar si nécessaire
           document.body.style.overflow = '';
       };

       gameContainer.appendChild(backButton);

       const gameDiv = document.createElement('div');
       gameDiv.id = 'game';
       gameDiv.className = 'w-full h-full';  // Assurez-vous que le conteneur prend toute la place
       gameContainer.appendChild(gameDiv);

       gameContainer.style.display = 'block';
       //Désactiver le scroll de la page principale
       document.body.style.overflow = 'hidden';

       window.EJS_player = '#game';
       window.EJS_gameName = gameName;
       window.EJS_biosUrl = '/data/bios/scph5501.bin'; // Si vous avez un BIOS
       window.EJS_gameUrl = romUrl;
       window.EJS_core = core;
       window.EJS_language = 'fr'; //language
       window.EJS_pathtodata = '/data/'; // Chemin absolu depuis la racine du site
       window.EJS_startOnLoaded = true;

       const script = document.createElement('script');
       script.src = '/data/loader.js';  // Chemin absolu depuis la racine du site
       document.body.appendChild(script); // Ajouter le script après avoir défini les variables EJS
   }


   //Fonction de gestion du slider
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


   //Initialisation
    loadGames().then(loadConsoles);