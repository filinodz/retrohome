<?php
require_once 'config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Fonction pour vérifier si un jeu est en favori pour un utilisateur donné
function isGameFavorite($userId, $gameId) {
    global $db;
    // Prépare la requête pour vérifier l'existence
    $stmt = $db->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND game_id = ? LIMIT 1");
    $stmt->execute([$userId, $gameId]);
    // fetchColumn() renvoie la valeur de la première colonne (ici '1') si trouvé, sinon false.
    return $stmt->fetchColumn() !== false;
}
// --- FIN DE LA NOUVELLE FONCTION ---

// ... (le reste de tes fonctions existantes : sendResponse, checkLoggedIn, checkAdmin, getGames, getConsoles, etc.) ...
// Assure-toi que toutes tes fonctions précédentes sont bien ici
// getRandomGame, sendResponse, checkLoggedIn, checkAdmin, getGames, getConsoles, registerUser,
// loginUser, logoutUser, getUserProfile, addFavorite, removeFavorite, getFavorites,
// addRating, getRating, createCollection, getCollections, getCollection, deleteCollection,
// addGameToCollection, removeGameFromCollection, getCollectionGames

// --- DEBUT Partie existante avec fonctions (juste pour contexte) ---
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function checkLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['error' => 'Not authenticated'], 401);
    }
}

function checkAdmin() {
    checkLoggedIn();
    global $db;
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
       sendResponse(['error' => 'Unauthorized'], 403);
    }
}

function getGames() {
    global $db;
     try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("
            SELECT g.*, c.name as console_name, c.logo as console_logo, c.slug as console_slug,
                   AVG(r.rating) as average_rating, COUNT(DISTINCT r.id) as rating_count -- Utiliser COUNT(DISTINCT r.id) ou COUNT(r.rating)
            FROM games g
            JOIN consoles c ON g.console_id = c.id
            LEFT JOIN ratings r ON g.id = r.game_id
            GROUP BY g.id, c.name, c.logo, c.slug -- Inclure toutes les colonnes non agrégées
            ORDER BY c.sort_order, g.sort_order
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
     } catch (PDOException $e) {
        error_log("Database error in getGames: " . $e->getMessage());
        sendResponse(['error' => 'Database error while fetching games'], 500);
        return []; // Retourne un tableau vide en cas d'erreur
     }
}

// --- FONCTION getConsoles MODIFIÉE ---
/**
 * Récupère la liste des consoles qui ont au moins un jeu associé.
 *
 * @return array Liste des consoles avec leurs informations, triées par sort_order.
 */
function getConsoles() {
    global $db;
     try {
        // Assurer que le mode d'erreur est bien défini pour cette connexion
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Préparer la requête SQL
        // Sélectionne toutes les colonnes (c.*) de la table consoles (alias c)
        // SEULEMENT SI il existe (WHERE EXISTS) au moins une ligne dans la table games (alias g)
        // où l'id de la console (g.console_id) correspond à l'id de la console actuelle (c.id).
        // Trie les résultats par la colonne sort_order de la table consoles.
        $stmt = $db->query("
            SELECT c.*
            FROM consoles c
            WHERE EXISTS (
                SELECT 1 -- On sélectionne juste une valeur constante (1) pour vérifier l'existence, c'est efficace
                FROM games g
                WHERE g.console_id = c.id
            )
            ORDER BY c.sort_order
        ");

        // Exécuter et récupérer tous les résultats sous forme de tableau associatif
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // En cas d'erreur de base de données, l'enregistrer dans les logs
        error_log("Database error in getConsoles: " . $e->getMessage());
        // Envoyer une réponse d'erreur standardisée au client
        sendResponse(['error' => 'Database error while fetching consoles'], 500);
        // Retourner un tableau vide pour éviter des erreurs PHP plus loin si le code appelant attend un tableau
        return [];
    }
}
// --- FIN DE LA FONCTION getConsoles MODIFIÉE ---

function registerUser($username, $password, $email) {
    global $db;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    // Assurez-vous que la colonne 'role' existe et a une valeur par défaut ou ajoutez-la ici
    $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
    try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt->execute([$username, $hashedPassword, $email]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Violation de contrainte (doublon)
            return false;
        }
        error_log("Registration error: " . $e->getMessage());
        return -1; // Code d'erreur différent pour erreur générale
    }
}

