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
    send_sse_message('error_event', ['filename' => 'N/A', 'message' => 'Token de session invalide ou traitement déjà terminé/expiré.']);
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
        send_sse_message('error_event', ['filename' => 'N/A', 'message' => 'Erreur fatale: Connexion BDD impossible dans le processus SSE.']);
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
 * Télécharge un fichier média (image ou vidéo via URL directe).
 * Modifié pour gérer spécifiquement mediaJeu.php en vérifiant la réponse avant écriture.
 * @param string $url URL source du média.
 * @param string $destination_path Chemin de sauvegarde local.
 * @param bool $is_image Indique s'il faut valider comme une image.
 * @return array ['success' => bool, 'error' => string|null]
 */
function downloadMedia($url, $destination_path, $is_image = false) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
        error_log("DownloadMedia SSE: URL invalide: " . print_r($url, true));
        return ['success' => false, 'error' => 'URL invalide/manquante'];
    }
    $ch = null; $fp = null;
    try {
        // Création/Vérification dossier destination (inchangé)
        $dir = dirname($destination_path);
        if (!is_dir($dir)) {
             if (!@mkdir($dir, 0775, true)) { error_log("DownloadMedia SSE: Échec création dir: " . $dir); return ['success' => false, 'error' => 'Échec création dossier']; }
        }
        if (!is_writable($dir)) { error_log("DownloadMedia SSE: Dir non accessible: " . $dir); return ['success' => false, 'error' => 'Dossier non accessible']; }

        // *** NOUVELLE LOGIQUE pour mediaJeu.php ***
        if (strpos($url, 'mediaJeu.php') !== false && !$is_image) { // Traiter spécifiquement l'URL de l'API vidéo par nom
            error_log("DownloadMedia SSE: Traitement spécifique mediaJeu.php pour $url");
            $ch = curl_init($url);
            if ($ch === false) { error_log("DownloadMedia SSE: Échec curl_init pour mediaJeu: " . $url); return ['success' => false, 'error' => "Échec init cURL (mediaJeu)."]; }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Récupérer en mémoire
            curl_setopt($ch, CURLOPT_HEADER, false); // Pas besoin des en-têtes dans la réponse
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180); // Timeout 3 minutes
            curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (PHP cURL)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_FAILONERROR, false); // Important: Ne pas échouer sur 4xx/5xx

            $response_content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            if ($ch !== false) { curl_close($ch); }

            // 1. Gérer erreurs cURL fondamentales
            if ($curl_error && $http_code == 0) {
                error_log("DownloadMedia SSE: Erreur cURL (mediaJeu) $url. Err: $curl_error");
                return ['success' => false, 'error' => "Erreur cURL (mediaJeu): " . $curl_error];
            }

            // 2. Gérer erreurs HTTP
            if ($http_code >= 400) {
                if ($http_code == 404) {
                    error_log("DownloadMedia SSE: mediaJeu.php a retourné 404 pour $url. NOMEDIA.");
                    return ['success' => false, 'error' => 'NOMEDIA']; // 404 est traité comme NOMEDIA
                } elseif ($http_code == 401) { return ['success' => false, 'error' => 'Non autorisé (401)'];}
                  elseif ($http_code == 403) { return ['success' => false, 'error' => 'Accès interdit (403)'];}
                  elseif ($http_code == 429) { return ['success' => false, 'error' => 'Trop de requêtes (429)'];}
                else {
                     error_log("DownloadMedia SSE: Erreur HTTP $http_code pour mediaJeu.php $url.");
                     // On pourrait essayer de lire la réponse pour voir si c'est "NOMEDIA" même avec un autre code
                     if ($response_content !== false && stripos(trim($response_content), 'NOMEDIA') !== false) {
                         return ['success' => false, 'error' => 'NOMEDIA'];
                     }
                    return ['success' => false, 'error' => "Erreur HTTP $http_code (mediaJeu)"];
                }
            }

            // 3. Vérifier le contenu de la réponse (même si HTTP 200)
            if ($response_content === false) {
                 error_log("DownloadMedia SSE: curl_exec a retourné false sans erreur cURL explicite (mediaJeu) pour $url.");
                 return ['success' => false, 'error' => 'Erreur lecture réponse (mediaJeu)'];
            }
            $trimmed_response = trim($response_content);
            if (stripos($trimmed_response, 'NOMEDIA') !== false) {
                error_log("DownloadMedia SSE: 'NOMEDIA' détecté dans la réponse pour $url.");
                return ['success' => false, 'error' => 'NOMEDIA'];
            }
            if (strlen($trimmed_response) < 100) { // Seuil arbitraire, une vidéo est > 100 octets
                 // Vérifier si ça ressemble à du HTML/XML/Texte simple d'erreur
                 if (stripos($trimmed_response, '<html') !== false || stripos($trimmed_response, '<?xml') !== false || stripos($trimmed_response, 'erreur') !== false) {
                      error_log("DownloadMedia SSE: Réponse non-vidéo suspecte (HTML/XML/Erreur?) pour $url. Contenu: " . substr($trimmed_response, 0, 200));
                      return ['success' => false, 'error' => 'Réponse serveur invalide (HTML/XML?)'];
                 }
            }
             // Vérifier aussi le Content-Type rapporté par le serveur
             $server_content_type = explode(';', $content_type ?? '')[0];
             if (strpos($server_content_type, 'video/') !== 0 && strpos($server_content_type, 'application/octet-stream') !== 0) { // Accepter video/* ou application/octet-stream comme potentiellement valides
                  error_log("DownloadMedia SSE: Content-Type inattendu pour mediaJeu.php $url. Type: " . $server_content_type);
                  // On pourrait être plus strict et retourner une erreur ici, ou juste logguer et continuer. Essayons de continuer.
                  // return ['success' => false, 'error' => 'Type de contenu serveur invalide: ' . $server_content_type];
             }

            // 4. Si tout semble OK, écrire le contenu en mémoire dans le fichier
             error_log("DownloadMedia SSE: Tentative d'écriture du contenu vidéo pour $url vers $destination_path");
            if (file_put_contents($destination_path, $response_content) === false) {
                error_log("DownloadMedia SSE: Échec file_put_contents pour mediaJeu: " . $destination_path);
                return ['success' => false, 'error' => "Échec écriture fichier vidéo"];
            }

            // 5. Vérifier la taille du fichier écrit
            clearstatcache(true, $destination_path);
            if (!file_exists($destination_path) || filesize($destination_path) == 0) {
                error_log("DownloadMedia SSE: Fichier vidéo écrit mais vide pour $url.");
                @unlink($destination_path);
                return ['success' => false, 'error' => 'Fichier vidéo écrit vide'];
            }

            error_log("DownloadMedia SSE: Vidéo écrite avec succès pour $url. Taille: " . filesize($destination_path));
            return ['success' => true]; // Succès pour mediaJeu.php

        } else {
            // *** Téléchargement Classique (Images ou autres URLs) ***
            // Cette partie est pour les images ou toute autre URL non spécifique
            error_log("DownloadMedia SSE: Traitement standard pour $url");
            $ch = curl_init($url);
            if ($ch === false) { /* ... erreur curl_init ... */ return ['success' => false, 'error' => "Échec init cURL."]; }
            $fp = fopen($destination_path, 'wb');
            if (!$fp) { /* ... erreur fopen ... */ if ($ch !== false) { curl_close($ch); } return ['success' => false, 'error' => "Échec ouverture fichier dest."]; }

            curl_setopt($ch, CURLOPT_FILE, $fp); // Écrire directement dans le fichier
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (PHP cURL)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_FAILONERROR, false); // Important

            $success_curl = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); // Récupérer le Content-Type

            if ($ch !== false) { curl_close($ch); }
            if (isset($fp) && is_resource($fp)) { fclose($fp); } // Fermer avant vérification

            // Gérer erreurs cURL
            if ($curl_error && $http_code == 0) { error_log("DownloadMedia SSE: Erreur cURL $url. Err: $curl_error"); @unlink($destination_path); return ['success' => false, 'error' => "Erreur cURL: " . $curl_error]; }

             // Vérifier si fichier existe après fermeture
             clearstatcache(true, $destination_path);
             if (!file_exists($destination_path)) {
                 if ($http_code == 404) { return ['success' => false, 'error' => 'NOMEDIA']; } // 404 = NOMEDIA
                 else { error_log("DownloadMedia SSE: Fichier non créé $url. Code: $http_code"); return ['success' => false, 'error' => "Fichier non créé (Code: $http_code)."]; }
             }
             $file_size = filesize($destination_path);

            // Gérer erreurs HTTP (sauf 404 géré ci-dessus)
            if ($http_code >= 400 && $http_code != 404) {
                 error_log("DownloadMedia SSE: Échec DL $url. Code HTTP: $http_code."); @unlink($destination_path);
                 if ($http_code == 401) return ['success' => false, 'error' => 'Non autorisé (401)'];
                 if ($http_code == 403) return ['success' => false, 'error' => 'Accès interdit (403)'];
                 if ($http_code == 429) return ['success' => false, 'error' => 'Trop de requêtes (429)'];
                 // Essayer de détecter NOMEDIA même avec un autre code d'erreur
                  if ($file_size < 20 && trim(file_get_contents($destination_path)) === 'NOMEDIA') {
                       error_log("DownloadMedia SSE: Détecté 'NOMEDIA' dans fichier malgré code $http_code pour $url.");
                       @unlink($destination_path); return ['success' => false, 'error' => 'NOMEDIA'];
                  }
                 return ['success' => false, 'error' => "Erreur $http_code DL."];
             }

             // Vérifier si fichier vide
             if ($file_size == 0) { error_log("DownloadMedia SSE: Fichier DL vide (0 octet): $url."); @unlink($destination_path); return ['success' => false, 'error' => 'Fichier DL vide.']; }

            // Vérification spécifique images (si demandé)
            if ($is_image) {
                 if (!extension_loaded('fileinfo')) { error_log("DownloadMedia SSE: Ext 'fileinfo' manquante pour vérif MIME."); }
                 else {
                    $mime_type = mime_content_type($destination_path);
                    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $server_content_type = explode(';', $content_type ?? '')[0];
                    if ($mime_type === false || (!in_array($mime_type, $allowed_image_types) && !in_array($server_content_type, $allowed_image_types)) ) {
                        error_log("DownloadMedia SSE: Fichier DL pour $url pas image valide (MIME: $mime_type, Header: $server_content_type)."); @unlink($destination_path); $expected_ext = pathinfo($destination_path, PATHINFO_EXTENSION); return ['success' => false, 'error' => "Fichier DL (.$expected_ext) pas image valide (détecté: $mime_type/$server_content_type)."];
                    }
                    error_log("DownloadMedia SSE: Image vérifiée OK (MIME: $mime_type, Header: $server_content_type) pour $destination_path");
                }
            }
            // Si on arrive ici pour un téléchargement standard, c'est un succès
            return ['success' => true];
        }
    } catch (Exception $e) { // Gestion des exceptions globales
        error_log("DownloadMedia SSE: Exception DL $url: " . $e->getMessage());
        if (isset($ch) && $ch !== false) { @curl_close($ch); }
        if (isset($fp) && is_resource($fp)) { @fclose($fp); }
        if (!empty($destination_path) && file_exists($destination_path)) { @unlink($destination_path); }
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
     send_sse_message('log', ['message' => 'Console chargée: ' . htmlspecialchars($console['name'])]);

} catch (PDOException $e) {
    send_sse_message('error_event', ['filename' => 'N/A', 'message' => 'Erreur BDD récupération console: ' . $e->getMessage()]);
    error_log("SSE Process Error - Fetch console failed: " . $e->getMessage());
    unset($_SESSION[$session_key]); exit();
}

