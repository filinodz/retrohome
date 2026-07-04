<?php
require_once '../config.php'; // Chemin relatif

// --- Initialisation et Sécurité ---
set_time_limit(300); // Augmenter le temps max d'exécution (téléchargements API/Images)
// ini_set('display_errors', 1); // Activer pour le débogage seulement
// error_reporting(E_ALL);     // Activer pour le débogage seulement

$errors = [];
$success_message = ''; // Pour les messages non bloquants
$console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT); // Pré-remplir si erreur
$consoles = []; // Initialiser consoles
$noConsolesConfigured = false; // Initialiser

// Vérification droits admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
try {
    $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
} catch (PDOException $e) {
     error_log("Admin Auto Add - User check error: " . $e->getMessage());
     die("Erreur interne lors de la vérification des permissions.");
}

// --- Récupération des consoles (avec ss_id) ---
try {
    // Récupère seulement les consoles ayant un ss_id défini et valide (> 0)
    $consoles = $db->query("SELECT id, name, ss_id FROM consoles WHERE ss_id IS NOT NULL AND ss_id > 0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($consoles)) {
        $noConsolesConfigured = true;
    }
} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des consoles depuis la base de données.";
    error_log("Admin Auto Add - Error fetching consoles: " . $e->getMessage());
}

// --- Fonctions utilitaires pour l'API ScreenScraper ---

/**
 * Nettoie un nom de fichier pour obtenir un terme de recherche.
 * Basique : enlève extension, tags, parenthèses/crochets et espaces multiples.
 */
function cleanRomFilename($filename) {
    $filename = pathinfo($filename, PATHINFO_FILENAME); // Enlève l'extension
    $filename = preg_replace('/[\{\(\[][^\]\)]*[\)\]\]]/', '', $filename); // Tags (Region, !, etc.)
    $filename = preg_replace('/^\d+\s*-\s*/', '', $filename); // Numéro au début (GoodTools)
    $filename = str_replace(['_', '.'], ' ', $filename); // Remplace _ et . par espace
    $filename = trim(preg_replace('/\s+/', ' ', $filename)); // Espaces multiples et trim
    return $filename;
}

/**
 * Appelle l'API ScreenScraper avec gestion basique des erreurs.
 */
function callScreenScraperAPI($url) {
    // Vérifier si les constantes sont définies
    if (!defined('SCREENSCRAPER_USER') || !defined('SCREENSCRAPER_PASSWORD') || !defined('SCREENSCRAPER_DEV_ID') || !defined('SCREENSCRAPER_DEV_PASSWORD')) {
        return ['error' => "Identifiants ScreenScraper non définis dans config.php", 'http_code' => 500];
    }

    $full_url = $url . (strpos($url, '?') === false ? '?' : '&') // Ajoute ? ou &
                   . 'ssid=' . urlencode(SCREENSCRAPER_USER)
                   . '&sspassword=' . urlencode(SCREENSCRAPER_PASSWORD)
                   . '&devid=' . urlencode(SCREENSCRAPER_DEV_ID)
                   . '&devpassword=' . urlencode(SCREENSCRAPER_DEV_PASSWORD)
                   . '&softname=RetroHomeAdmin&output=json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout en secondes
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suivre les redirections
    curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdmin/1.0 (compatible; PHP cURL)'); // User agent
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Décommenter SEULEMENT si erreur SSL en dev local
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);   // Décommenter SEULEMENT si erreur SSL en dev local

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error' => "Erreur cURL: " . $curl_error, 'http_code' => $http_code];
    }

    // Traitement spécifique pour les endpoints media qui peuvent renvoyer du contenu binaire
    if (strpos($url, 'mediaVideoJeu.php') !== false || strpos($url, 'mediaJeu.php') !== false) {
         if ($http_code == 200) {
             if (strpos($response, 'OK') === 0 && strlen($response) < 10) { // Ex: CRCOK, MD5OK, SHA1OK
                  return ['status' => 'OK_CHECKSUM']; 
             } elseif (strpos($response, 'NOMEDIA') !== false) {
                  return ['error' => 'NOMEDIA', 'http_code' => 404]; 
             } elseif (strpos($response, '<?xml') === 0 || strpos($response, '<html') === 0 || (strlen($response) < 500 && empty(trim($response)))) {
                   return ['error' => "Réponse inattendue de l'API média (non-binaire/vide). Code: $http_code", 'http_code' => $http_code];
             }
              return ['is_media_content' => true, 'http_code' => $http_code, 'content' => $response]; 
         } else {
              return ['error' => "Erreur API média ScreenScraper (Code: $http_code)", 'http_code' => $http_code];
         }
    }

    // --- Traitement normal pour les autres endpoints (JSON attendu) ---
    if ($http_code >= 400) {
         $error_data = json_decode($response, true);
         $error_message = $error_data['message'] ?? $response;
         if (stripos($response, 'maximum threads') !== false) $error_message = "Limite de requêtes ScreenScraper atteinte. Réessayez plus tard.";
         elseif ($http_code === 401 || stripos($response, 'Erreur de login') !== false) $error_message = "Identifiants ScreenScraper invalides ou problème d'authentification API.";
        return ['error' => "Erreur API ScreenScraper (Code: $http_code): " . strip_tags($error_message), 'http_code' => $http_code];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => "Réponse API invalide (JSON): " . json_last_error_msg(), 'http_code' => $http_code];
    }
    if (isset($data['ssuser']['connect']) && $data['ssuser']['connect'] == 0) {
         return ['error' => "Échec de connexion à ScreenScraper (user/pass incorrects ?).", 'http_code' => 401];
    }

    return $data;
}

