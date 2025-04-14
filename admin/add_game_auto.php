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

    // Traitement spécifique pour l'endpoint mediaVideoJeu qui peut renvoyer du texte simple
    if (strpos($url, 'mediaVideoJeu.php') !== false) {
         if ($http_code == 200) {
             if (strpos($response, 'OK') !== false) { // Ex: CRCOK, MD5OK, SHA1OK
                  return ['status' => 'OK_CHECKSUM']; // Indique que le fichier local est bon
             } elseif (strpos($response, 'NOMEDIA') !== false) {
                  return ['error' => 'NOMEDIA', 'http_code' => 404]; // Média non trouvé
             } elseif (strpos($response, '<?xml') === 0 || strpos($response, '<html') === 0 || empty(trim($response))) {
                  // Si ça renvoie du XML/HTML ou est vide, c'est probablement une erreur non JSON
                   return ['error' => "Réponse inattendue de l'API vidéo (non-JSON/vide). Code: $http_code", 'http_code' => $http_code];
             }
             // Si ce n'est rien de tout ça mais code 200, on suppose que c'est le contenu vidéo (même si binaire)
             // La fonction downloadMedia gère l'écriture
              return ['is_media_content' => true, 'http_code' => $http_code, 'content' => $response]; // On retourne le contenu pour downloadMedia
         } else {
              // Erreur HTTP pour l'API vidéo
              return ['error' => "Erreur API vidéo ScreenScraper (Code: $http_code)", 'http_code' => $http_code];
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

        // Si c'est l'URL de l'API vidéo, on utilise callScreenScraperAPI
        if (strpos($url, 'mediaVideoJeu.php') !== false) {
            $api_response = callScreenScraperAPI($url); // Appelle l'API vidéo

            if (isset($api_response['error'])) {
                if ($api_response['error'] === 'NOMEDIA') {
                    error_log("DownloadMedia: ScreenScraper a répondu 'NOMEDIA' pour l'URL: " . $url);
                    return ['success' => false, 'error' => 'NOMEDIA']; // Erreur spécifique
                } else {
                    error_log("DownloadMedia: Erreur API vidéo ScreenScraper: " . $api_response['error'] . " URL: " . $url);
                    return ['success' => false, 'error' => $api_response['error']];
                }
            } elseif (isset($api_response['is_media_content']) && $api_response['is_media_content']) {
                // Écrire le contenu binaire directement dans le fichier
                if (file_put_contents($destination_path, $api_response['content']) === false) {
                     error_log("DownloadMedia: Impossible d'écrire le contenu vidéo dans: " . $destination_path);
                     return ['success' => false, 'error' => "Impossible d'écrire le fichier vidéo."];
                }
                // Vérifier la taille du fichier écrit
                 if (filesize($destination_path) == 0) {
                     error_log("DownloadMedia: Fichier vidéo écrit mais vide pour $url.");
                     @unlink($destination_path);
                     return ['success' => false, 'error' => 'Fichier vidéo téléchargé vide.'];
                 }
                 return ['success' => true]; // Succès écriture directe
            } else {
                 // Réponse inattendue de l'API vidéo
                 error_log("DownloadMedia: Réponse inattendue de l'API vidéo pour l'URL: " . $url . " Réponse: " . json_encode($api_response));
                 return ['success' => false, 'error' => 'Réponse inattendue de l\'API vidéo.'];
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
            $ssGame = $searchResult['response']['jeux'][0];
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
            // Titre (priorité ss > us > eu > wor > jp > premier trouvé > fallback nom fichier)
            $title = $searchTerm;
            $regions_order_title = ['ss', 'us', 'eu', 'wor', 'jp'];
            foreach($regions_order_title as $region) { foreach ($gameData['noms'] ?? [] as $nom) { if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { $title = $nom['text']; break 2; }}}
            if ($title === $searchTerm && isset($gameData['noms'][0]['text']) && !empty($gameData['noms'][0]['text'])) { $title = $gameData['noms'][0]['text']; }
            $title = trim($title);

            // Description (Priorité fr > en)
            $description = ''; $lang_order = ['fr', 'en'];
            foreach($lang_order as $lang) { foreach ($gameData['synopsis'] ?? [] as $syn) { if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { $description = $syn['text']; break 2; }}}
            $description = $description ? trim(preg_replace('/\s+/', ' ', $description)) : null;

            // Année
            $year = null; foreach ($gameData['dates'] ?? [] as $date) { if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { $y = (int)$matches[1]; if ($y >= 1950 && $y < ($year ?? PHP_INT_MAX) ) $year = $y; }}

            // Editeur
            $publisher = isset($gameData['editeur']['text']) && !empty($gameData['editeur']['text']) ? trim($gameData['editeur']['text']) : null;

            // Jaquette (Priorité mixrbv1 > box-2D > box-3D > screenshot ; wor > ss > us > eu > jp)
            $coverUrl = ''; $mediaTypes = ['mixrbv1', 'box-2D', 'box-3D', 'screenshot']; $regions = ['wor', 'ss', 'us', 'eu', 'jp'];
            foreach ($mediaTypes as $type) { foreach ($regions as $region) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['region'], $media['url']) && $media['type'] == $type && $media['region'] == $region && !empty($media['url'])) { $coverUrl = $media['url']; error_log("Auto Add: Cover trouvée ($type, $region) pour {$title}."); break 3; }}}
                if (empty($coverUrl)) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['url']) && $media['type'] == $type && !empty($media['url'])) { $coverUrl = $media['url']; error_log("Auto Add: Cover trouvée ($type, fallback région) pour {$title}."); break 2; }}}
                if (!empty($coverUrl)) break;
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
             if (empty($errors)) {
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

            if (empty($errors)) { // Continue seulement si pas d'erreur (doublon, dossier)
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
                if (empty($errors) && !empty($coverUrl)) {
                    $cover_ext = strtolower(pathinfo(parse_url($coverUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                    $cover_filename = $gameSlug . '.' . $cover_ext; // Nom basé sur slug API
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
                if (empty($errors)) { // Ne tente que si ROM OK
                    $mediaVideoUrl = 'https://api.screenscraper.fr/api2/mediaVideoJeu.php?systemeid=' . $systemId . '&jeuid=' . $ssGameId;
                    $preview_filename = $gameSlug . '.mp4'; // Nom fichier local
                    $preview_destination = $previewDir . $preview_filename;
                    error_log("Admin Auto Add: Tentative DL preview via API endpoint pour jeu ID {$ssGameId}.");
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

            } // Fin if (empty($errors)) pour téléchargement

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
                        header('Location: index.php');
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

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajout Auto Jeu - Administration</title>
    <link rel="icon" type="image/png" href="../assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Ajouter un Jeu (Automatique)</h1>
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-errors <?= !empty(array_filter($errors, fn($e) => strpos($e, 'INFO:') !== 0)) ? 'animate__animated animate__shakeX' : '' ?> mb-6">
                <p class="font-bold mb-2">Résultat du traitement :</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <?php
                            $isInfo = strpos($error, 'INFO:') === 0;
                            $message = $isInfo ? substr($error, 5) : $error;
                            $iconClass = $isInfo ? 'fas fa-info-circle text-blue-400' : 'fas fa-exclamation-circle text-red-400'; // Rouge plus clair
                            $textClass = $isInfo ? 'text-blue-300' : 'text-red-300';
                        ?>
                        <li class="<?= $textClass ?>"><i class="<?= $iconClass ?> mr-2"></i><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

         <?php if ($noConsolesConfigured): ?>
             <div class="bg-yellow-800 border border-yellow-600 text-yellow-100 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Configuration requise!</strong>
                <span class="block sm:inline">Aucune console n'a d'ID ScreenScraper configuré. Veuillez modifier les consoles pour utiliser cette fonctionnalité.</span>
            </div>
         <?php endif; ?>


        <div class="admin-form-container animate__animated animate__fadeInUp">
             <p class="text-text-secondary mb-6 text-sm">
                Sélectionnez la console, puis téléchargez le fichier ROM. Le système tentera de récupérer
                automatiquement les informations (titre Fr>En, description Fr>En, année, jaquette MixV1>2D>3D, vidéo MP4) depuis ScreenScraper.fr.
            </p>

            <form action="add_game_auto.php" method="post" enctype="multipart/form-data" id="auto-add-form" novalidate>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                    <div class="form-group md:col-span-1">
                        <label for="console_id">Console :<span class="text-red-500">*</span></label>
                        <select id="console_id" name="console_id" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                            <option value="" disabled <?= empty($console_id) ? 'selected' : '' ?>>-- Sélectionner la console --</option>
                            <?php foreach ($consoles as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($console_id == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> <?= !empty($c['ss_id']) ? '(ID:'.$c['ss_id'].')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($noConsolesConfigured): ?>
                             <p class="text-xs text-yellow-400 mt-1">Aucune console configurée pour l'ajout auto.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group md:col-span-1">
                        <label for="rom">Fichier ROM :<span class="text-red-500">*</span></label>
                        <input type="file" id="rom" name="rom" accept=".zip,.sfc,.smc,.fig,.bin,.gba,.gbc,.gb,.nes,.pce,.md,.mgd,.sms,.gg,.col,.ngp,.ngc,.ws,.wsc,.7z,.iso,.cue" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                        <p class="text-xs text-text-secondary mt-1">Le nom du fichier sera utilisé pour la recherche.</p>
                    </div>

                </div> <!-- Fin Grid -->

                 <!-- Zone de chargement -->
                 <div id="loading-message" class="loading-message">
                      <i class="fas fa-sync fa-spin"></i>
                     <p>Recherche des informations sur ScreenScraper et téléchargement des médias...</p>
                     <p class="text-sm text-text-secondary">Cela peut prendre un certain temps.</p>
                 </div>


                <div class="form-actions">
                    <a href="index.php" class="form-button cancel-button">
                         <i class="fas fa-times mr-2"></i>Annuler
                    </a>
                    <button type="submit" class="form-button submit-button" id="submit-auto-add" <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                        <i class="fas fa-search mr-2"></i>Lancer la recherche et Ajouter
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
        if (form && submitButton && loadingMessage && consoleSelect && romInput) {
            form.addEventListener('submit', function(event) {
                 let valid = true; consoleSelect.style.borderColor = ''; romInput.style.borderColor = '';
                let errorMsg = '';
                if (!consoleSelect.value) { consoleSelect.style.borderColor = 'red'; valid = false; errorMsg += 'Veuillez sélectionner une console.\n';}
                 if (romInput.files.length === 0) { romInput.style.borderColor = 'red'; valid = false; errorMsg += 'Veuillez sélectionner un fichier ROM.\n';}
                 if (!valid) {
                     event.preventDefault();
                    let errorDiv = document.querySelector('.form-errors');
                     if (!errorDiv) { errorDiv = document.createElement('div'); errorDiv.className = 'form-errors animate__animated animate__shakeX mb-6'; form.parentNode.insertBefore(errorDiv, form); }
                     errorDiv.innerHTML = `<p class="font-bold mb-2">Erreur Formulaire:</p><ul><li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>${errorMsg.trim().replace(/\n/g, '</li><li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>')}</li></ul>`; // Correction regex replace
                     return;
                 }
                submitButton.disabled = true; submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Traitement...';
                loadingMessage.style.display = 'block'; loadingMessage.classList.add('animate__animated', 'animate__fadeIn');
            });
        } else { console.error("Erreur JS: Eléments formulaire auto-add manquants."); }
    </script>
</body>
</html>