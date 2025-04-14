// Éléments du DOM
const authForm = document.getElementById('auth-form');
const authToggle = document.getElementById('auth-toggle');
const emailInput = authForm.querySelector('input[type="email"]');
const authMessage = document.getElementById('auth-message');
const loading = document.getElementById('loading');
const gamePreview = document.getElementById('gamePreview');
const gameInfo = document.getElementById('gameInfo');
const gameTitle = document.getElementById('gameTitle');
const gameDescription = document.getElementById('gameDescription');
const consoleLogo = document.getElementById('consoleLogo');

let isRegisterMode = false;
let currentGame = null;

// Fonction pour charger un jeu aléatoire
// Fonction pour charger un jeu aléatoire
async function loadRandomGame() {
    try {
        loading.style.display = 'flex';
        gamePreview.classList.add('hidden');
        
        const response = await fetch('api.php?action=getRandomGame');
        const contentType = response.headers.get('content-type');
        
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('La réponse du serveur n\'est pas au format JSON');
        }
        
        const game = await response.json();
        
        if (!game || game.error) {
            throw new Error(game.error || 'Données de jeu invalides');
        }
        
        console.log('Données du jeu reçues:', game); // Pour le debug
        
        // Mise à jour des informations du jeu
        gameTitle.textContent = game.title;
        gameDescription.textContent = game.description;
        consoleLogo.src = game.logo;
        
        // Chargement de la vidéo
        gamePreview.innerHTML = '';
        const source = document.createElement('source');
        source.src = game.preview;
        source.type = 'video/mp4';
        gamePreview.appendChild(source);
        
        gamePreview.load();
        gamePreview.onloadeddata = () => {
            loading.style.display = 'none';
            gamePreview.classList.remove('hidden');
            gamePreview.play().catch(console.error);
        };
        
        gamePreview.onerror = (e) => {
            console.error('Erreur de chargement vidéo:', e);
            loading.style.display = 'none';
        };
        
    } catch (error) {
        console.error('Erreur détaillée:', error);
        loading.style.display = 'none';
        // Afficher un message d'erreur à l'utilisateur si nécessaire
    }
}

// Gestion du hover pour les informations du jeu
const tvScreen = gamePreview.parentElement;
tvScreen.addEventListener('mouseenter', () => {
    if (currentGame) {
        gameInfo.style.transform = 'translateY(0)';
    }
});

tvScreen.addEventListener('mouseleave', () => {
    gameInfo.style.transform = 'translateY(100%)';
});

// Gestion du mode connexion/inscription
authToggle.addEventListener('click', () => {
    isRegisterMode = !isRegisterMode;
    const submitButton = authForm.querySelector('button[type="submit"]');
    
    if (isRegisterMode) {
        emailInput.classList.remove('hidden');
        submitButton.textContent = "S'inscrire";
        authToggle.textContent = "Déjà inscrit ? Se connecter";
    } else {
        emailInput.classList.add('hidden');
        submitButton.textContent = "Se connecter";
        authToggle.textContent = "Pas encore inscrit ? S'inscrire";
    }
    
    authForm.reset();
    authMessage.textContent = '';
});

// Gestion du formulaire d'authentification
authForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const formData = new FormData(authForm);
        const data = Object.fromEntries(formData);
        const action = isRegisterMode ? 'register' : 'login';

        const response = await fetch(`api.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });

        const result = await response.json();

        if (response.ok) {
            window.location.href = 'index.php';
        } else {
            authMessage.textContent = result.error || "Une erreur s'est produite";
            authMessage.className = 'mt-4 text-center text-red-500';
        }
    } catch (error) {
        console.error('Error:', error);
        authMessage.textContent = "Une erreur inattendue s'est produite";
        authMessage.className = 'mt-4 text-center text-red-500';
    }
});

// Chargement initial et rechargement périodique
loadRandomGame();
setInterval(loadRandomGame, 30000);