function loginUser($username, $password) {
    global $db;
     try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user'; // Rôle par défaut si NULL
            return true;
        } else {
            return false;
        }
     } catch (PDOException $e) {
         error_log("Login error: " . $e->getMessage());
         return false;
     }
}

function logoutUser(){
    // session_start(); // Assurez-vous que la session est démarrée si pas déjà fait dans config.php
    $_SESSION = array(); // Vide le tableau $_SESSION
    if (ini_get("session.use_cookies")) { // Supprime le cookie de session
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy(); // Détruit la session côté serveur
}


function getUserProfile($userId) {
  global $db;
   try {
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $db->prepare("
          SELECT u.username, u.email, u.created_at,
                 COUNT(DISTINCT f.game_id) AS favorite_count,
                 COUNT(DISTINCT r.game_id) AS rating_count
          FROM users u
          LEFT JOIN favorites f ON u.id = f.user_id
          LEFT JOIN ratings r ON u.id = r.user_id
          WHERE u.id = ?
          GROUP BY u.id, u.username, u.email, u.created_at -- MySQL strict mode requires all non-aggregated columns in GROUP BY
      ");
      $stmt->execute([$userId]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
   } catch (PDOException $e) {
       error_log("Database error in getUserProfile: " . $e->getMessage());
       return null; // Retourne null en cas d'erreur
   }
}

 function addFavorite($userId, $gameId) {
     global $db;
     try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $stmt = $db->prepare("INSERT INTO favorites (user_id, game_id) VALUES (?, ?)");
         $stmt->execute([$userId, $gameId]);
         return true;
     } catch (PDOException $e) {
          if ($e->getCode() == 23000) { // Constraint violation (already favorited)
             return "already_exists";
          }
          error_log("Error adding favorite: ".$e->getMessage());
         return false;
     }
 }

 function removeFavorite($userId, $gameId) {
     global $db;
      try {
          $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $stmt = $db->prepare("DELETE FROM favorites WHERE user_id = ? AND game_id = ?");
         $stmt->execute([$userId, $gameId]);
         return $stmt->rowCount() > 0; // True if a row was deleted
      } catch (PDOException $e) {
          error_log("Error removing favorite: ".$e->getMessage());
          return false;
      }
 }

 function getFavorites($userId) {
   global $db;
    try {
       $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       $stmt = $db->prepare("
           SELECT g.*, c.name as console_name, c.logo as console_logo, c.slug as console_slug,
                  AVG(r.rating) as average_rating, COUNT(DISTINCT r.id) as rating_count
           FROM games g
           JOIN consoles c ON g.console_id = c.id
           JOIN favorites f ON g.id = f.game_id
           LEFT JOIN ratings r ON g.id = r.game_id
           WHERE f.user_id = ?
           GROUP BY g.id, c.name, c.logo, c.slug -- Ajoutez toutes les colonnes de g et c non agrégées
           ORDER BY g.title
       ");
       $stmt->execute([$userId]);
       return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getFavorites: " . $e->getMessage());
        return [];
    }
}
 function addRating($userId, $gameId, $rating) {
    global $db;
     try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         // Utilise INSERT ... ON DUPLICATE KEY UPDATE pour simplifier
         // Assurez-vous qu'il y a une contrainte UNIQUE sur (user_id, game_id) dans la table ratings
         $stmt = $db->prepare("
             INSERT INTO ratings (user_id, game_id, rating)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating)
         ");
         $stmt->execute([$userId, $gameId, $rating]);

         // Après la mise à jour, récupérer la nouvelle moyenne et le compte
         $avgStmt = $db->prepare("SELECT AVG(rating) as average_rating, COUNT(rating) as rating_count FROM ratings WHERE game_id = ?");
         $avgStmt->execute([$gameId]);
         return $avgStmt->fetch(PDO::FETCH_ASSOC);

     } catch (PDOException $e) {
         error_log("Error adding/updating rating: " . $e->getMessage());
         return false;
     }
 }

 function getRating($userId, $gameId){
     global $db;
      try {
          $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $stmt = $db->prepare("SELECT rating FROM ratings WHERE user_id = ? AND game_id = ?");
         $stmt->execute([$userId, $gameId]);
         // Retourne la note ou null si non trouvée
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result ? $result : null; // Renvoie null explicitement si pas de note
      } catch (PDOException $e) {
          error_log("Error getting rating: " . $e->getMessage());
          return null; // Retourne null en cas d'erreur
      }
 }

 // --- Fonctions Collection (pas de changement majeur ici) ---
function createCollection($userId, $name, $description) {
    global $db;
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Vérifie d'abord si une collection avec ce nom existe déjà pour cet utilisateur
        $checkStmt = $db->prepare("SELECT 1 FROM collections WHERE user_id = ? AND name = ?");
        $checkStmt->execute([$userId, $name]);
        if ($checkStmt->fetch()) {
            return "name_exists"; // Code spécifique pour nom déjà utilisé
        }

        $stmt = $db->prepare("INSERT INTO collections (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $name, $description]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        // Gérer d'autres erreurs potentielles
        error_log("Error creating collection: " . $e->getMessage());
        return false;
    }
}

function getCollections($userId) {
    global $db;
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("SELECT * FROM collections WHERE user_id = ? ORDER BY name");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getCollections: " . $e->getMessage());
        return [];
    }
}

function getCollection($collectionId) {
    global $db;
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("SELECT * FROM collections WHERE id = ?");
        $stmt->execute([$collectionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getCollection: " . $e->getMessage());
        return null;
    }
}

function deleteCollection($userId, $collectionId){
     global $db;
     try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         $db->beginTransaction(); // Commence une transaction

         // Vérifie d'abord si la collection appartient à l'utilisateur
         $checkStmt = $db->prepare("SELECT 1 FROM collections WHERE id = ? AND user_id = ?");
         $checkStmt->execute([$collectionId, $userId]);
         if (!$checkStmt->fetch()) {
             $db->rollBack();
             return false; // N'appartient pas à l'utilisateur ou n'existe pas
         }

         // Supprimer d'abord les jeux de la collection
        $stmtDeleteGames = $db->prepare('DELETE FROM collection_games WHERE collection_id = ?');
        $stmtDeleteGames->execute([$collectionId]);

         // Ensuite, supprimer la collection elle-même
         $stmt = $db->prepare("DELETE FROM collections WHERE id = ?"); // user_id déjà vérifié
         $stmt->execute([$collectionId]);
         $rowCount = $stmt->rowCount();

         $db->commit(); // Valide la transaction
         return $rowCount > 0;
     } catch (PDOException $e) {
         $db->rollBack(); // Annule la transaction en cas d'erreur
         error_log("Error deleting collection: " . $e->getMessage());
         return false;
     }
}

function addGameToCollection($userId, $collectionId, $gameId) {
    global $db;
     try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         // Vérifiez si la collection appartient à l'utilisateur
        $checkStmt = $db->prepare("SELECT 1 FROM collections WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$collectionId, $userId]);
        if (!$checkStmt->fetch()) {
            return "not_owned"; // La collection n'appartient pas à l'utilisateur
        }

        $stmt = $db->prepare("INSERT INTO collection_games (collection_id, game_id) VALUES (?, ?)");
        $stmt->execute([$collectionId, $gameId]);
        return true;
    } catch(PDOException $e){
       if($e->getCode() == 23000){ // Déjà dans la collection
          return "already_exists";
       }
        error_log("Erreur lors de l'ajout du jeu à la collection : ". $e->getMessage());
       return false;
    }
}

function removeGameFromCollection($userId, $collectionId, $gameId) {
    global $db;
    try {
         $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         // Vérifiez si la collection appartient à l'utilisateur AVANT de supprimer
        $checkStmt = $db->prepare("SELECT 1 FROM collections WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$collectionId, $userId]);
        if (!$checkStmt->fetch()) {
            return false; // Collection n'appartient pas à l'utilisateur
        }
        $stmt = $db->prepare("DELETE FROM collection_games WHERE collection_id = ? AND game_id = ?");
        $stmt->execute([$collectionId, $gameId]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e){
        error_log("Erreur lors de la suppression du jeu de la collection : ". $e->getMessage());
       return false;
    }
}

function getCollectionGames($collectionId) {
    global $db;
    try {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Il est bon de vérifier si la collection existe avant de chercher les jeux
        $collectionCheck = $db->prepare("SELECT 1 FROM collections WHERE id = ?");
        $collectionCheck->execute([$collectionId]);
        if(!$collectionCheck->fetch()) {
            return []; // Retourne un tableau vide si la collection n'existe pas
        }

        $stmt = $db->prepare("
            SELECT g.*, c.name as console_name, c.logo as console_logo, c.slug as console_slug
            FROM games g
            JOIN consoles c ON g.console_id = c.id
            JOIN collection_games cg ON g.id = cg.game_id
            WHERE cg.collection_id = ?
            ORDER BY g.title
        ");
        $stmt->execute([$collectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
     } catch (PDOException $e) {
        error_log("Database error in getCollectionGames: " . $e->getMessage());
        return [];
    }
}

// --- FIN Partie existante avec fonctions ---


// Action handling
$action = $_GET['action'] ?? '';
// Pour les actions POST, utiliser $_POST
$postData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gérer le cas où le Content-Type est application/json
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $postData = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        // Sinon, supposer que c'est application/x-www-form-urlencoded ou multipart/form-data
        $postData = $_POST;
    }
}


switch ($action) {
    case 'getGames':
        sendResponse(getGames());
        break;
    case 'getConsoles':
        sendResponse(getConsoles());
        break;
    case 'register':
        $username = $postData['username'] ?? '';
        $password = $postData['password'] ?? '';
        $email = $postData['email'] ?? '';
        if (empty($username) || empty($password) || empty($email)) {
            sendResponse(['error' => 'All fields are required'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             sendResponse(['error' => 'Invalid email format'], 400);
        }
        // Ajouter validation longueur mot de passe, etc. si besoin

        $userId = registerUser($username, $password, $email);

        if ($userId === false) {
             sendResponse(['error' => 'Username or email already used'], 409); // Conflict
        } elseif ($userId === -1) {
             sendResponse(['error' => 'Registration error'], 500); // Internal server error
        } elseif ($userId) {
            // Connexion automatique après inscription réussie
            if (loginUser($username, $password)) {
                 sendResponse(['message' => 'Registration successful and logged in', 'user_id' => $userId, 'username' => $username, 'role' => $_SESSION['role'] ?? 'user']);
            } else {
                 // Normalement ne devrait pas arriver si l'enregistrement a réussi
                 sendResponse(['message' => 'Registration successful but auto-login failed', 'user_id' => $userId], 201); // 201 Created
            }
        } else {
             sendResponse(['error' => 'Unknown registration error'], 500);
        }
        break;

    case 'login':
        $username = $postData['username'] ?? '';
        $password = $postData['password'] ?? '';
         if (empty($username) || empty($password)) {
            sendResponse(['error' => 'Username and password are required'], 400);
         }
        if (loginUser($username, $password)) {
            sendResponse(['message' => 'Login successful', 'username' => $_SESSION['username'], 'role' => $_SESSION['role']]);
        } else {
            sendResponse(['error' => 'Invalid username or password'], 401); // Unauthorized
        }
        break;
     case 'logout':
         // Pas besoin de checkLoggedIn() ici, on peut se déconnecter même si la session a expiré
         logoutUser();
         sendResponse(['message' => 'Logout successful']);
         break;

     case 'getProfile':
         checkLoggedIn();
         $profile = getUserProfile($_SESSION['user_id']);
          if ($profile === null) {
                sendResponse(['error' => 'Error fetching profile data'], 500);
          } else {
                sendResponse($profile);
          }
         break;

     // --- NOUVEAU CASE ---
     case 'isFavorite':
        checkLoggedIn(); // Vérifie si l'utilisateur est connecté
        $gameId = $_GET['game_id'] ?? null; // Récupère l'ID du jeu depuis l'URL

        // Vérifie si game_id est fourni et est un nombre valide
        if (!$gameId || !filter_var($gameId, FILTER_VALIDATE_INT)) {
            sendResponse(['error' => 'Valid game_id is required'], 400);
        }

        // Appelle la fonction pour vérifier le statut de favori
        $isFav = isGameFavorite($_SESSION['user_id'], (int)$gameId);

        // Renvoie la réponse JSON attendue par le JavaScript
        sendResponse(['isFavorite' => $isFav]);
        break;
     // --- FIN NOUVEAU CASE ---

     case 'addFavorite':
         checkLoggedIn();
         $gameId = $postData['game_id'] ?? null;
          if(!$gameId || !filter_var($gameId, FILTER_VALIDATE_INT)){
             sendResponse(['error' => 'Valid game_id is required'], 400);
          }
         $result = addFavorite($_SESSION['user_id'], (int)$gameId);
         if ($result === "already_exists") {
             // Ce n'est pas vraiment une erreur, le jeu EST favori. Renvoyer OK.
             // Ou renvoyer un code spécifique si le front doit réagir différemment.
             sendResponse(['message' => 'Game is already in favorites'], 200); // Ou 409 si besoin explicite
         } elseif ($result) {
             sendResponse(['message' => 'Game added to favorites'], 201); // 201 Created
         } else {
             sendResponse(['error' => 'Error adding favorite'], 500);
         }
         break;

     case 'removeFavorite':
         checkLoggedIn();
         // Pour DELETE, on pourrait aussi utiliser la méthode DELETE et récupérer l'ID de l'URL
         // Mais si on reste en POST :
         $gameId = $postData['game_id'] ?? null;
          if(!$gameId || !filter_var($gameId, FILTER_VALIDATE_INT)){
              sendResponse(['error' => 'Valid game_id is required'], 400);
          }
         if (removeFavorite($_SESSION['user_id'], (int)$gameId)) {
             sendResponse(['message' => 'Game removed from favorites']); // 200 OK ou 204 No Content
         } else {
             // Pourrait être une erreur 500 ou 404 si le favori n'existait pas
             sendResponse(['error' => 'Error removing favorite or favorite not found'], 500);
         }
         break;
     case 'getFavorites':
         checkLoggedIn();
         sendResponse(getFavorites($_SESSION['user_id']));
         break;
     case 'addRating':
         checkLoggedIn();
         $gameId = $postData['game_id'] ?? null;
         $rating = $postData['rating'] ?? null;

         // Validation plus stricte
         if (!filter_var($gameId, FILTER_VALIDATE_INT) || !filter_var($rating, FILTER_VALIDATE_INT)) {
             sendResponse(['error'=> 'Valid game_id and rating are required'], 400);
         }
         $gameId = (int)$gameId;
         $rating = (int)$rating;

         if($rating < 1 || $rating > 5){
            sendResponse(['error' => 'Rating must be between 1 and 5'], 400);
         }

         $updateResult = addRating($_SESSION['user_id'], $gameId, $rating);

         if ($updateResult !== false){
             // Renvoie la nouvelle moyenne et le compte comme demandé par le JS
             sendResponse([
                 'message' => 'Rating added/updated successfully',
                 'average_rating' => $updateResult['average_rating'] ?? 0,
                 'rating_count' => $updateResult['rating_count'] ?? 0
             ]);
         } else{
             sendResponse(['error' => 'Error adding/updating rating'], 500);
         }
         break;
     case 'getRating':
         checkLoggedIn();
         $gameId = $_GET['game_id'] ?? null;
         if(!$gameId || !filter_var($gameId, FILTER_VALIDATE_INT)){
             sendResponse(['error' => 'Valid game_id is required'], 400);
         }
         $userRating = getRating($_SESSION['user_id'], (int)$gameId);
          // Renvoie la note (qui peut être null)
         sendResponse($userRating); // Renverra {"rating": X} ou null (qui devient {} ou null en JSON)
         break;

    // Collection actions
    case 'createCollection':
        checkLoggedIn();
        $name = trim($postData['name'] ?? '');
        $description = trim($postData['description'] ?? '');
        if (empty($name)) {
            sendResponse(['error' => 'Collection name is required'], 400);
        }
        // Limiter la longueur du nom/description si besoin

        $result = createCollection($_SESSION['user_id'], $name, $description);

        if ($result === "name_exists") {
            sendResponse(['error' => 'A collection with this name already exists'], 409); // Conflict
        } elseif ($result) {
            sendResponse(['message' => 'Collection created', 'collection_id' => $result], 201); // 201 Created
        } else {
            sendResponse(['error' => 'Collection creation error'], 500);
        }
        break;

    case 'getCollections':
        checkLoggedIn();
        sendResponse(getCollections($_SESSION['user_id']));
        break;

    case 'getCollection': // Récupère les détails d'UNE collection
        checkLoggedIn();
        $collectionId = $_GET['collection_id'] ?? null;
        if (!$collectionId || !filter_var($collectionId, FILTER_VALIDATE_INT)) {
            sendResponse(['error' => 'Valid collection_id is required'], 400);
        }
        $collection = getCollection((int)$collectionId);
        // Vérifie si la collection existe ET appartient à l'utilisateur
        if (!$collection || $collection['user_id'] != $_SESSION['user_id']) {
            sendResponse(['error' => 'Collection not found or access denied'], 404); // Ou 403 Forbidden
        }
        sendResponse($collection);
        break;

    case 'deleteCollection':
        checkLoggedIn();
        $collectionId = $postData['collection_id'] ?? null; // Souvent envoyé en POST/DELETE
         if(!$collectionId || !filter_var($collectionId, FILTER_VALIDATE_INT)){
             sendResponse(['error' => 'Valid collection_id is required'], 400);
         }
         if(deleteCollection($_SESSION['user_id'], (int)$collectionId)){
             sendResponse(['message' => 'Collection deleted successfully']); // 200 OK ou 204 No Content
         } else {
             // L'erreur peut être que la collection n'existe pas ou n'appartient pas à l'user
             sendResponse(['error' => 'Error deleting collection or collection not found/owned'], 500); // Ou 404/403
         }
         break;

    case 'addGameToCollection':
        checkLoggedIn();
        $collectionId = $postData['collection_id'] ?? null;
        $gameId = $postData['game_id'] ?? null;
        if (!filter_var($collectionId, FILTER_VALIDATE_INT) || !filter_var($gameId, FILTER_VALIDATE_INT)) {
            sendResponse(['error' => 'Valid collection_id and game_id are required'], 400);
        }
        $result = addGameToCollection($_SESSION['user_id'], (int)$collectionId, (int)$gameId);

        if ($result === "already_exists") {
             sendResponse(['message' => 'Game is already in this collection'], 200); // Pas une erreur
        } elseif ($result === "not_owned") {
             sendResponse(['error' => 'You do not own this collection'], 403); // Forbidden
        } elseif($result){
             sendResponse(['message' => 'Game added to collection'], 201); // Created
        } else {
            sendResponse(['error' => 'Error adding game to collection'], 500);
        }
        break;

    case 'removeGameFromCollection':
        checkLoggedIn();
        // Pourrait être une méthode DELETE avec /collections/{cid}/games/{gid}
        // Si on reste en POST:
        $collectionId = $postData['collection_id'] ?? null;
        $gameId = $postData['game_id'] ?? null;
        if (!filter_var($collectionId, FILTER_VALIDATE_INT) || !filter_var($gameId, FILTER_VALIDATE_INT)) {
           sendResponse(['error' => 'Valid collection_id and game_id are required'], 400);
        }
        if (removeGameFromCollection($_SESSION['user_id'], (int)$collectionId, (int)$gameId)) {
            sendResponse(['message' => 'Game removed from collection']); // 200 OK ou 204 No Content
        } else {
            sendResponse(['error' => 'Error removing game, or game/collection not found/owned'], 500); // ou 404/403
        }
        break;

    case 'getCollectionGames': // Récupère les JEUX d'une collection
        checkLoggedIn();  // Pour vérifier que l'utilisateur peut voir cette collection
        $collectionId = $_GET['collection_id'] ?? null;
          if(!$collectionId || !filter_var($collectionId, FILTER_VALIDATE_INT)){
              sendResponse(['error' => 'Valid collection_id is required'], 400);
          }
          // Vérifier si l'utilisateur a le droit de voir les jeux de cette collection
          $collectionInfo = getCollection((int)$collectionId);
          if (!$collectionInfo || $collectionInfo['user_id'] != $_SESSION['user_id']) {
                sendResponse(['error' => 'Collection not found or access denied'], 404); // Ou 403
          }
        sendResponse(getCollectionGames((int)$collectionId));
        break;

    // Action getRandomGame (ajoutée plus haut mais à placer ici logiquement si tu préfères)
    case 'getRandomGame':
        try {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $db->query("
                SELECT
                    g.id, g.title, g.description, g.preview, g.cover,
                    c.logo as console_logo, c.name as console_name, c.slug as console_slug, g.rom_path -- Ajout rom_path et slug
                FROM games g
                JOIN consoles c ON g.console_id = c.id
                ORDER BY RAND()
                LIMIT 1
            ");
            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($game) {
                sendResponse($game); // Utilise sendResponse
            } else {
                sendResponse(['error' => 'No games found'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database error in getRandomGame: " . $e->getMessage());
            sendResponse(['error' => 'Database error'], 500);
        }
        break; // N'oublie pas le break

    default:
        sendResponse(['error' => 'Invalid action requested: ' . htmlspecialchars($action)], 400);
}