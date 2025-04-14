document.addEventListener('DOMContentLoaded', () => {
    loadProfileData();
    loadFavorites();
    loadCollections();

    // Event listeners for modal
    document.getElementById('create-collection-btn').addEventListener('click', showCollectionModal);
    document.getElementById('save-collection-btn').addEventListener('click', createCollection);
    document.getElementById('cancel-collection-btn').addEventListener('click', hideCollectionModal);

});

async function loadProfileData() {
    try {
        const profileData = await (await fetch('api.php?action=getProfile')).json();

        // Mettez à jour les informations du profil
        document.getElementById('profile-username').textContent = profileData.username;
        document.getElementById('profile-email').textContent = profileData.email;
        document.getElementById('profile-created-at').textContent = new Date(profileData.created_at).toLocaleDateString();
        document.getElementById('profile-favorite-count').textContent = profileData.favorite_count;
        document.getElementById('profile-rating-count').textContent = profileData.rating_count;

    } catch (error) {
        console.error('Erreur lors du chargement du profil:', error);
    }
}

async function loadFavorites() {
    const favoritesContainer = document.getElementById('profile-favorites');
    favoritesContainer.innerHTML = ''; //Clear previous favorites.

    try{
        const favoritesData = await (await fetch('api.php?action=getFavorites')).json();
         if(favoritesData.length > 0){
                favoritesData.forEach(game => {
                   const gameCard = document.createElement('div');
                    gameCard.className = 'favorite-game-card';
                    gameCard.innerHTML = `
                       <div class="flex items-center">
                            <img src="${game.cover}" alt="${game.title}" class="w-16 h-16 object-cover mr-4">
                            <div>
                                <h4 class="text-lg font-bold">${game.title}</h4>
                                <p class="text-sm text-gray-400">${game.console_name}</p>
                            </div>
                        </div>
                    `;
                    favoritesContainer.appendChild(gameCard);

                });
            } else {
                favoritesContainer.innerHTML = `<p class="no-favorite-message">Vous n'avez pas encore de jeux favoris.</p>`;
            }
    } catch(error){
        console.error("Erreur lors du chargement des favoris : ", error);
    }
}

function showCollectionModal() {
    document.getElementById('collection-modal').classList.remove('hidden');
}

function hideCollectionModal() {
    document.getElementById('collection-modal').classList.add('hidden');
    // Clear form fields
    document.getElementById('collection-name').value = '';
    document.getElementById('collection-description').value = '';
}

async function createCollection() {
    const name = document.getElementById('collection-name').value.trim();
    const description = document.getElementById('collection-description').value.trim();

    if (!name) {
        alert('Veuillez entrer un nom pour la collection.');
        return;
    }

    const response = await fetch('api.php?action=createCollection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
    });

    if (response.ok) {
        hideCollectionModal();
        loadCollections(); // Reload collections after creation
    } else {
        const data = await response.json();
        alert(data.error || 'Erreur lors de la création de la collection.');
    }
}

async function loadCollections() {
    const container = document.getElementById('collections-container');
    container.innerHTML = ''; // Clear previous collections

    try {
        const collections = await (await fetch('api.php?action=getCollections')).json();
        collections.forEach(collection => {
          const collectionCard = document.createElement('div');
          collectionCard.className = 'collection-card cursor-pointer'; // Ajout de cursor-pointer
          collectionCard.innerHTML = `
              <h3 class="text-xl font-bold mb-2">${collection.name}</h3>
              <p class="text-sm text-gray-400">${collection.description || ''}</p>
              <div class="collection-games-list" data-collection-id="${collection.id}">
                <!-- Games will be loaded here -->
              </div>
              <div class="mt-2 flex justify-end">
                <button class="delete-collection-btn text-red-500 hover:text-red-700" data-collection-id="${collection.id}">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
          `;
            container.appendChild(collectionCard);
            collectionCard.addEventListener('click', (event) => {
               // Prevent event from triggering when delete button is clicked
                if (!event.target.closest('.delete-collection-btn')) {
                   window.location.href = `collection.php?id=${collection.id}`;
                }
            });

            //Ajout du listener pour supprimer
             collectionCard.querySelector('.delete-collection-btn').addEventListener('click', (event) => {
                event.stopPropagation(); // Empêche l'événement de clic de se propager au parent
                deleteCollection(collection.id);
            });

            loadCollectionGames(collection.id); // Load games for this collection

        });
    } catch(error) {
        console.error("Erreur lors du chargement des collections: ", error);
    }
}

async function loadCollectionGames(collectionId) {
    try {
        const games = await (await fetch(`api.php?action=getCollectionGames&collection_id=${collectionId}`)).json();
        const gamesList = document.querySelector(`.collection-games-list[data-collection-id="${collectionId}"]`);
        games.forEach(game => {
           const gameElement = document.createElement('div');
            gameElement.className = "collection-game";
            gameElement.innerHTML = `
               <img src="${game.cover}" alt="${game.title}">
                <span>${game.title} (${game.console_name})</span>
            `;
            gamesList.appendChild(gameElement);

        });

        if(games.length === 0){
            gamesList.innerHTML = `<p class="text-sm text-gray-400 italic">Aucun jeu dans cette collection.</p>`;
        }

    } catch (error) {
        console.error(`Error loading games for collection ${collectionId}:`, error);
    }
}

async function deleteCollection(collectionId) {
   if (!confirm('Êtes-vous sûr de vouloir supprimer cette collection ?')) {
        return; // Stop if the user cancels
    }

    const response = await fetch('api.php?action=deleteCollection', {
       method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `collection_id=${collectionId}`
    });
    if(response.ok){
       loadCollections(); // Recharge les collections
    } else {
        const data = await response.json();
        alert(data.error || 'Erreur lors de la suppression de la collection.');
    }
}