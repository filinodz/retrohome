<?php
// Désactiver la mise en mémoire tampon pour la sortie SSE
if (ob_get_level() > 0) { for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); } }
ob_implicit_flush(true);

// Charger la configuration et démarrer la session
require_once '../config.php'; // Chemin relatif
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- SSE Headers ---
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Important pour Nginx

// --- Configuration de l'exécution ---
set_time_limit(0); // Pas de limite de temps pour ce script

// --- Fonction utilitaire pour envoyer des messages SSE ---
function send_sse_message($event_type, $data) {
    echo "event: " . $event_type . "\n";
    // Utiliser JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES pour un meilleur rendu
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    // Forcer l'envoi au client
    @flush();
    @ob_flush();
}

// --- Récupération du token et des données de session ---
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$session_key = 'bulk_upload_data_' . $token;

if (!$token || !isset($_SESSION[$session_key])) {
    send_sse_message('error_event', ['filename' => 'N/A', 'message' => __('admin_invalid_token')]);
    exit();
}

$upload_data = $_SESSION[$session_key];
$console_id = $upload_data['console_id'];
$files_to_process = $upload_data['files'];
$total_files = count($files_to_process);

// --- Initialisation de la connexion DB et définition des fonctions ---
$db = null;
try {
    // Connexion à la base de données
    try {
         $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        send_sse_message('error_event', ['filename' => 'N/A', 'message' => __('admin_db_error_fatal')]);
        error_log("SSE Process Error - DB Connection failed: " . $e->getMessage());
        unset($_SESSION[$session_key]); // Nettoyer session en cas d'erreur fatale
        exit;
    }

    // --- Définition des Fonctions Utilitaires ---

    /**
     * Nettoie un nom de fichier pour obtenir un terme de recherche pertinent.
     * @param string $filename Nom de fichier original.
     * @return string Terme de recherche nettoyé.
     */
    function cleanRomFilename($filename) {
        $cleaned_filename = pathinfo($filename, PATHINFO_FILENAME);
        $cleaned_filename = preg_replace('/[\{\(\[][^\]\)]*[\)\]\]]/', '', $cleaned_filename); // Tags [], ()
        $cleaned_filename = preg_replace('/^\d+\s*-\s*/', '', $cleaned_filename); // Numéros GoodTools
        $cleaned_filename = str_replace(['_', '.'], ' ', $cleaned_filename); // Underscores/Points -> Espaces
        $cleaned_filename = trim(preg_replace('/\s+/', ' ', $cleaned_filename)); // Espaces multiples + trim
        // Optionnel: supprimer des mots courants peu utiles
        // $cleaned_filename = str_ireplace([' the ', ' a '], ' ', $cleaned_filename);
        // $cleaned_filename = trim(preg_replace('/\s+/', ' ', $cleaned_filename));
        return $cleaned_filename;
    }

    /**
     * Appelle l'API ScreenScraper (pour les endpoints JSON).
     * @param string $url URL de l'endpoint API.
     * @return array Résultat décodé ou tableau d'erreur.
     */
    function callScreenScraperAPI($url) {
        if (!defined('SCREENSCRAPER_USER') || !defined('SCREENSCRAPER_PASSWORD') || !defined('SCREENSCRAPER_DEV_ID') || !defined('SCREENSCRAPER_DEV_PASSWORD')) {
            return ['error' => "Identifiants ScreenScraper non définis dans config.php", 'http_code' => 500];
        }
        // Construction de l'URL complète avec authentification et paramètres
        $full_url = $url . (strpos($url, '?') === false ? '?' : '&')
                       . http_build_query([
                           'ssid' => SCREENSCRAPER_USER,
                           'sspassword' => SCREENSCRAPER_PASSWORD,
                           'devid' => SCREENSCRAPER_DEV_ID,
                           'devpassword' => SCREENSCRAPER_DEV_PASSWORD,
                           'softname' => 'RetroHomeAdminBulk',
                           'output' => 'json'
                       ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout 60s
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (PHP cURL)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À ajuster pour la prod si possible
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);   // À ajuster pour la prod si possible
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        if ($ch !== false) { curl_close($ch); }

        if ($curl_error) { return ['error' => "Erreur cURL: " . $curl_error, 'http_code' => $http_code]; }

        // Traitement spécifique pour les endpoints media qui peuvent renvoyer du contenu binaire
        if (strpos($url, 'mediaVideoJeu.php') !== false || strpos($url, 'mediaJeu.php') !== false) {
             if ($http_code == 200) {
                 if (strpos($response, 'OK') === 0 && strlen($response) < 10) {
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

        // Gérer les erreurs HTTP et les réponses non JSON
        if ($http_code >= 400) {
             $error_data = json_decode($response, true);
             $error_message = $error_data['message'] ?? $response;
             if (stripos($response, 'maximum threads') !== false) $error_message = "Limite requêtes SS atteinte.";
             elseif ($http_code === 401 || stripos($response, 'Erreur de login') !== false) $error_message = "Identifiants SS invalides.";
             elseif ($http_code === 429) $error_message = "Trop de requêtes (Code 429). Ralentir.";
            return ['error' => "Erreur API SS (Code: $http_code): " . strip_tags($error_message), 'http_code' => $http_code];
        }

        // Décoder le JSON
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Si le JSON est invalide mais que la requête était OK, logguer la réponse brute
             error_log("SSE Process - Invalid JSON received from $url. HTTP Code: $http_code. Response: " . substr($response, 0, 500));
            return ['error' => "Réponse API invalide (JSON mal formé)", 'http_code' => $http_code];
        }
        // Vérifier l'état de connexion SS
        if (isset($data['ssuser']['connect']) && $data['ssuser']['connect'] == 0) {
             return ['error' => "Échec connexion SS (user/pass incorrects?).", 'http_code' => 401];
        }
        return $data; // Retourne les données décodées
    }

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
 * Télécharge un fichier média (image ou vidéo).
 * @param string $url URL source du média.
 * @param string $destination_path Chemin de sauvegarde local.
 * @return array ['success' => bool, 'error' => string|null]
 */
function downloadMedia($url, $destination_path) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        error_log("DownloadMedia SSE: URL invalide: " . print_r($url, true));
        return ['success' => false, 'error' => 'URL invalide'];
    }

    try {
        $dir = dirname($destination_path);
        if (!is_dir($dir)) {
             if (!@mkdir($dir, 0775, true)) { return ['success' => false, 'error' => 'Échec création dossier']; }
        }
        if (!is_writable($dir)) { return ['success' => false, 'error' => 'Dossier non accessible']; }

        if (strpos($url, 'mediaVideoJeu.php') !== false || strpos($url, 'mediaJeu.php') !== false) {
            $api_response = callScreenScraperAPI($url);

            if (isset($api_response['error'])) {
                if ($api_response['error'] === 'NOMEDIA') {
                    return ['success' => false, 'error' => 'NOMEDIA'];
                } else {
                    return ['success' => false, 'error' => $api_response['error']];
                }
            } elseif (isset($api_response['is_media_content']) && $api_response['is_media_content']) {
                if (file_put_contents($destination_path, $api_response['content']) === false) {
                     return ['success' => false, 'error' => "Échec écriture fichier média"];
                }
                 if (filesize($destination_path) == 0) {
                     @unlink($destination_path);
                     return ['success' => false, 'error' => 'Fichier média téléchargé vide'];
                 }
                 return ['success' => true];
            } else {
                 return ['success' => false, 'error' => 'Réponse inattendue de l\'API média.'];
            }
        } else {
            // Téléchargement standard (fallback)
            $ch = curl_init($url);
            $fp = fopen($destination_path, 'wb');
            if (!$fp) { @curl_close($ch); return ['success' => false, 'error' => "Échec ouverture fichier dest."]; }

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (PHP cURL)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $success_curl = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if (!$success_curl || $http_code >= 400) {
                @unlink($destination_path);
                return ['success' => false, 'error' => "Erreur $http_code DL."];
            }
            if (filesize($destination_path) == 0) {
                @unlink($destination_path);
                return ['success' => false, 'error' => 'Fichier DL vide.'];
            }
            return ['success' => true];
        }
    } catch (Exception $e) {
        @unlink($destination_path);
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}
    // --- FIN des Fonctions Utilitaires ---

} catch (Exception $e) {
     send_sse_message('error_event', ['filename' => 'N/A', 'message' => 'Erreur initialisation SSE (fonctions): ' . $e->getMessage()]);
     error_log("SSE Process Error - Initialization/Functions failed: " . $e->getMessage());
     unset($_SESSION[$session_key]);
     exit;
}


// --- Récupérer les infos de la console ---
$console = null;
try {
    $stmtConsole = $db->prepare("SELECT id, name, slug, ss_id FROM consoles WHERE id = ?");
    $stmtConsole->execute([$console_id]);
    $console = $stmtConsole->fetch(PDO::FETCH_ASSOC);
    if (!$console || empty($console['ss_id'])) {
        send_sse_message('error_event', ['filename' => 'N/A', 'message' => 'Console invalide ou non configurée pour SS (ID: '.$console_id.').']);
        unset($_SESSION[$session_key]); exit();
    }
     $consoleSlug = $console['slug'];
     $systemId = $console['ss_id'];
     send_sse_message('log', ['message' => __('admin_console_loaded') . ': ' . htmlspecialchars($console['name'])]);

} catch (PDOException $e) {
    send_sse_message('error_event', ['filename' => 'N/A', 'message' => __('admin_db_error_fetch_console') . ': ' . $e->getMessage()]);
    error_log("SSE Process Error - Fetch console failed: " . $e->getMessage());
    unset($_SESSION[$session_key]); exit();
}

// --- Boucle Principale de Traitement ---
send_sse_message('log', ['message' => __('admin_start_processing_files') . ' ' . $total_files . ' ' . __('admin_files_plural') . '...']);

for ($i = 0; $i < $total_files; $i++) {
    $file_info = $files_to_process[$i];
    $current_rom_original_name = $file_info['original_name'];
    $current_rom_tmp_path = $file_info['temp_path']; // Chemin temporaire unique
    $current_file_errors = [];
    $current_file_infos = [];

    // Envoi du statut initial pour ce fichier
    send_sse_message('progress', [
        'index' => ($i + 1),
        'total' => $total_files,
        'filename' => htmlspecialchars($current_rom_original_name),
        'status' => __('admin_initialization')
    ]);

    // Vérifier existence fichier temporaire
    if (!file_exists($current_rom_tmp_path) || !is_readable($current_rom_tmp_path)) {
        $errMsg = 'Fichier temporaire introuvable ou illisible (' . basename($current_rom_tmp_path) . ').';
        send_sse_message('error_event', ['filename' => htmlspecialchars($current_rom_original_name), 'message' => $errMsg]);
        error_log("SSE Process Error - Temp file missing/unreadable: " . $current_rom_tmp_path);
        continue; // Passer au suivant
    }

    // Utiliser un bloc try/catch pour chaque fichier pour isoler les erreurs
    try {
        // Pause API (sauf pour le premier)
        if ($i > 0) {
            send_sse_message('log', ['message' => __('admin_pause_api') . ' (1s)...']);
            sleep(1);
        }

        // 1. Recherche API ScreenScraper
        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_api_search')]);
        $searchTerm = cleanRomFilename($current_rom_original_name);
        send_sse_message('log', ['message' => __('admin_search_term_for') . " '" . htmlspecialchars($current_rom_original_name) . "': '" . htmlspecialchars($searchTerm) . "'"]);

        $searchUrl = 'https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=' . urlencode($searchTerm) . '&systemeid=' . $systemId;
        $searchResult = callScreenScraperAPI($searchUrl);

        // Gestion des erreurs de recherche
        if (isset($searchResult['error'])) {
            $current_file_errors[] = "Recherche SS: " . $searchResult['error'];
            // Si erreur 429 (Too Many Requests), faire une pause plus longue avant de continuer
            if (($searchResult['http_code'] ?? 0) == 429) {
                send_sse_message('log', ['message' => __('admin_error_429_pause')]);
                sleep(5);
            }
        } elseif (empty($searchResult['response']['jeux'])) { // Utiliser empty() est plus sûr
            $current_file_errors[] = "Aucun jeu trouvé sur SS pour '" . htmlspecialchars($searchTerm) . "'.";
        } else {
            // 2. Jeu trouvé -> Infos détaillées
            send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_api_infos')]);
            $ssGame = findBestLevenMatch($searchResult['response']['jeux'], $searchTerm);
            $ssGameId = $ssGame['id'] ?? null; // Utiliser null coalescing operator
            if (!$ssGameId) {
                 $current_file_errors[] = "ID de jeu manquant dans la réponse API.";
                 // Continuer sans ID de jeu ? Peut-être pas pertinent. On sort du 'else'.
            } else {
                $gameData = $ssGame;
                $infoUrl = 'https://api.screenscraper.fr/api2/jeuInfos.php?gameid=' . $ssGameId;
                usleep(500000); // Pause 0.5s
                $infoResult = callScreenScraperAPI($infoUrl);
                if (!isset($infoResult['error']) && isset($infoResult['response']['jeu'])) {
                    $gameData = $infoResult['response']['jeu'];
                    send_sse_message('log', ['message' => __('admin_game_details_loaded')]);
                } elseif (isset($infoResult['error'])) { $current_file_infos[] = "INFO: " . __('admin_error_ss_details') . ": " . $infoResult['error']; }
                else { $current_file_infos[] = "INFO: " . __('admin_no_complete_details'); }

                // --- Extraction des données ---
            $selectedRegion = $current_batch_data['region'] ?? 'fr';

            // Titre (priorité ss > us > eu > wor > jp > premier trouvé > fallback nom fichier)
            $title = $searchTerm;
            $regions_order_title = ['ss', 'us', 'eu', 'wor', 'jp'];
            foreach($regions_order_title as $region) { foreach ($gameData['noms'] ?? [] as $nom) { if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { $title = $nom['text']; break 2; }}}
            if ($title === $searchTerm && isset($gameData['noms'][0]['text']) && !empty($gameData['noms'][0]['text'])) { $title = $gameData['noms'][0]['text']; }
            $title = trim($title);

                // --- DEBUG SSE ---
                send_sse_message('log', ['message' => "INFO: Recherche pour '$searchTerm' - Jeu identifié: ID ".($ssGame['id'] ?? 'N/A')." ('$title')"]);

            // Description (Priorité user_choice > fr > en)
            send_sse_message('log', ['message' => "INFO: Jeu identifié: ID ".($ssGame['id'] ?? 'N/A')." ('$title')"]);

            // Description
            $description = ''; 
            $lang_order = [$selectedRegion, 'fr', 'en'];
            $lang_order = array_unique($lang_order);

            foreach($lang_order as $lang) { foreach ($gameData['synopsis'] ?? [] as $syn) { if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { $description = $syn['text']; break 2; }}}
            $description = $description ? trim(preg_replace('/\s+/', ' ', $description)) : null;

                $year = null; foreach ($gameData['dates'] ?? [] as $date) { if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { $y = (int)$matches[1]; if ($y >= 1950 && $y < ($year ?? PHP_INT_MAX) ) $year = $y; }}

                $publisher = isset($gameData['editeur']['text']) && !empty($gameData['editeur']['text']) ? trim($gameData['editeur']['text']) : null;

                // 4. Recherche URLs Médias (Jaquette et Vidéo)
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
                                error_log("SSE Process: Cover trouvée ($type, $region): $coverUrl");
                                break 3;
                            }
                        }
                    }
                    if (empty($coverUrl)) {
                        foreach($gameData['medias'] ?? [] as $media) {
                            if (isset($media['type'], $media['url']) && $media['type'] == $type && !empty($media['url'])) {
                                $coverUrl = $media['url'];
                                $coverFormat = $media['format'] ?? 'png';
                                error_log("SSE Process: Cover trouvée ($type, fallback région): $coverUrl");
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
                        error_log("SSE Process: Vidéo trouvée dans jeuInfos pour $title.");
                        break;
                    }
                }

                // 5. Slug et Dossiers de Destination
                $gameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
                if(empty($gameSlug)) $gameSlug = 'game-' . ($ssGameId ?? 'no_id') . '-' . time(); // Utiliser $ssGameId s'il existe
                $gameDirAbsolute = rtrim(ROMS_PATH, '/') . '/' . $consoleSlug . '/' . $gameSlug . '/';
                $imagesDir = $gameDirAbsolute . 'images/';
                $previewDir = $gameDirAbsolute . 'preview/';
                $gameBaseRelativePath = '/roms/' . $consoleSlug . '/' . $gameSlug . '/'; // Chemin relatif pour BDD

                // 6. Vérification Doublon BDD
                try {
                    $stmtCheckGame = $db->prepare("SELECT id FROM games WHERE title = ? AND console_id = ?");
                    $stmtCheckGame->execute([$title, $console_id]);
                    if ($stmtCheckGame->fetch()) {
                        $current_file_errors[] = "Doublon BDD: '".htmlspecialchars($title)."' existe déjà.";
                    }
                } catch(PDOException $e) { $current_file_errors[] = "Err BDD check doublon."; error_log("SSE Process - Game check PDOException for '$title': " . $e->getMessage()); }

                // 7. Création Dossiers
                 if (empty($current_file_errors)) {
                     $directories = [$gameDirAbsolute, $imagesDir, $previewDir];
                     foreach ($directories as $dir) {
                         if (!is_dir($dir)) {
                             if (!@mkdir($dir, 0775, true)) { // Utiliser @ pour éviter les warnings si le dossier est créé entre temps (peu probable)
                                 $current_file_errors[] = "Impossible créer dossier: " . basename($dir);
                                 error_log("SSE Process Error - Failed mkdir: " . $dir);
                                 break; // Inutile de continuer si le dossier principal échoue
                             }
                         } elseif (!is_writable($dir)) {
                              $current_file_errors[] = "Dossier non accessible en écriture: " . basename($dir);
                              error_log("SSE Process Error - Dir not writable: " . $dir);
                         }
                     }
                 }

                // 8. Déplacement ROM / Téléchargement Médias
                $rom_path_relative = ''; $cover_path_relative = ''; $preview_path_relative = '';
                $rom_destination = ''; $cover_destination = ''; $preview_destination = '';
                $cover_download_success = false;

                if (empty($current_file_errors)) {
                    // Déplacement ROM
                    send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_moving_rom')]);
                    $rom_ext = strtolower(pathinfo($current_rom_original_name, PATHINFO_EXTENSION));
                    $rom_filename = $gameSlug . '.' . $rom_ext;
                    $rom_destination = $gameDirAbsolute . $rom_filename;
                    if (!@rename($current_rom_tmp_path, $rom_destination)) { // Tenter rename d'abord
                         if (@copy($current_rom_tmp_path, $rom_destination)) { // Fallback copy
                             @unlink($current_rom_tmp_path); // Supprimer source après copie réussie
                              $rom_path_relative = $gameBaseRelativePath . $rom_filename;
                              error_log("SSE Process - Moved ROM via copy+unlink: $rom_destination");
                         } else {
                             $current_file_errors[] = "Erreur déplacement/copie ROM finale.";
                             error_log("SSE Process Error - Failed to move/copy ROM from $current_rom_tmp_path to $rom_destination");
                             if (file_exists($current_rom_tmp_path)) @unlink($current_rom_tmp_path); // Nettoyer source si échec
                         }
                    } else {
                        $rom_path_relative = $gameBaseRelativePath . $rom_filename; // Chemin relatif pour BDD
                        error_log("SSE Process - Moved ROM via rename: $rom_destination");
                         // Le fichier source $current_rom_tmp_path n'existe plus
                    }

                    // Téléchargement Cover (si ROM OK)
                    if (empty($current_file_errors) && !empty($coverUrl)) {
                        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_dl_cover')]);
                        $cover_ext = strtolower($coverFormat) ?: 'png';
                        $cover_filename = $gameSlug . '.' . $cover_ext;
                        $cover_destination = $imagesDir . $cover_filename;
                        $downloadResult = downloadMedia($coverUrl, $cover_destination);
                        if ($downloadResult['success']) {
                            $cover_path_relative = $gameBaseRelativePath . 'images/' . $cover_filename;
                            $cover_download_success = true;
                            send_sse_message('log', ['message' => __('admin_cover_downloaded')]);
                        } else { $current_file_infos[] = "INFO: " . __('admin_dl_cover_failed') . ": " . ($downloadResult['error'] ?? 'Inconnue'); }
                    }

                    // Téléchargement Preview (si ROM OK)
                    if (empty($current_file_errors)) {
                        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_dl_preview')]);
                        
                        $mediaVideoUrl = $videoUrl ?: 'https://api.screenscraper.fr/api2/mediaVideoJeu.php?systemeid=' . $systemId . '&jeuid=' . ($ssGameId ?? '') . '&media=video';
                        $video_ext = strtolower($videoFormat) ?: 'mp4';
                        $preview_filename = $gameSlug . '.' . $video_ext;
                        $preview_destination = $previewDir . $preview_filename;

                        error_log("SSE Process - Tentative DL Preview via: " . $mediaVideoUrl);
                        $downloadResult = downloadMedia($mediaVideoUrl, $preview_destination);

                        if ($downloadResult['success']) {
                            $preview_path_relative = $gameBaseRelativePath . 'preview/' . $preview_filename;
                            send_sse_message('log', ['message' => __('admin_preview_downloaded')]);
                        } else {
                            if ($downloadResult['error'] == 'NOMEDIA') {
                                $current_file_infos[] = "INFO: Pas de preview vidéo disponible sur SS.";
                            } else {
                                $current_file_infos[] = "INFO: Échec DL preview: " . ($downloadResult['error'] ?? 'Inconnue');
                            }
                            error_log("SSE Process - Preview DL failed for '$current_rom_original_name'. Err: " . ($downloadResult['error'] ?? 'Inconnue'));
                        }
                    }
                    

                } // Fin if(empty($current_file_errors)) après création dossiers

                // 9. Condition et Ajout BDD / Skip
                // Condition : Titre trouvé (peut être identique au terme de recherche), description trouvée, et jaquette téléchargée avec succès.
                $is_complete = !empty($title) && !empty($description) && $cover_download_success;

                if (empty($current_file_errors)) {
                    if ($is_complete) {
                        // Ajout BDD
                        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => __('admin_adding_db')]);
                        try {
                            $stmtInsert = $db->prepare("INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path, sort_order) VALUES (:console_id, :title, :description, :year, :publisher, :cover, :preview, :rom_path, :sort_order)");
                            $executionSuccess = $stmtInsert->execute([
                                 ':console_id' => $console_id, ':title' => $title, ':description' => $description, ':year' => $year, ':publisher' => $publisher, ':cover' => $cover_path_relative, ':preview' => $preview_path_relative ?: null, ':rom_path' => $rom_path_relative, ':sort_order' => 0
                            ]);
                            if($executionSuccess) {
                                send_sse_message('success', ['filename' => htmlspecialchars($current_rom_original_name), 'title' => htmlspecialchars($title)]);
                            } else { $current_file_errors[] = "Insertion BDD échouée (sans exception PDO)."; }
                        } catch (PDOException $e) { $current_file_errors[] = "Erreur BDD insertion: " . $e->getCode() . " - " . $e->getMessage() ; error_log("SSE Process - DB Insert PDOException: " . $e->getMessage());}

                    } else {
                        // Skip (Ajout manuel requis)
                        $missing_items = [];
                        if (empty($description)) $missing_items[] = 'description';
                        if (!$cover_download_success) $missing_items[] = 'jaquette valide';
                        // La condition sur le titre est retirée, il ne sera plus skippé pour cette raison
                        if(empty($missing_items)) $missing_items[] = 'inconnue (vérif logs)'; // Fallback si aucune des raisons ci-dessus n'est vraie
                        $reason = implode(', ', $missing_items);
                        send_sse_message('skipped', [
                            'filename' => htmlspecialchars($current_rom_original_name),
                            'title' => htmlspecialchars($title),
                            'reason' => htmlspecialchars($reason)
                        ]);
                        error_log("SSE Process - Skipped '$title' ($current_rom_original_name). Reason: $reason");
                        // Ne pas supprimer les fichiers ici, ils sont dans le dossier final du jeu
                    }
                } // Fin if(empty($current_file_errors)) pour check BDD

            } // Fin du else où on traite le jeu si $ssGameId existe
        } // Fin du else où on traite le jeu si trouvé par la recherche

    } catch (Exception $e) {
        // Attraper toute exception non gérée pendant le traitement du fichier
        $current_file_errors[] = "Exception inattendue: " . $e->getMessage();
        error_log("SSE Process - Uncaught Exception for file '$current_rom_original_name': " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    // --- Gestion des Erreurs accumulées pour ce fichier ---
    if (!empty($current_file_errors)) {
         // Envoyer un seul message d'erreur récapitulatif
         send_sse_message('error_event', [
            'filename' => htmlspecialchars($current_rom_original_name),
            'message' => htmlspecialchars(implode('; ', $current_file_errors)) // Concaténer les erreurs
         ]);
         // Log serveur détaillé
         error_log("SSE Process - ERRORS for '$current_rom_original_name': " . implode('; ', $current_file_errors));

         // *** Nettoyage en cas d'erreur bloquante ***
         // Supprimer les fichiers/dossiers potentiellement créés dans la destination finale
         if (!empty($rom_destination) && file_exists($rom_destination)) @unlink($rom_destination);
         if (!empty($cover_destination) && file_exists($cover_destination)) @unlink($cover_destination);
         if (!empty($preview_destination) && file_exists($preview_destination)) @unlink($preview_destination);
         // Tenter de supprimer les dossiers vides (ne fonctionnera pas s'ils ne sont pas vides)
         if (!empty($imagesDir) && is_dir($imagesDir)) @rmdir($imagesDir);
         if (!empty($previewDir) && is_dir($previewDir)) @rmdir($previewDir);
         if (!empty($gameDirAbsolute) && is_dir($gameDirAbsolute)) @rmdir($gameDirAbsolute);
         // **Important**: Supprimer aussi le fichier source temporaire en cas d'erreur
         if (file_exists($current_rom_tmp_path)) {
             error_log("SSE Process - Cleaning up source temp file on error: " . $current_rom_tmp_path);
             @unlink($current_rom_tmp_path);
         }

    } else {
        // Si succès ou skipped, le fichier source temporaire devrait avoir été géré (renommé ou supprimé après copie)
        // Vérifier par sécurité s'il existe encore (ne devrait pas arriver si la logique est correcte)
        if (file_exists($current_rom_tmp_path)) {
            error_log("SSE Process - WARNING: Cleaning up unexpected remaining source temp file after success/skip: " . $current_rom_tmp_path);
            @unlink($current_rom_tmp_path);
        }
    }

     // Pause courte pour laisser le temps au navigateur de traiter le message SSE
     usleep(50000); // 50ms

} // Fin de la boucle FOR sur les fichiers

// --- Nettoyage final et message de complétion ---
send_sse_message('log', ['message' => __('admin_final_cleanup') . '...']);

// Supprimer le dossier temporaire parent (ne fonctionnera que s'il est vide)
$temp_upload_dir = sys_get_temp_dir() . '/bulk_upload_' . $token;
// Vérifier s'il est vide avant de tenter de le supprimer
if (is_dir($temp_upload_dir)) {
    $is_dir_empty = !(new \FilesystemIterator($temp_upload_dir))->valid();
    if ($is_dir_empty) {
        if (@rmdir($temp_upload_dir)) {
             error_log("SSE Process - Successfully removed empty temp dir: $temp_upload_dir");
        } else {
             error_log("SSE Process - Failed to remove empty temp dir (permissions?): $temp_upload_dir");
        }
    } else {
         error_log("SSE Process - WARNING: Temp dir not empty after processing, not removed: $temp_upload_dir");
    }
}

// Nettoyer la session
unset($_SESSION[$session_key]);

// Envoyer le message final
send_sse_message('complete', ['message' => __('admin_processing_complete') . '.']);

// Terminer le script
exit();
?>