/**
 * Trouve le meilleur résultat parmi les jeux retournés par ScreenScraper en utilisant la distance de Levenshtein.
 */
/**
 * Trouve le meilleur résultat parmi les jeux retournés par ScreenScraper en utilisant la distance de Levenshtein.
 * RAFFINEMENT : Ajout d'un filtre strict sur les nombres pour éviter les confusions (ex: RE2 vs RE3).
 */
function findBestLevenMatch($games, $searchTerm) {
    if (empty($games)) return null;
    
    $bestMatch = null;
    $bestLeven = -1;
    $searchTermLower = strtolower(trim($searchTerm));
    
    // Extraction des nombres du terme de recherche
    preg_match_all('/\d+/', $searchTermLower, $searchNumbers);
    $searchNumbers = $searchNumbers[0] ?? [];
    
    // Si un seul résultat, on le prend
    if (count($games) === 1) {
        return $games[0];
    }

    foreach ($games as $game) {
        // Collecter tous les noms connus pour ce jeu (toutes régions)
        $names = [];
        foreach ($game['noms'] as $nom) {
             if (!empty($nom['text'])) {
                 $names[] = $nom['text'];
             }
        }
        // Fallback si pas de noms (rare)
        if (empty($names)) $names[] = $game['nom_jeu'] ?? '';

        $localBestDist = -1;
        
        foreach ($names as $name) {
            $nameLower = strtolower(trim($name));

            // --- FILTRE STRICT SUR LES NOMBRES ---
            // Si le terme recherché contient des nombres (ex: "3"), le nom du jeu DOIT les contenir.
            if (!empty($searchNumbers)) {
                preg_match_all('/\d+/', $nameLower, $gameNumbers);
                $gameNumbers = $gameNumbers[0] ?? [];
                
                // Vérifier si tous les nombres recherchés sont présents dans le nom du candidat
                $missingNumbers = array_diff($searchNumbers, $gameNumbers);
                if (!empty($missingNumbers)) {
                    continue; // Ce nom ne contient pas les numéros requis, on l'ignore
                }
            }
            // -------------------------------------

            $dist = levenshtein($searchTermLower, $nameLower);
            if ($dist === 0) {
                // Correspondance exacte trouvée !
                return $game;
            }
            if ($localBestDist === -1 || $dist < $localBestDist) {
                $localBestDist = $dist;
            }
        }

        // Comparer avec le meilleur global SEULEMENT si on a trouvé un nom valide
        if ($localBestDist !== -1) {
            if ($bestLeven === -1 || $localBestDist < $bestLeven) {
                $bestLeven = $localBestDist;
                $bestMatch = $game;
            }
        }
    }

    return $bestMatch ?: $games[0]; // Fallback au premier
}

/**
 * Télécharge un fichier depuis une URL et le sauvegarde.
 * Modifié pour gérer l'appel à mediaVideoJeu.php qui retourne directement le contenu.
 */
