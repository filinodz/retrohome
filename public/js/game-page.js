document.addEventListener('DOMContentLoaded', () => {
    // --- Récupération des données initiales (passées via PHP) ---
    // Supposons que PHP ait créé des variables globales ou des attributs data-*
    // Méthode 1: Variables globales (simple mais moins propre)
    // const gameId = window.phpGameId || null;
    // const isLoggedIn = window.phpIsLoggedIn || false;
    // const initialUserRating = window.phpInitialUserRating || null;
    // const averageRating = window.phpAverageRating || 0;
    // const isFavoriteInitial = window.phpIsFavorite || false;

    // Méthode 2: Attributs data-* sur un élément conteneur (plus propre)
    const gameDataElement = document.getElementById('game-data-container'); // Un div à ajouter dans le HTML
    const gameId = gameDataElement ? parseInt(gameDataElement.dataset.gameId) : null;
    const isLoggedIn = gameDataElement ? gameDataElement.dataset.isLoggedIn === 'true' : false;
    const initialUserRating = gameDataElement ? parseInt(gameDataElement.dataset.userRating) || null : null;
    const averageRating = gameDataElement ? parseFloat(gameDataElement.dataset.averageRating) || 0 : 0;
    const isFavoriteInitial = gameDataElement ? gameDataElement.dataset.isFavorite === 'true' : false;

    // --- Sélecteurs d'éléments DOM ---
    const elements = {
        averageRatingValue: document.querySelector('.rating-box div[style*="font-size: 3rem"]'),
        averageRatingCount: document.querySelector('.rating-box .pixel-text'),
        userRatingContainer: document.querySelector('.user-rating-stars'),
        favoriteButton: document.querySelector('.favorite-toggle-button'),
        playButton: document.querySelector('.btn-neon[onclick*="startGame"]')
    };

    // --- Vérification des éléments essentiels ---
    if (!gameId) {
        console.error("Erreur critique: ID du jeu manquant !");
        return;
    }

    // --- Fonctions ---

    /**
     * Affiche les étoiles pour la note moyenne.
     * @param {HTMLElement} container - Le conteneur où insérer les étoiles.
     * @param {number} rating - La note moyenne (0-5).
     */
    function displayAverageRatingStars(container, rating) {
        if (!container) return;
        container.innerHTML = ''; // Vider
        const ratingNum = parseFloat(rating) || 0;
        const fullStars = Math.floor(ratingNum);
        const halfStar = (ratingNum % 1) >= 0.4; // Seuil pour demi-étoile (ajuster si besoin)
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

        for (let i = 0; i < fullStars; i++) container.innerHTML += '<i class="fas fa-star"></i>';
        if (halfStar) container.innerHTML += '<i class="fas fa-star-half-alt"></i>';
        for (let i = 0; i < emptyStars; i++) container.innerHTML += '<i class="far fa-star"></i>';
    }

    /**
     * Génère les étoiles interactives pour la note utilisateur.
     * @param {HTMLElement} container - Le conteneur où insérer les étoiles.
     * @param {number} gameId - L'ID du jeu.
     * @param {number|null} currentUserRating - La note actuelle de l'utilisateur (0 ou null si pas noté).
     */
    function generateUserRatingStars(container, gameId, currentUserRating) {
        if (!container) return;
        container.innerHTML = ''; // Vider
        const currentRatingInt = parseInt(currentUserRating) || 0;
        container.dataset.currentUserRating = currentRatingInt; // Stocker la note actuelle

        for (let i = 5; i >= 1; i--) {
            const inputId = `star${i}-${gameId}`;
            const input = document.createElement('input');
            input.type = 'radio';
            input.id = inputId;
            input.name = `rating-${gameId}`; // Important pour groupe radio
            input.value = i;
            input.checked = (currentRatingInt === i);
            input.classList.add('sr-only'); // Cacher la radio

            const label = document.createElement('label');
            label.htmlFor = inputId;
            label.title = `${i} étoile${i > 1 ? 's' : ''}`;
            // L'icône est gérée par CSS

            label.addEventListener('click', () => handleRatingClick(gameId, i, container));

            container.appendChild(input);
            container.appendChild(label);
        }
    }

    /**
     * Gère le clic sur une étoile utilisateur et appelle l'API.
     * @param {number} gameId - L'ID du jeu.
     * @param {number} rating - La note cliquée.
     * @param {HTMLElement} container - Le conteneur des étoiles.
     */
    async function handleRatingClick(gameId, rating, container) {
        if (!isLoggedIn) {
            alert("Veuillez vous connecter pour noter ce jeu.");
            return;
        }
        const initialRating = parseInt(container.dataset.currentUserRating) || 0;
        // Retour visuel immédiat (optionnel mais agréable)
        container.querySelectorAll('label').forEach((lbl, index) => {
            // Mettre à jour le 'checked' state visuellement (CSS devrait le faire)
            const radio = container.querySelector(`#star${5 - index}-${gameId}`);
            if (radio) radio.checked = (5 - index <= rating);
        });


        // Désactiver pendant l'appel
        container.style.pointerEvents = 'none';
        container.style.opacity = '0.6';

        try {
            const response = await fetch(`${SITE_URL}/api?action=addRating`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}&rating=${rating}`
            });

            if (!response.ok) {
                const data = await response.json().catch(() => { });
                throw new Error(data.error || `Erreur API notation (${response.status})`);
            }

            const result = await response.json(); // Récupère { average_rating, rating_count }

            if (elements.averageRatingValue) {
                const newVal = parseFloat(result.average_rating || 0).toFixed(1);
                elements.averageRatingValue.innerHTML = `${newVal}<span style="font-size: 1rem; opacity: 0.4;">/5</span>`;
            }
            if (elements.averageRatingCount) elements.averageRatingCount.textContent = `${(result.rating_count || 0)} votes reçus`;

            // Marquer explicitement la bonne étoile (si le CSS ne suffit pas)
            const checkedRadio = container.querySelector(`#star${rating}-${gameId}`);
            if (checkedRadio) checkedRadio.checked = true;


        } catch (error) {
            console.error("Erreur notation:", error);
            alert("Erreur lors de l'enregistrement de la note: " + error.message);
            // Revenir à l'état initial des étoiles utilisateur en cas d'erreur
            generateUserRatingStars(container, gameId, initialRating);
        } finally {
            // Réactiver
            container.style.pointerEvents = 'auto';
            container.style.opacity = '1';
        }
    }

    /**
     * Gère le clic sur le bouton Favori et appelle l'API.
     * @param {Event} e - L'événement de clic.
     */
    async function handleFavoriteToggle(e) {
        if (!isLoggedIn) {
            alert("Veuillez vous connecter pour ajouter ce jeu aux favoris.");
            return;
        }
        const button = e.currentTarget;
        const isFavorited = button.classList.contains('favorited');
        const action = isFavorited ? 'removeFavorite' : 'addFavorite';
        const originalIconHTML = button.querySelector('i').outerHTML; // Sauvegarde icône HTML

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; // Indicateur

        try {
            const response = await fetch(`${SITE_URL}/api?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_id=${gameId}`
            });
            if (!response.ok) {
                const data = await response.json().catch(() => { });
                throw new Error(data.error || 'Erreur API favoris');
            }
            // Succès: inverser l'état visuel
            button.classList.toggle('favorited');
            button.title = button.classList.contains('favorited') ? 'Retirer des favoris' : 'Ajouter aux favoris';
            button.innerHTML = '<i class="fa-heart"></i>'; // Remettre l'icône coeur (CSS s'occupe du style plein/vide)

        } catch (error) {
            console.error("Erreur favori:", error);
            alert("Erreur lors de la mise à jour des favoris: " + error.message);
            button.innerHTML = originalIconHTML; // Restaurer icône
        } finally {
            button.disabled = false;
        }
    }

    // --- Gestion Modal Preview ---
    function showPreviewModal() {
        if (!elements.previewContainer || !elements.previewTV || !elements.showPreviewBtn || !window.VideoPreview) return;

        const url = elements.showPreviewBtn.dataset.previewUrl;
        const title = elements.showPreviewBtn.dataset.gameTitle;

        if (url) {
            elements.previewTV.classList.remove('animate__zoomOut');
            elements.previewTV.classList.add('animate__animated', 'animate__zoomIn', 'animate__faster');
            VideoPreview.showPreview(url, title); // Utilise le module externe
            elements.previewContainer.style.display = 'flex'; // Assurer affichage si géré par style
            elements.previewContainer.classList.remove('hidden'); // Assurer affichage si géré par classe
        } else {
            console.warn("Aucune URL de preview trouvée pour ce jeu.");
            // Optionnel: afficher un message à l'utilisateur
        }
    }

    function hidePreviewModal() {
        if (!elements.previewContainer || !elements.previewTV || !window.VideoPreview) return;

        elements.previewTV.classList.remove('animate__zoomIn');
        elements.previewTV.classList.add('animate__animated', 'animate__zoomOut', 'animate__faster');
        elements.previewTV.addEventListener('animationend', () => {
            VideoPreview.hidePreview(); // Cache après l'animation (change display:none)
            elements.previewTV.classList.remove('animate__animated', 'animate__zoomOut', 'animate__faster');
        }, { once: true });
    }

    // --- Initialisation et Écouteurs ---

    // Affichage initial non nécessaire si PHP le fait déjà

    // Générer étoiles utilisateur si connecté
    if (isLoggedIn && elements.userRatingContainer) {
        generateUserRatingStars(elements.userRatingContainer, gameId, initialUserRating);
    }

    // Écouteur bouton favori si connecté
    if (isLoggedIn && elements.favoriteButton) {
        // Définir état initial basé sur PHP
        if (isFavoriteInitial) {
            elements.favoriteButton.classList.add('favorited');
            elements.favoriteButton.title = 'Retirer des favoris';
        } else {
            elements.favoriteButton.classList.remove('favorited');
            elements.favoriteButton.title = 'Ajouter aux favoris';
        }
        elements.favoriteButton.addEventListener('click', handleFavoriteToggle);
    } else if (elements.favoriteButton) {
        // Optionnel : Cacher ou désactiver le bouton favori si non connecté
        elements.favoriteButton.style.display = 'none'; // Ou ajouter une classe pour le cacher
    }


    // Écouteurs pour le modal Preview
    if (elements.showPreviewBtn) {
        elements.showPreviewBtn.addEventListener('click', showPreviewModal);
    }
    if (elements.closePreviewBtn) {
        elements.closePreviewBtn.addEventListener('click', hidePreviewModal);
    }
    if (elements.previewContainer) {
        // Fermer si clic extérieur
        elements.previewContainer.addEventListener('click', (event) => {
            if (event.target === elements.previewContainer) hidePreviewModal();
        });
    }
    // Fermer avec Echap
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && elements.previewContainer && elements.previewContainer.style.display !== 'none' && !elements.previewContainer.classList.contains('hidden')) {
            hidePreviewModal();
        }
    });

    // Note: La fonction startGame() est appelée directement depuis l'attribut onclick du bouton Jouer dans le HTML/PHP.
    // Si tu voulais la déplacer ici, il faudrait récupérer les paramètres (core, romUrl, gameName)
    // depuis des attributs data-* sur le bouton play et les passer à la fonction.

}); // Fin DOMContentLoaded