// --- Boucle Principale de Traitement ---
send_sse_message('log', ['message' => 'Début du traitement de ' . $total_files . ' fichier(s)...']);

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
        'status' => 'Initialisation'
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
            send_sse_message('log', ['message' => 'Pause API (1s)...']);
            sleep(1);
        }

        // 1. Recherche API ScreenScraper
        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => 'Recherche API']);
        $searchTerm = cleanRomFilename($current_rom_original_name);
        send_sse_message('log', ['message' => "Terme recherche pour '" . htmlspecialchars($current_rom_original_name) . "': '" . htmlspecialchars($searchTerm) . "'"]);

        $searchUrl = 'https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=' . urlencode($searchTerm) . '&systemeid=' . $systemId;
        $searchResult = callScreenScraperAPI($searchUrl);

        // Gestion des erreurs de recherche
        if (isset($searchResult['error'])) {
            $current_file_errors[] = "Recherche SS: " . $searchResult['error'];
            // Si erreur 429 (Too Many Requests), faire une pause plus longue avant de continuer
            if (($searchResult['http_code'] ?? 0) == 429) {
                send_sse_message('log', ['message' => 'Erreur 429 (Trop de requêtes). Pause de 5 secondes...']);
                sleep(5);
            }
        } elseif (empty($searchResult['response']['jeux'])) { // Utiliser empty() est plus sûr
            $current_file_errors[] = "Aucun jeu trouvé sur SS pour '" . htmlspecialchars($searchTerm) . "'.";
        } else {
            // 2. Jeu trouvé -> Infos détaillées
            send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => 'Infos API']);
            $ssGame = $searchResult['response']['jeux'][0];
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
                    send_sse_message('log', ['message' => 'Détails du jeu récupérés.']);
                } elseif (isset($infoResult['error'])) { $current_file_infos[] = "INFO: Err détails SS: " . $infoResult['error']; }
                else { $current_file_infos[] = "INFO: Pas détails complets."; }

                // 3. Extraction données (Titre, Desc, Année, Editeur)
                $title = $searchTerm; // Fallback
                $regions_order_title = ['ss', 'us', 'eu', 'wor', 'jp'];
                foreach($regions_order_title as $region) { foreach ($gameData['noms'] ?? [] as $nom) { if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { $title = $nom['text']; break 2; }}}
                if ($title === $searchTerm && isset($gameData['noms'][0]['text']) && !empty($gameData['noms'][0]['text'])) { $title = $gameData['noms'][0]['text']; }
                $title = trim($title);

                $description = ''; $lang_order = ['fr', 'en'];
                foreach($lang_order as $lang) { foreach ($gameData['synopsis'] ?? [] as $syn) { if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { $description = $syn['text']; break 2; }}}
                $description = $description ? trim(preg_replace('/\s+/', ' ', $description)) : null;

                $year = null; foreach ($gameData['dates'] ?? [] as $date) { if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { $y = (int)$matches[1]; if ($y >= 1950 && $y < ($year ?? PHP_INT_MAX) ) $year = $y; }}

                $publisher = isset($gameData['editeur']['text']) && !empty($gameData['editeur']['text']) ? trim($gameData['editeur']['text']) : null;

                // 4. Recherche URL Jaquette
                $coverUrl = ''; $mediaTypes = ['mixrbv1', 'box-2D', 'box-3D', 'screenshot']; $regions = ['wor', 'ss', 'us', 'eu', 'jp'];
                foreach ($mediaTypes as $type) { foreach ($regions as $region) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['region'], $media['url']) && $media['type'] == $type && $media['region'] == $region && !empty($media['url'])) { $coverUrl = $media['url']; error_log("SSE Process: Cover trouvée ($type, $region): $coverUrl"); break 3; }}}
                    if (empty($coverUrl)) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['url']) && $media['type'] == $type && !empty($media['url'])) { $coverUrl = $media['url']; error_log("SSE Process: Cover trouvée ($type, fallback région): $coverUrl"); break 2; }}}
                    if (!empty($coverUrl)) break;
                }
                if(empty($coverUrl)) { $current_file_infos[] = "INFO: Pas de jaquette SS."; }

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
                    send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => 'Déplacement ROM']);
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
                        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => 'DL Jaquette']);
                        $cover_ext = strtolower(pathinfo(parse_url($coverUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                        if (empty($cover_ext) || strlen($cover_ext) > 5 || !in_array($cover_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $cover_ext = 'jpg'; } // Extension simple et valide
                        $cover_filename = $gameSlug . '.' . $cover_ext;
                        $cover_destination = $imagesDir . $cover_filename;
                        $downloadResult = downloadMedia($coverUrl, $cover_destination, true); // true pour valider l'image
                        if ($downloadResult['success']) {
                            $cover_path_relative = $gameBaseRelativePath . 'images/' . $cover_filename;
                            $cover_download_success = true;
                            send_sse_message('log', ['message' => 'Jaquette téléchargée.']);
                        } else { $current_file_infos[] = "INFO: Échec DL/Val jaquette: " . ($downloadResult['error'] ?? 'Inconnue'); }
                    }

                    // Téléchargement Preview (si ROM OK) - Utilisation de mediaJeu.php
                    if (empty($current_file_errors)) {
                        send_sse_message('progress', [
                            'index' => ($i + 1),
                            'total' => $total_files,
                            'filename' => htmlspecialchars($current_rom_original_name),
                            'status' => 'DL Preview'
                        ]);
                    
                        $previewApiUrl = 'https://www.screenscraper.fr/api2/mediaJeu.php?' . http_build_query([
                            'devid'       => SCREENSCRAPER_DEV_ID,
                            'devpassword' => SCREENSCRAPER_DEV_PASSWORD,
                            'softname'    => 'RetroHomeAdminBulk',
                            'ssid'        => SCREENSCRAPER_USER,
                            'sspassword'  => SCREENSCRAPER_PASSWORD,
                            'romnom'      => $current_rom_original_name,
                            'systemeid'   => $systemId,
                            'media'       => 'video'
                        ]);
                    
                        $preview_filename = $gameSlug . '.mp4';
                        $preview_destination = $previewDir . $preview_filename;
                    
                        error_log("SSE Process - Tentative DL Preview via mediaJeu.php pour: " . $current_rom_original_name);
                        usleep(500000); // Petite pause
                    
                        $response = file_get_contents($previewApiUrl);
                    
                        // Vérifie si c'est une erreur texte renvoyée au lieu d'une vidéo
                        if ($response !== false && !str_starts_with($response, 'Problème') && stripos($response, 'NOMEDIA') === false) {
                            file_put_contents($preview_destination, $response);
                            $preview_path_relative = $gameBaseRelativePath . 'preview/' . $preview_filename;
                            send_sse_message('log', ['message' => 'Preview téléchargée.']);
                        } else {
                            // Gestion de l'erreur renvoyée par ScreenScraper
                            $errorMsg = trim($response);
                            if (stripos($errorMsg, 'NOMEDIA') !== false) {
                                $current_file_infos[] = "INFO: Pas de preview vidéo (via mediaJeu).";
                            } else {
                                $current_file_infos[] = "INFO: Échec DL preview (via mediaJeu): " . $errorMsg;
                            }
                            error_log("SSE Process - Preview DL failed for '$current_rom_original_name'. Err: " . $errorMsg);
                        }
                    }
                    

                } // Fin if(empty($current_file_errors)) après création dossiers

                // 9. Condition et Ajout BDD / Skip
                // Condition : Titre trouvé (peut être identique au terme de recherche), description trouvée, et jaquette téléchargée avec succès.
                $is_complete = !empty($title) && !empty($description) && $cover_download_success;

                if (empty($current_file_errors)) {
                    if ($is_complete) {
                        // Ajout BDD
                        send_sse_message('progress', ['index' => ($i + 1), 'total' => $total_files, 'filename' => htmlspecialchars($current_rom_original_name), 'status' => 'Ajout BDD']);
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
send_sse_message('log', ['message' => 'Nettoyage final...']);

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
send_sse_message('complete', ['message' => 'Traitement terminé.']);

// Terminer le script
exit();
?>