function downloadMedia($url, $destination_path) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("DownloadMedia: URL invalide ou vide fournie: " . $url);
        return ['success' => false, 'error' => 'URL invalide'];
    }

    try {
        // Créer le dossier parent si nécessaire
         $dir = dirname($destination_path);
         if (!is_dir($dir)) {
             if (!mkdir($dir, 0775, true)) {
                 error_log("DownloadMedia: Impossible de créer le répertoire: " . $dir);
                 return ['success' => false, 'error' => 'Impossible de créer le répertoire de destination'];
             }
         }
          if (!is_writable($dir)) {
                error_log("DownloadMedia: Répertoire non accessible en écriture: " . $dir);
                return ['success' => false, 'error' => 'Répertoire de destination non accessible en écriture'];
            }

        // Si c'est l'URL de l'API média, on utilise callScreenScraperAPI
        if (strpos($url, 'mediaVideoJeu.php') !== false || strpos($url, 'mediaJeu.php') !== false) {
            $api_response = callScreenScraperAPI($url); // Appelle l'API média

            if (isset($api_response['error'])) {
                if ($api_response['error'] === 'NOMEDIA') {
                    error_log("DownloadMedia: ScreenScraper a répondu 'NOMEDIA' pour l'URL: " . $url);
                    return ['success' => false, 'error' => 'NOMEDIA'];
                } else {
                    error_log("DownloadMedia: Erreur API média ScreenScraper: " . $api_response['error'] . " URL: " . $url);
                    return ['success' => false, 'error' => $api_response['error']];
                }
            } elseif (isset($api_response['is_media_content']) && $api_response['is_media_content']) {
                // Écrire le contenu binaire directement dans le fichier
                if (file_put_contents($destination_path, $api_response['content']) === false) {
                     error_log("DownloadMedia: Impossible d'écrire le contenu média dans: " . $destination_path);
                     return ['success' => false, 'error' => "Impossible d'écrire le fichier média."];
                }
                // Vérifier la taille du fichier écrit
                 if (filesize($destination_path) == 0) {
                     error_log("DownloadMedia: Fichier média écrit mais vide pour $url.");
                     @unlink($destination_path);
                     return ['success' => false, 'error' => 'Fichier média téléchargé vide.'];
                 }
                 return ['success' => true];
            } else {
                 error_log("DownloadMedia: Réponse inattendue de l'API média pour l'URL: " . $url);
                 return ['success' => false, 'error' => 'Réponse inattendue de l\'API média.'];
            }

        } else {
            // --- Téléchargement classique pour les autres URLs (images, etc.) ---
            $ch = curl_init($url);
            $fp = fopen($destination_path, 'wb');
            if (!$fp) {
                error_log("DownloadMedia: Impossible d'ouvrir le fichier en écriture: " . $destination_path);
                curl_close($ch);
                return ['success' => false, 'error' => "Impossible d'ouvrir le fichier destination."];
            }

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdmin/1.0 (compatible; PHP cURL)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Si besoin
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);   // Si besoin

            $success_curl = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            curl_close($ch);
            fclose($fp);

            if (!$success_curl || $http_code >= 400) {
                error_log("DownloadMedia: Échec téléchargement $url. Code: $http_code. cURL Error: $curl_error");
                @unlink($destination_path);
                return ['success' => false, 'error' => "Erreur $http_code lors du téléchargement."];
            }
            if (filesize($destination_path) == 0) {
                 error_log("DownloadMedia: Fichier téléchargé vide pour $url.");
                 @unlink($destination_path);
                 return ['success' => false, 'error' => 'Fichier téléchargé vide.'];
             }

            return ['success' => true];
        }
    } catch (Exception $e) {
        error_log("DownloadMedia: Exception téléchargement $url: " . $e->getMessage());
        @unlink($destination_path);
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// --- Traitement du formulaire POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noConsolesConfigured) { // N'exécute que si des consoles sont configurées

    $console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT);
    $rom_file = $_FILES['rom'] ?? null;

    // Validation initiale
    if (!$console_id) $errors[] = "Veuillez sélectionner une console.";
    if (!$rom_file || $rom_file['error'] !== UPLOAD_ERR_OK) {
        $phpFileUploadErrors = [
             UPLOAD_ERR_INI_SIZE => "Le fichier téléchargé dépasse la directive upload_max_filesize dans php.ini.",
             UPLOAD_ERR_FORM_SIZE => "Le fichier téléchargé dépasse la directive MAX_FILE_SIZE spécifiée dans le formulaire HTML.",
             UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé.",
             UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été téléchargé.",
             UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
             UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
             UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload.",
        ];
        $error_code = $rom_file['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_message = $phpFileUploadErrors[$error_code] ?? "Erreur inconnue lors de l'upload.";
        $errors[] = "Erreur upload ROM (Code: $error_code): $error_message";
    }

    $console = null;
    if ($console_id) {
        try {
            $stmtConsole = $db->prepare("SELECT id, name, slug, ss_id FROM consoles WHERE id = ?");
            $stmtConsole->execute([$console_id]);
            $console = $stmtConsole->fetch(PDO::FETCH_ASSOC);
            if (!$console || empty($console['ss_id'])) $errors[] = "Console invalide ou non configurée pour ScreenScraper (ss_id manquant).";
        } catch (PDOException $e) {
            $errors[] = "Erreur DB lors de la récupération des infos console.";
            error_log("Admin Auto Add - Fetch console error: " . $e->getMessage());
        }
    }

    // --- Appel API ScreenScraper ---
    if (empty($errors) && $console) {
        $searchTerm = cleanRomFilename($rom_file['name']);
        $systemId = $console['ss_id'];
        $consoleSlug = $console['slug']; // Slug de notre BDD

        $searchUrl = 'https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=' . urlencode($searchTerm) . '&systemeid=' . $systemId;
        $searchResult = callScreenScraperAPI($searchUrl);

        if (isset($searchResult['error'])) {
            $errors[] = "Erreur recherche ScreenScraper: " . $searchResult['error'];
        } elseif (empty($searchResult['response']['jeux']) || !isset($searchResult['response']['jeux'][0]['id']) || empty($searchResult['response']['jeux'][0]['id'])) {
            $errors[] = "Aucun jeu trouvé sur ScreenScraper pour '" . htmlspecialchars($searchTerm) . "' sur " . htmlspecialchars($console['name']) .". Vérifiez le nom du fichier ROM ou essayez l'ajout manuel.";
        } else {
            // Jeu trouvé !
            $ssGame = findBestLevenMatch($searchResult['response']['jeux'], $searchTerm);
            $ssGameId = $ssGame['id']; // ** ID du jeu trouvé sur SS **
            $gameData = $ssGame; // Données de base

            // Essayer de récupérer plus d'infos
            $infoUrl = 'https://api.screenscraper.fr/api2/jeuInfos.php?gameid=' . $ssGameId;
            $infoResult = callScreenScraperAPI($infoUrl);

            if (!isset($infoResult['error']) && isset($infoResult['response']['jeu'])) {
                 $gameData = $infoResult['response']['jeu']; // Utiliser données détaillées
                 error_log("Admin Auto Add: Détails récupérés pour SS Game ID " . $ssGameId);
            } elseif (isset($infoResult['error'])) {
                 $errors[] = "INFO: Erreur récupération détails SS: " . $infoResult['error']; // Message non bloquant
                 error_log("Admin Auto Add: Erreur récupération détails SS pour ID " . $ssGameId . ": " . $infoResult['error']);
            } else {
                 $errors[] = "INFO: Impossible de récupérer les détails complets depuis ScreenScraper.";
                 error_log("Admin Auto Add: Réponse inattendue de jeuInfos pour ID " . $ssGameId);
            }

            // --- Extraction des données ---
            $validRegions = ['fr', 'en', 'es', 'pt', 'de', 'it'];
            $postedRegion = $_POST['region'] ?? 'fr';
            $selectedRegion = in_array($postedRegion, $validRegions) ? $postedRegion : 'fr';
            
            // Titre (priorité ss > us > eu > wor > jp > premier trouvé > fallback nom fichier)
            $title = $searchTerm;
            $regions_order_title = ['ss', 'us', 'eu', 'wor', 'jp'];
            foreach($regions_order_title as $region) { foreach ($gameData['noms'] ?? [] as $nom) { if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { $title = $nom['text']; break 2; }}}
            if ($title === $searchTerm && isset($gameData['noms'][0]['text']) && !empty($gameData['noms'][0]['text'])) { $title = $gameData['noms'][0]['text']; }
            $title = trim($title);

            // Description (Priorité user_choice > fr > en)
            $description = ''; 
            $lang_order = [$selectedRegion, 'fr', 'en']; // Priorité à la langue sélectionnée
            $lang_order = array_unique($lang_order); // Éviter doublons (ex: fr, fr, en)

            foreach($lang_order as $lang) { foreach ($gameData['synopsis'] ?? [] as $syn) { if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { $description = $syn['text']; break 2; }}}
            $description = $description ? trim(preg_replace('/\s+/', ' ', $description)) : null;

            // Année
            $year = null; foreach ($gameData['dates'] ?? [] as $date) { if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { $y = (int)$matches[1]; if ($y >= 1950 && $y < ($year ?? PHP_INT_MAX) ) $year = $y; }}

            // Editeur
            $publisher = isset($gameData['editeur']['text']) && !empty($gameData['editeur']['text']) ? trim($gameData['editeur']['text']) : null;

            // Médias (Jaquette et Vidéo)
            $coverUrl = '';
            $coverFormat = '';
            $videoUrl = '';
            $videoFormat = '';

            $mediaTypesCover = ['mixrbv1', 'box-2D', 'box-3D', 'screenshot'];
            $regions = ['wor', 'ss', 'us', 'eu', 'jp'];

            // Recherche Cover
            foreach ($mediaTypesCover as $type) {
                foreach ($regions as $region) {
                    foreach($gameData['medias'] ?? [] as $media) {
                        if (isset($media['type'], $media['region'], $media['url']) && $media['type'] == $type && $media['region'] == $region && !empty($media['url'])) {
                            $coverUrl = $media['url'];
                            $coverFormat = $media['format'] ?? 'png';
                            error_log("Auto Add: Cover trouvée ($type, $region) pour {$title}.");
                            break 3;
                        }
                    }
                }
                if (empty($coverUrl)) {
                    foreach($gameData['medias'] ?? [] as $media) {
                        if (isset($media['type'], $media['url']) && $media['type'] == $type && !empty($media['url'])) {
                            $coverUrl = $media['url'];
                            $coverFormat = $media['format'] ?? 'png';
                            error_log("Auto Add: Cover trouvée ($type, fallback région) pour {$title}.");
                            break 2;
                        }
                    }
                }
                if (!empty($coverUrl)) break;
            }

            // Recherche Vidéo
            foreach($gameData['medias'] ?? [] as $media) {
                if (isset($media['type'], $media['url']) && ($media['type'] == 'video' || $media['type'] == 'video-normalized') && !empty($media['url'])) {
                    $videoUrl = $media['url'];
                    $videoFormat = $media['format'] ?? 'mp4';
                    error_log("Auto Add: Vidéo trouvée dans jeuInfos pour {$title}.");
                    break;
                }
            }

             // --- Création slug et dossiers ---
            $gameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
            if(empty($gameSlug)) $gameSlug = 'game-' . $ssGameId; // Fallback avec ID SS
            $gameDirAbsolute = rtrim(ROMS_PATH, '/') . '/' . $consoleSlug . '/' . $gameSlug . '/';
            $imagesDir = $gameDirAbsolute . 'images/';
            $previewDir = $gameDirAbsolute . 'preview/';
            $gameBaseRelativePath = '/roms/' . $consoleSlug . '/' . $gameSlug . '/'; // Chemin relatif web

             // Vérification doublon jeu
             try {
                 // DEBUG pour l'utilisateur
                 $errors[] = "INFO: Recherche pour '".htmlspecialchars($searchTerm)."' - Jeu identifié: ID ".($ssGame['id'] ?? 'N/A')." ('".htmlspecialchars($title)."')";
                 
                 $stmtCheckGame = $db->prepare("SELECT id FROM games WHERE title = ? AND console_id = ?");
                 $stmtCheckGame->execute([$title, $console_id]);
                 if ($stmtCheckGame->fetch()) {
                     $errors[] = "Un jeu avec le titre '".htmlspecialchars($title)."' existe déjà pour la console '".htmlspecialchars($console['name'])."'.";
                 }
             } catch(PDOException $e) {
                 $errors[] = "Erreur lors de la vérification du doublon de jeu.";
                 error_log("Admin Auto Add - Game check error: " . $e->getMessage());
             }

            // Créer les répertoires
            $real_errors = array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0);
             if (empty($real_errors)) {
                 $directories = [$gameDirAbsolute, $imagesDir, $previewDir];
                 foreach ($directories as $dir) {
                     if (!is_dir($dir)) {
                         error_log("Admin Auto Add: Tentative création dossier: " . $dir); // Log avant création
                         if (!mkdir($dir, 0775, true)) {
                             $errors[] = "Impossible de créer le répertoire : " . $dir . ". Vérifiez les permissions.";
                             if ($dir === $gameDirAbsolute) break; // Arrêter si le dossier principal échoue
                         } else {
                               error_log("Admin Auto Add: Dossier créé avec succès: " . $dir); // Log après succès
                         }
                     } elseif (!is_writable($dir)) {
                         $errors[] = "Le répertoire n'est pas accessible en écriture : " . $dir;
                         error_log("Admin Auto Add: Dossier non écrivable: " . $dir);
                     }
                 }
             }

            // --- Téléchargement & Déplacement ---
            $rom_path_relative = '';
            $cover_path_relative = '';
            $preview_path_relative = '';

            $real_errors = array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0);
            if (empty($real_errors)) { // Continue seulement si pas d'erreur (doublon, dossier)
                // 1. ROM
                $rom_original_name = $rom_file['name'];
                $rom_ext = strtolower(pathinfo($rom_original_name, PATHINFO_EXTENSION));
                $rom_filename = $gameSlug . '.' . $rom_ext; // Nom basé sur slug API
                $rom_destination = $gameDirAbsolute . $rom_filename;
                error_log("Admin Auto Add: Tentative déplacement ROM vers: " . $rom_destination);
                if (!move_uploaded_file($rom_file['tmp_name'], $rom_destination)) {
                    $errors[] = "Erreur technique lors du déplacement de la ROM vers " . $rom_destination . ". Vérifiez les permissions et l'espace disque.";
                     error_log("Admin Auto Add: Échec move_uploaded_file pour ROM. Source: " . $rom_file['tmp_name']);
                } else {
                    $rom_path_relative = $gameBaseRelativePath . $rom_filename;
                     error_log("Admin Auto Add: ROM déplacée avec succès vers: " . $rom_destination);
                }

                // 2. Cover (seulement si ROM OK)
                $real_errors = array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0);
                if (empty($real_errors) && !empty($coverUrl)) {
                    $cover_ext = strtolower($coverFormat) ?: 'png';
                    $cover_filename = $gameSlug . '.' . $cover_ext;
                    $cover_destination = $imagesDir . $cover_filename;
                    error_log("Admin Auto Add: Tentative téléchargement Cover depuis: " . $coverUrl);
                    $downloadResult = downloadMedia($coverUrl, $cover_destination);
                    if ($downloadResult['success']) {
                         $cover_path_relative = $gameBaseRelativePath . 'images/' . $cover_filename;
                         error_log("Admin Auto Add: Cover téléchargée: " . $cover_path_relative);
                    } else {
                         $errors[] = "Échec téléchargement jaquette (" . ($downloadResult['error'] ?? 'Erreur inconnue') . ").";
                         error_log("Admin Auto Add: Échec téléchargement Cover. Erreur: " . ($downloadResult['error'] ?? 'Inconnue'));
                    }
                } elseif(empty($coverUrl)) {
                     $errors[] = "INFO: Aucune jaquette trouvée sur SS.";
                     error_log("Admin Auto Add: Aucune cover trouvée sur SS pour " . $title);
                }

                // 3. Preview (seulement si ROM OK)
                $real_errors = array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0);
                if (empty($real_errors)) {
                    $mediaVideoUrl = $videoUrl ?: 'https://api.screenscraper.fr/api2/mediaVideoJeu.php?systemeid=' . $systemId . '&jeuid=' . $ssGameId . '&media=video';
                    $preview_ext = strtolower($videoFormat) ?: 'mp4';
                    $preview_filename = $gameSlug . '.' . $preview_ext;
                    $preview_destination = $previewDir . $preview_filename;
                    error_log("Admin Auto Add: Tentative DL preview via: " . $mediaVideoUrl);
                    $downloadResult = downloadMedia($mediaVideoUrl, $preview_destination);
                    if ($downloadResult['success']) {
                        $preview_path_relative = $gameBaseRelativePath . 'preview/' . $preview_filename;
                        error_log("Admin Auto Add: Preview vidéo téléchargée avec succès.");
                    } else {
                        if ($downloadResult['error'] == 'NOMEDIA') {
                            $errors[] = "INFO: Aucune preview vidéo disponible sur SS.";
                            error_log("Admin Auto Add: Preview NOMEDIA pour jeu ID " . $ssGameId);
                        } else {
                            // Ne pas ajouter comme erreur bloquante, mais comme info
                            $errors[] = "INFO: Échec téléchargement preview vidéo (" . ($downloadResult['error'] ?? 'Erreur inconnue') . ").";
                            error_log("Admin Auto Add: Échec DL preview pour jeu ID " . $ssGameId . ". Erreur: " . ($downloadResult['error'] ?? 'Inconnue'));
                        }
                    }
                }

            } // Fin if (empty($real_errors)) pour téléchargement

            // --- Insertion Finale en BDD ---
            // Vérifie s'il y a des erreurs réelles (pas juste INFO:)
            $real_errors = array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0);
            if (empty($real_errors)) {
                 try {
                    // --- La requête SQL est ici ---
                    $stmtInsert = $db->prepare("
                        INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path, sort_order)
                        VALUES (:console_id, :title, :description, :year, :publisher, :cover, :preview, :rom_path, :sort_order)
                    ");
                    // --- Fin Requête SQL ---

                    $executionSuccess = $stmtInsert->execute([
                        ':console_id' => $console_id,
                        ':title' => $title,
                        ':description' => $description,
                        ':year' => $year,
                        ':publisher' => $publisher,
                        ':cover' => $cover_path_relative ?: null,
                        ':preview' => $preview_path_relative ?: null,
                        ':rom_path' => $rom_path_relative,
                        ':sort_order' => 0 // Ajout auto -> sort_order 0 par défaut
                    ]);

                    if($executionSuccess) {
                        $info_messages_count = count(array_filter($errors, fn($e)=> strpos($e, 'INFO:') === 0));
                        $flash_message = 'Jeu "' . htmlspecialchars($title) . '" ajouté automatiquement avec succès !';
                        if ($info_messages_count > 0) {
                            $flash_message .= ' (' . $info_messages_count . ' info' . ($info_messages_count > 1 ? 's' : '') . ')';
                        }
                         $_SESSION['admin_flash_message'] = ['type' => 'success', 'message' => $flash_message];
                        header('Location: ./');
                        exit();
                    } else {
                         $errors[] = "L'insertion dans la base de données a échoué sans erreur PDO explicite.";
                         error_log("Admin Auto Add - Final DB Insert: execute() returned false for Game '$title' ConsoleID '$console_id'");
                         if(!empty($rom_destination) && file_exists($rom_destination)) @unlink($rom_destination);
                         if(!empty($cover_destination) && file_exists($cover_destination)) @unlink($cover_destination);
                         if(!empty($preview_destination) && file_exists($preview_destination)) @unlink($preview_destination);
                    }

                 } catch (PDOException $e) {
                     // Attraper les erreurs spécifiques (ex: clé dupliquée) pourrait être utile
                     $errorInfo = $stmtInsert ? $stmtInsert->errorInfo() : $db->errorInfo(); // Obtenir plus d'infos
                     $sqlErrorCode = $e->getCode() ?: ($errorInfo[1] ?? 'N/A');
                     $sqlErrorMessage = $e->getMessage() ?: ($errorInfo[2] ?? 'N/A');

                     $errors[] = "Erreur finale BDD (Code: $sqlErrorCode). Vérifiez les logs.";
                     error_log("Admin Auto Add - Final DB Insert PDOException: Game '$title' ConsoleID '$console_id' - SQLSTATE[{$sqlErrorCode}] {$sqlErrorMessage}");

                     // Suppression fichiers
                     if(!empty($rom_destination) && file_exists($rom_destination)) @unlink($rom_destination);
                     if(!empty($cover_destination) && file_exists($cover_destination)) @unlink($cover_destination);
                     if(!empty($preview_destination) && file_exists($preview_destination)) @unlink($preview_destination);
                 }
            } else {
                 error_log("Admin Auto Add: Insertion BDD annulée à cause d'erreurs pour la ROM: " . ($rom_file['name'] ?? 'N/A'));
                 // Optionnel: ajouter une erreur générique si ce n'est pas déjà fait
                 if (!in_array("Erreur lors du traitement. Vérifiez les messages ci-dessus.", $errors)) {
                    $errors[] = "Erreur lors du traitement. Vérifiez les messages ci-dessus.";
                 }
            } // Fin if (empty($real_errors))

        } // Fin else (jeu trouvé sur SS)
    } // Fin if (empty($errors) && $console)

} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

// --- Handle Search Request (AJAX) ---
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    $rawTerm = $_GET['term'] ?? '';
    $searchTerm = cleanRomFilename($rawTerm); // Nettoyage côté serveur
    $systemId = $_GET['system_id'] ?? 0;
    
    if (empty($searchTerm) || !$systemId) {
        echo json_encode(['error' => 'Term and System ID required']);
        exit;
    }
    
    $searchUrl = "https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=" . urlencode($searchTerm) . "&systemeid=" . $systemId;
    $result = callScreenScraperAPI($searchUrl);
    
    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']]);
    } else {
        $games = $result['response']['jeux'] ?? [];
        // Format games for display
        $formatted = [];
        foreach ($games as $g) {
            $formatted[] = [
                'id' => $g['id'],
                'title' => $g['noms'][0]['text'] ?? 'Unknown',
                'cover' => 'https://api.screenscraper.fr/api2/mediaJeu.php?systemeid=' . $systemId . '&jeuid=' . $g['id'] . '&media=box-2D&region=wor&ssid=' . urlencode(SCREENSCRAPER_USER) . '&sspassword=' . urlencode(SCREENSCRAPER_PASSWORD) . '&devid=' . urlencode(SCREENSCRAPER_DEV_ID) . '&devpassword=' . urlencode(SCREENSCRAPER_DEV_PASSWORD) . '&softname=RetroHomeAdmin',
                'description' => $g['synopsis'][0]['text'] ?? 'No description',
                'year' => $g['dates'][0]['text'] ?? 'N/A'
            ];
        }
        echo json_encode($formatted);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_add_game_auto_title') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <link rel="stylesheet" href="../public/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/vendor/animatecss/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../public/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="public/css/admin_style.css">
    <style>
        .selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .selection-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .selection-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 5px 15px var(--primary-glow);
        }
        .selection-card.selected {
            border-color: var(--primary);
            background: rgba(0, 242, 255, 0.1);
            box-shadow: 0 0 20px var(--primary-glow);
        }
        /* Image Container with Aspect Ratio */
        .img-container {
            width: 100%;
            aspect-ratio: 16/9; /* ScreenScraper covers usually fit well here, or auto */
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            overflow: hidden;
            position: relative;
        }
        /* Skeleton Loading Animation */
        .skeleton {
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            position: absolute;
            top: 0;
            left: 0;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .selection-card img {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Don't crop, show full cover */
            opacity: 0; /* Hidden initially for fade-in */
            transition: opacity 0.5s ease;
            position: relative;
            z-index: 1;
        }
        .selection-card img.loaded {
            opacity: 1;
        }
        .selection-card h4 {
            font-size: 0.9rem;
            margin: 0;
            color: #fff;
        }
        .selection-card p {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin: 5px 0 0;
        }
        #selection-container {
            margin-top: 30px;
            display: none;
        }
    </style>
</head>
<body class="bg-background text-text-primary">

    <div class="app-container">
        <!-- Premium Header -->
        <header class="glass nav-bar animate-fade-in" style="margin-bottom: 40px;">
            <div class="flex items-center gap-4">
                <div style="background: rgba(112, 0, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px var(--secondary-glow);">
                    <i class="fas fa-magic text-xl text-secondary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_add_game_auto_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;"><?= __('admin_node_retro_version') ?></span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-errors animate-fade-in mb-6">
                <p class="font-bold mb-2"><?= __('admin_process_result') ?> :</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <?php
                            $isInfo = strpos($error, 'INFO:') === 0;
                            $message = $isInfo ? substr($error, 5) : $error;
                            $iconClass = $isInfo ? 'fas fa-info-circle text-blue-400' : 'fas fa-exclamation-circle text-red-400'; 
                            $textClass = $isInfo ? 'text-blue-300' : 'text-red-300';
                        ?>
                        <li class="<?= $textClass ?>"><i class="<?= $iconClass ?> mr-2"></i><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

         <?php if ($noConsolesConfigured): ?>
             <div class="form-errors animate-fade-in mb-6" style="background: rgba(255, 171, 0, 0.1); border-color: rgba(255, 171, 0, 0.2); color: #ffd180;">
                <strong class="font-bold"><?= __('admin_config_required') ?>!</strong>
                <span class="block sm:inline"><?= __('admin_no_console_ss_id') ?></span>
            </div>
         <?php endif; ?>


        <div class="glass p-8 animate-fade-in" style="animation-delay: 0.2s;">
             <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 30px; opacity: 0.7;">
                <?= __('admin_add_game_auto_desc') ?>
            </p>

            <form action="add_game_auto" method="post" enctype="multipart/form-data" id="auto-add-form" novalidate>
                 <div class="form-grid">

                    <div class="form-group">
                        <label for="console_id"><?= __('admin_console') ?> *</label>
                        <select id="console_id" name="console_id" class="form-control" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                            <option value="" disabled <?= empty($console_id) ? 'selected' : '' ?>><?= __('admin_choose_console') ?></option>
                            <?php foreach ($consoles as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($console_id == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> <?= !empty($c['ss_id']) ? '(ID:'.$c['ss_id'].')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($noConsolesConfigured): ?>
                             <p style="font-size: 0.65rem; color: var(--accent); margin-top: 8px;"><?= __('admin_no_console_ss_id_short') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="region"><?= __('admin_description_lang') ?? 'Langue Description' ?></label>
                        <select id="region" name="region" class="form-control">
                            <option value="fr">Français (FR)</option>
                            <option value="en">English (EN)</option>
                            <option value="es">Español (ES)</option>
                            <option value="pt">Português (PT)</option>
                            <option value="de">Deutsch (DE)</option>
                            <option value="it">Italiano (IT)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rom"><?= __('admin_rom_label') ?> *</label>
                        <input type="file" id="rom" name="rom" class="form-control" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                        <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; opacity: 0.6;"><?= __('admin_rom_auto_desc') ?></p>
                    </div>

                </div>

                 <!-- Selection Area -->
                 <div id="selection-container" class="animate-fade-in">
                      <h3 class="pixel-text" style="font-size: 1rem; margin-bottom: 20px; color: var(--primary);">
                          <i class="fas fa-check-circle mr-2"></i><?= __('admin_confirm_match') ?? 'Confirmer la correspondance' ?>
                      </h3>
                      <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 20px;">
                          <?= __('admin_select_game_desc') ?? 'Plusieurs résultats trouvés. Veuillez sélectionner le bon jeu :' ?>
                      </p>
                      <div id="selection-grid" class="selection-grid"></div>
                      <input type="hidden" name="game_id" id="selected_game_id">
                 </div>

                 <!-- Zone de chargement -->
                 <!-- Zone de chargement Ultra Moderne -->
                 <div id="loading-message" class="loading-message glass p-8" style="display: none; text-align: center; margin: 30px 0; border: 1px solid var(--primary);">
                      <div class="flex flex-col items-center justify-center">
                           <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem; position: relative;">
                               <i class="fas fa-satellite-dish fa-spin" style="--fa-animation-duration: 3s;"></i>
                               <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; border: 1px solid var(--primary); border-radius: 50%; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
                           </div>
                           <h3 class="pixel-text" style="color: white; font-size: 1.5rem; margin-bottom: 0.5rem;"><?= __('admin_scanning_db') ?? 'SCANNING_DATABASES' ?>...</h3>
                           <p style="color: var(--primary); font-family: monospace; letter-spacing: 2px; font-size: 0.8rem;" id="loading-text">CONNECTING_TO_SCREENSCRAPER_NODE</p>
                           
                           <div class="progress-bar-container" style="width: 100%; max-width: 300px; height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 15px; overflow: hidden;">
                               <div class="progress-bar-fill" style="height: 100%; background: var(--primary); width: 0%; animation: progressIndeterminate 2s infinite linear;"></div>
                           </div>
                      </div>
                      <style>
                          @keyframes ping {
                              75%, 100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
                          }
                          @keyframes progressIndeterminate {
                              0% { width: 0%; transform: translateX(-100%); }
                              50% { width: 100%; transform: translateX(0%); }
                              100% { width: 100%; transform: translateX(100%); }
                          }
                      </style>
                 </div>


                <div class="flex justify-end gap-4 mt-8 pt-8" style="border-top: 1px solid var(--glass-border);">
                    <a href="index.php" class="btn-modern btn-secondary">
                         <i class="fas fa-times"></i> <?= __('admin_cancel') ?>
                    </a>
                    <button type="submit" class="btn-modern btn-primary" id="submit-auto-add" <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                        <i class="fas fa-search"></i> <?= __('admin_search_and_add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Script JS pour afficher/cacher chargement (inchangé)
        const form = document.getElementById('auto-add-form');
        const submitButton = document.getElementById('submit-auto-add');
        const loadingMessage = document.getElementById('loading-message');
        const consoleSelect = document.getElementById('console_id');
        const romInput = document.getElementById('rom');
        const selectionContainer = document.getElementById('selection-container');
        const selectionGrid = document.getElementById('selection-grid');
        const selectedGameIdInput = document.getElementById('selected_game_id');

        let isSearching = false;

        form.addEventListener('submit', async function(event) {
            if (selectedGameIdInput.value) {
                // If we already have a selected ID, let the form submit normally
                submitButton.disabled = true; 
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Ajout en cours...';
                loadingMessage.style.display = 'block';
                return;
            }

            event.preventDefault();
            
            let valid = true; 
            consoleSelect.style.borderColor = ''; 
            romInput.style.borderColor = '';
            let errorMsg = '';
            
            if (!consoleSelect.value) { consoleSelect.style.borderColor = 'red'; valid = false; errorMsg += 'Veuillez sélectionner une console.\n'; }
            if (romInput.files.length === 0) { romInput.style.borderColor = 'red'; valid = false; errorMsg += 'Veuillez sélectionner un fichier ROM.\n'; }
            
            if (!valid) {
                let errorDiv = document.querySelector('.form-errors');
                if (!errorDiv) { 
                    errorDiv = document.createElement('div'); 
                    errorDiv.className = 'form-errors animate__animated animate__shakeX mb-6'; 
                    form.parentNode.insertBefore(errorDiv, form); 
                }
                errorDiv.innerHTML = `<p class="font-bold mb-2">Erreur Formulaire:</p><ul><li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>${errorMsg.trim().replace(/\n/g, '</li><li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>')}</li></ul>`;
                return;
            }

            // Start Search
            isSearching = true;
            submitButton.disabled = true; 
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Recherche...';
            loadingMessage.style.display = 'block';
            selectionContainer.style.display = 'none';

            const term = romInput.files[0].name.split('.').slice(0, -1).join('.');
            const systemId = consoleSelect.options[consoleSelect.selectedIndex].text.match(/ID:(\d+)/)?.[1] || 0;

            try {
                const response = await fetch(`add_game_auto.php?action=search&term=${encodeURIComponent(term)}&system_id=${systemId}`);
                const games = await response.json();

                loadingMessage.style.display = 'none';
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-search"></i> <?= __('admin_search_and_add') ?>';

                if (games.error) {
                    alert('Erreur: ' + games.error);
                    return;
                }

                if (games.length === 0) {
                    alert('Aucun jeu trouvé sur ScreenScraper. L\'ajout manuel sera utilisé.');
                    form.submit(); // Submit anyway to add with defaults
                    return;
                }

                // Show selection grid
                selectionGrid.innerHTML = '';
                games.forEach(game => {
                    const card = document.createElement('div');
                    card.className = 'selection-card animate__animated animate__fadeIn';
                    card.innerHTML = `
                        <div class="img-container">
                            <div class="skeleton"></div>
                            <img src="${game.cover}" alt="${game.title}" loading="lazy" 
                                 onload="this.classList.add('loaded'); this.previousElementSibling.style.display='none';" 
                                 onerror="this.style.display='none'; this.previousElementSibling.style.display='block'; this.previousElementSibling.classList.remove('skeleton'); this.previousElementSibling.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,0.3);\'><i class=\'fas fa-image fa-2x\'></i></div>';">
                        </div>
                        <h4 style="margin-top:auto;">${game.title}</h4>
                        <p>${game.year}</p>
                    `;
                    card.onclick = () => {
                        document.querySelectorAll('.selection-card').forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        selectedGameIdInput.value = game.id;
                        submitButton.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> <?= __('admin_confirm_and_add') ?? 'Confirmer et Ajouter' ?>';
                    };
                    selectionGrid.appendChild(card);
                });

                selectionContainer.style.display = 'block';
                window.scrollTo({ top: selectionContainer.offsetTop - 100, behavior: 'smooth' });

            } catch (err) {
                console.error(err);
                alert('Erreur lors de la recherche.');
                loadingMessage.style.display = 'none';
                submitButton.disabled = false;
            }
        });
    </script>
</body>
</html>
