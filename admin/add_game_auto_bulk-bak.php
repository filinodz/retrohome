<?php
require_once '../config.php'; // Chemin relatif
// Démarre la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Initialisation et Sécurité ---
set_time_limit(600); // Augmenter le temps max d'exécution
// ini_set('display_errors', 1); // Débogage
// error_reporting(E_ALL);     // Débogage
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_file_uploads', '50');

$errors = []; // Erreurs générales
$results = ['success' => [], 'skipped_incomplete' => [], 'errors' => []];
$console_id_selected = filter_input(INPUT_GET, 'console_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT);
$consoles = [];
$noConsolesConfigured = false;
$processing_done = false;

// --- Vérification Admin (inchangé) ---
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
     error_log("Admin Auto Add Bulk - User check error: " . $e->getMessage());
     die("Erreur interne lors de la vérification des permissions.");
}

// --- Récupération des consoles (inchangé) ---
try {
    $consoles = $db->query("SELECT id, name, ss_id, slug FROM consoles WHERE ss_id IS NOT NULL AND ss_id > 0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($consoles)) {
        $noConsolesConfigured = true;
    }
} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des consoles depuis la base de données.";
    error_log("Admin Auto Add Bulk - Error fetching consoles: " . $e->getMessage());
}

// --- Fonctions utilitaires (nettoyage filename, appel API - inchangées) ---
function cleanRomFilename($filename) {
    $filename = pathinfo($filename, PATHINFO_FILENAME);
    $filename = preg_replace('/[\{\(\[][^\]\)]*[\)\]\]]/', '', $filename);
    $filename = preg_replace('/^\d+\s*-\s*/', '', $filename);
    $filename = str_replace(['_', '.'], ' ', $filename);
    $filename = trim(preg_replace('/\s+/', ' ', $filename));
    return $filename;
}

function callScreenScraperAPI($url) {
    if (!defined('SCREENSCRAPER_USER') || !defined('SCREENSCRAPER_PASSWORD') || !defined('SCREENSCRAPER_DEV_ID') || !defined('SCREENSCRAPER_DEV_PASSWORD')) {
        return ['error' => "Identifiants ScreenScraper non définis dans config.php", 'http_code' => 500];
    }
    $full_url = $url . (strpos($url, '?') === false ? '?' : '&')
                   . 'ssid=' . urlencode(SCREENSCRAPER_USER)
                   . '&sspassword=' . urlencode(SCREENSCRAPER_PASSWORD)
                   . '&devid=' . urlencode(SCREENSCRAPER_DEV_ID)
                   . '&devpassword=' . urlencode(SCREENSCRAPER_DEV_PASSWORD)
                   . '&softname=RetroHomeAdminBulk&output=json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (compatible; PHP cURL)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    if ($ch !== false) { curl_close($ch); }
    if ($curl_error) { return ['error' => "Erreur cURL: " . $curl_error, 'http_code' => $http_code]; }
    if (strpos($url, 'mediaVideoJeu.php') !== false) {
         if ($http_code == 200) {
             if (strpos($response, 'OK') !== false) return ['status' => 'OK_CHECKSUM'];
             elseif (strpos($response, 'NOMEDIA') !== false) return ['error' => 'NOMEDIA', 'http_code' => 404];
             elseif (strpos($response, '<?xml') === 0 || strpos($response, '<html') === 0 || empty(trim($response))) {
                   return ['error' => "Réponse inattendue de l'API vidéo (non-JSON/vide). Code: $http_code", 'http_code' => $http_code];
             }
             return ['is_media_content' => true, 'http_code' => $http_code, 'content' => $response];
         } else { return ['error' => "Erreur API vidéo ScreenScraper (Code: $http_code)", 'http_code' => $http_code]; }
    }
    if ($http_code >= 400) {
         $error_data = json_decode($response, true);
         $error_message = $error_data['message'] ?? $response;
         if (stripos($response, 'maximum threads') !== false) $error_message = "Limite de requêtes ScreenScraper atteinte. Réessayez plus tard.";
         elseif ($http_code === 401 || stripos($response, 'Erreur de login') !== false) $error_message = "Identifiants ScreenScraper invalides ou problème d'authentification API.";
        return ['error' => "Erreur API ScreenScraper (Code: $http_code): " . strip_tags($error_message), 'http_code' => $http_code];
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { return ['error' => "Réponse API invalide (JSON): " . json_last_error_msg(), 'http_code' => $http_code]; }
    if (isset($data['ssuser']['connect']) && $data['ssuser']['connect'] == 0) { return ['error' => "Échec de connexion à ScreenScraper (user/pass incorrects ?).", 'http_code' => 401]; }
    return $data;
}

// Fonction downloadMedia (inchangée depuis la dernière correction)
function downloadMedia($url, $destination_path, $is_image = false) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
        error_log("DownloadMedia: URL invalide ou vide fournie: " . print_r($url, true));
        return ['success' => false, 'error' => 'URL invalide ou manquante (vérification filter_var)'];
    }
    $ch = null; $fp = null;
    try {
        $dir = dirname($destination_path);
        if (!is_dir($dir)) {
             if (!mkdir($dir, 0775, true)) { error_log("DownloadMedia: Impossible de créer le répertoire: " . $dir); return ['success' => false, 'error' => 'Impossible de créer le répertoire destination']; }
        }
        if (!is_writable($dir)) { error_log("DownloadMedia: Répertoire non accessible en écriture: " . $dir); return ['success' => false, 'error' => 'Répertoire destination non accessible en écriture']; }

        if (strpos($url, 'mediaVideoJeu.php') !== false) {
            $api_response = callScreenScraperAPI($url);
            if (isset($api_response['error'])) { if ($api_response['error'] === 'NOMEDIA') return ['success' => false, 'error' => 'NOMEDIA']; error_log("DownloadMedia: Erreur API vidéo SS: " . $api_response['error'] . " URL: " . $url); return ['success' => false, 'error' => $api_response['error']]; }
            elseif (isset($api_response['is_media_content']) && $api_response['is_media_content']) {
                if (file_put_contents($destination_path, $api_response['content']) === false) { error_log("DownloadMedia: Échec écriture vidéo: " . $destination_path); return ['success' => false, 'error' => "Impossible d'écrire le fichier vidéo."]; }
                clearstatcache(true, $destination_path);
                if (!file_exists($destination_path) || filesize($destination_path) == 0) { error_log("DownloadMedia: Fichier vidéo écrit mais vide pour $url."); @unlink($destination_path); return ['success' => false, 'error' => 'Fichier vidéo téléchargé vide.']; }
                return ['success' => true];
            } else { error_log("DownloadMedia: Réponse API vidéo inattendue: " . $url . " Réponse: " . json_encode($api_response)); return ['success' => false, 'error' => 'Réponse inattendue de l\'API vidéo.']; }
        } else {
            $ch = curl_init($url);
            if ($ch === false) { error_log("DownloadMedia: Échec curl_init() pour URL: " . $url); return ['success' => false, 'error' => "Impossible d'initialiser la session cURL."]; }
            $fp = fopen($destination_path, 'wb');
            if (!$fp) { error_log("DownloadMedia: Impossible ouvrir fichier écriture: " . $destination_path); if ($ch !== false) { curl_close($ch); } return ['success' => false, 'error' => "Impossible d'ouvrir fichier destination."]; }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeAdminBulk/1.0 (compatible; PHP cURL)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $success_curl = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            if ($ch !== false) { curl_close($ch); }
            if (isset($fp) && is_resource($fp)) { fclose($fp); }
            if (!$success_curl || $http_code >= 400) { error_log("DownloadMedia: Échec DL $url. Code: $http_code. cURL Error: $curl_error"); @unlink($destination_path); return ['success' => false, 'error' => "Erreur $http_code lors du téléchargement."]; }
            clearstatcache(true, $destination_path);
            if (!file_exists($destination_path) || filesize($destination_path) == 0) { error_log("DownloadMedia: Fichier téléchargé vide ou inexistant pour $url."); @unlink($destination_path); return ['success' => false, 'error' => 'Fichier téléchargé vide ou non créé.']; }
            if ($is_image) {
                $mime_type = mime_content_type($destination_path);
                $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if ($mime_type === false || !in_array($mime_type, $allowed_image_types)) {
                    error_log("DownloadMedia: Fichier téléchargé pour $url n'est pas une image valide (Type détecté: $mime_type). Destination: $destination_path");
                    @unlink($destination_path);
                    $expected_ext = pathinfo($destination_path, PATHINFO_EXTENSION);
                    return ['success' => false, 'error' => "Le fichier téléchargé (.$expected_ext) n'est pas une image valide (détecté: $mime_type). Contenu du serveur peut-être incorrect?"];
                }
                error_log("DownloadMedia: Image vérifiée avec succès ($mime_type) pour $destination_path");
            }
            return ['success' => true];
        }
    } catch (Exception $e) {
        error_log("DownloadMedia: Exception DL $url: " . $e->getMessage());
        if (isset($ch) && $ch !== false) { @curl_close($ch); }
        if (isset($fp) && is_resource($fp)) { @fclose($fp); }
        if (!empty($destination_path) && file_exists($destination_path)) { @unlink($destination_path); }
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// --- Traitement du formulaire POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noConsolesConfigured) {
    $processing_done = true;
    $console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT);
    $rom_files_data = $_FILES['rom'] ?? null;

    // Validation globale initiale (inchangée)
    if (!$console_id) $errors[] = "Veuillez sélectionner une console.";
    $uploaded_files_count = 0;
    if (isset($rom_files_data['name']) && is_array($rom_files_data['name'])) {
        foreach ($rom_files_data['error'] as $key => $error_code) {
             if ($error_code === UPLOAD_ERR_OK && !empty($rom_files_data['size'][$key])) {
                $uploaded_files_count++;
            } elseif ($error_code !== UPLOAD_ERR_NO_FILE) {
                 $phpFileUploadErrors = []; // Map si nécessaire
                 $filename_for_error = isset($rom_files_data['name'][$key]) ? "'" . htmlspecialchars($rom_files_data['name'][$key]) . "'" : "(nom inconnu)";
                 $errors[] = "Erreur upload initiale fichier $filename_for_error (Code: $error_code).";
            }
        }
    }
    if ($uploaded_files_count === 0 && empty($errors)) {
         $errors[] = "Aucun fichier ROM valide n'a été téléversé.";
    }

    // Récupération infos console
    $console = null;
    if ($console_id && empty($errors)) {
        try {
            $stmtConsole = $db->prepare("SELECT id, name, slug, ss_id FROM consoles WHERE id = ?");
            $stmtConsole->execute([$console_id]);
            $console = $stmtConsole->fetch(PDO::FETCH_ASSOC);
            if (!$console || empty($console['ss_id'])) { $errors[] = "Console invalide ou non configurée pour ScreenScraper."; $console = null; }
        } catch (PDOException $e) {
            $errors[] = "Erreur DB infos console.";
            // CORRECTION error_log
            error_log("Admin Auto Add Bulk - Fetch console PDOException: " . $e->getMessage());
            $console = null;
        }
    }

    // --- Boucle de Traitement des Fichiers ---
    if (empty($errors) && $console && $uploaded_files_count > 0) {
        $num_files = count($rom_files_data['name']);
        $consoleSlug = $console['slug'];
        $systemId = $console['ss_id'];

        for ($i = 0; $i < $num_files; $i++) {
            // Infos fichier courant (inchangé)
            $current_rom_original_name = $rom_files_data['name'][$i];
            $current_rom_tmp_name = $rom_files_data['tmp_name'][$i];
            $current_rom_error_code = $rom_files_data['error'][$i];
            $current_rom_size = $rom_files_data['size'][$i];
            $current_file_errors = [];
            $current_file_infos = [];

            // Validation upload fichier courant (inchangé)
            if ($current_rom_error_code !== UPLOAD_ERR_OK || $current_rom_size == 0) {
                if ($current_rom_error_code === UPLOAD_ERR_NO_FILE) continue;
                $phpFileUploadErrors = []; // Map si nécessaire
                $error_message = $phpFileUploadErrors[$current_rom_error_code] ?? "Erreur inconnue (Code: $current_rom_error_code)";
                 if ($current_rom_size == 0 && $current_rom_error_code === UPLOAD_ERR_OK) $error_message = "Fichier vide.";
                $results['errors'][] = ['filename' => $current_rom_original_name, 'errors' => ["Erreur Upload: " . $error_message]];
                error_log("Admin Auto Add Bulk - Skip file '$current_rom_original_name': $error_message");
                continue;
            }

            error_log("Admin Auto Add Bulk: Traitement Fichier [" . ($i+1) . "/$num_files]: " . $current_rom_original_name);
            if ($i > 0) { sleep(1); }

            // Appel API et extraction données
            $searchTerm = cleanRomFilename($current_rom_original_name);
            $searchUrl = 'https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=' . urlencode($searchTerm) . '&systemeid=' . $systemId;
            $searchResult = callScreenScraperAPI($searchUrl);

            if (isset($searchResult['error'])) { $current_file_errors[] = "Erreur recherche SS: " . $searchResult['error']; }
            elseif (empty($searchResult['response']['jeux']) || !isset($searchResult['response']['jeux'][0]['id']) || empty($searchResult['response']['jeux'][0]['id'])) { $current_file_errors[] = "Aucun jeu trouvé sur SS pour '" . htmlspecialchars($searchTerm) . "'."; }
            else {
                // Récupération infos détaillées (inchangé)
                $ssGame = $searchResult['response']['jeux'][0];
                $ssGameId = $ssGame['id'];
                $gameData = $ssGame;
                $infoUrl = 'https://api.screenscraper.fr/api2/jeuInfos.php?gameid=' . $ssGameId;
                usleep(500000);
                $infoResult = callScreenScraperAPI($infoUrl);
                if (!isset($infoResult['error']) && isset($infoResult['response']['jeu'])) { $gameData = $infoResult['response']['jeu']; }
                elseif (isset($infoResult['error'])) { $current_file_infos[] = "INFO: Erreur récupération détails SS: " . $infoResult['error']; error_log("Admin Auto Add Bulk: Erreur détails SS pour ID $ssGameId (Fichier: $current_rom_original_name): " . $infoResult['error']); }
                else { $current_file_infos[] = "INFO: Impossible de récupérer les détails complets."; error_log("Admin Auto Add Bulk: Réponse inattendue de jeuInfos pour ID $ssGameId (Fichier: $current_rom_original_name)"); }

                // Extraction données (titre, description, année, éditeur - inchangé)
                $title = $searchTerm;
                $regions_order_title = ['ss', 'us', 'eu', 'wor', 'jp'];
                foreach($regions_order_title as $region) { foreach ($gameData['noms'] ?? [] as $nom) { if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { $title = $nom['text']; break 2; }}}
                if ($title === $searchTerm && isset($gameData['noms'][0]['text']) && !empty($gameData['noms'][0]['text'])) { $title = $gameData['noms'][0]['text']; }
                $title = trim($title);

                $description = ''; $lang_order = ['fr', 'en'];
                foreach($lang_order as $lang) { foreach ($gameData['synopsis'] ?? [] as $syn) { if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { $description = $syn['text']; break 2; }}}
                $description = $description ? trim(preg_replace('/\s+/', ' ', $description)) : null;

                $year = null; foreach ($gameData['dates'] ?? [] as $date) { if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { $y = (int)$matches[1]; if ($y >= 1950 && $y < ($year ?? PHP_INT_MAX) ) $year = $y; }}

                $publisher = isset($gameData['editeur']['text']) && !empty($gameData['editeur']['text']) ? trim($gameData['editeur']['text']) : null;

                // Jaquette (inchangé pour recherche URL)
                $coverUrl = ''; $mediaTypes = ['mixrbv1', 'box-2D', 'box-3D', 'screenshot']; $regions = ['wor', 'ss', 'us', 'eu', 'jp'];
                foreach ($mediaTypes as $type) { foreach ($regions as $region) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['region'], $media['url']) && $media['type'] == $type && $media['region'] == $region && !empty($media['url'])) { $coverUrl = $media['url']; error_log("Auto Add: Cover trouvée ($type, $region): $coverUrl"); break 3; }}}
                    if (empty($coverUrl)) { foreach($gameData['medias'] ?? [] as $media) { if (isset($media['type'], $media['url']) && $media['type'] == $type && !empty($media['url'])) { $coverUrl = $media['url']; error_log("Auto Add: Cover trouvée ($type, fallback région): $coverUrl"); break 2; }}}
                    if (!empty($coverUrl)) break;
                }
                if(empty($coverUrl)) { $current_file_infos[] = "INFO: Aucune jaquette trouvée sur SS."; }

                // Création slug et dossiers (inchangé)
                $gameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
                if(empty($gameSlug)) $gameSlug = 'game-' . $ssGameId . '-' . time();
                $gameDirAbsolute = rtrim(ROMS_PATH, '/') . '/' . $consoleSlug . '/' . $gameSlug . '/';
                $imagesDir = $gameDirAbsolute . 'images/';
                $previewDir = $gameDirAbsolute . 'preview/';
                $gameBaseRelativePath = '/roms/' . $consoleSlug . '/' . $gameSlug . '/';

                // Vérification doublon jeu
                try {
                    $stmtCheckGame = $db->prepare("SELECT id FROM games WHERE title = ? AND console_id = ?");
                    $stmtCheckGame->execute([$title, $console_id]);
                    if ($stmtCheckGame->fetch()) { $current_file_errors[] = "Doublon: Un jeu nommé '".htmlspecialchars($title)."' existe déjà."; }
                } catch(PDOException $e) {
                    $current_file_errors[] = "Erreur BDD vérification doublon.";
                    // CORRECTION error_log
                    error_log("Admin Auto Add Bulk - Game check PDOException for '$title': " . $e->getMessage());
                }

                // Création répertoires (inchangé)
                if (empty($current_file_errors)) {
                    $directories = [$gameDirAbsolute, $imagesDir, $previewDir];
                    foreach ($directories as $dir) {
                        if (!is_dir($dir)) { if (!mkdir($dir, 0775, true)) { $current_file_errors[] = "Impossible créer répertoire : " . $dir; error_log("Admin Auto Add Bulk: Échec création dossier: " . $dir); break; } else { error_log("Admin Auto Add Bulk: Dossier créé: " . $dir); } }
                        elseif (!is_writable($dir)) { $current_file_errors[] = "Répertoire non accessible : " . $dir; error_log("Admin Auto Add Bulk: Dossier non écrivable: " . $dir); }
                    }
                }

                // Téléchargement & Déplacement
                $rom_path_relative = '';
                $cover_path_relative = '';
                $preview_path_relative = '';
                $rom_destination = ''; $cover_destination = ''; $preview_destination = '';
                $cover_download_success = false;

                if (empty($current_file_errors)) {
                    // 1. ROM
                    $rom_ext = strtolower(pathinfo($current_rom_original_name, PATHINFO_EXTENSION));
                    $rom_filename = $gameSlug . '.' . $rom_ext;
                    $rom_destination = $gameDirAbsolute . $rom_filename;
                    if (!move_uploaded_file($current_rom_tmp_name, $rom_destination)) {
                        $current_file_errors[] = "Erreur déplacement ROM.";
                        // CORRECTION error_log
                        error_log("Admin Auto Add Bulk: Échec move_uploaded_file ROM. Source: " . $current_rom_tmp_name . " Dest: " . $rom_destination);
                    } else {
                        $rom_path_relative = $gameBaseRelativePath . $rom_filename;
                        error_log("Admin Auto Add Bulk: ROM déplacée: " . $rom_destination);
                    }

                    // 2. Cover (avec validation)
                    if (empty($current_file_errors) && !empty($coverUrl)) {
                        $cover_ext = strtolower(pathinfo(parse_url($coverUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                        if (empty($cover_ext) || strlen($cover_ext) > 4) { $cover_ext = 'jpg'; error_log("Admin Auto Add Bulk: Extension de jaquette invalide/manquante dans URL '$coverUrl'. Utilisation de '$cover_ext'."); }
                        $cover_filename = $gameSlug . '.' . $cover_ext;
                        $cover_destination = $imagesDir . $cover_filename;
                        error_log("Admin Auto Add Bulk: Tentative DL Cover '$coverUrl' vers '$cover_destination'");
                        $downloadResult = downloadMedia($coverUrl, $cover_destination, true); // true pour is_image
                        if ($downloadResult['success']) {
                             $cover_path_relative = $gameBaseRelativePath . 'images/' . $cover_filename;
                             $cover_download_success = true;
                             error_log("Admin Auto Add Bulk: Cover téléchargée et validée: " . $cover_path_relative);
                        } else {
                             $current_file_infos[] = "INFO: Échec DL/Validation jaquette (" . ($downloadResult['error'] ?? 'Inconnue') . "). URL: $coverUrl";
                             error_log("Admin Auto Add Bulk: Échec DL/Validation Cover '$title'. Erreur: " . ($downloadResult['error'] ?? 'Inconnue') . ". URL: $coverUrl");
                        }
                    } elseif(empty($coverUrl) && empty($current_file_errors)) {
                         $current_file_infos[] = "INFO: Aucune URL de jaquette trouvée sur ScreenScraper.";
                    }

                    // 3. Preview
                     if (empty($current_file_errors)) {
                         $mediaVideoUrl = 'https://api.screenscraper.fr/api2/mediaVideoJeu.php?systemeid=' . $systemId . '&jeuid=' . $ssGameId;
                         $preview_filename = $gameSlug . '.mp4';
                         $preview_destination = $previewDir . $preview_filename;
                         error_log("Admin Auto Add Bulk: Tentative DL preview jeu ID {$ssGameId} (Fichier: $current_rom_original_name).");
                         usleep(500000);
                         $downloadResult = downloadMedia($mediaVideoUrl, $preview_destination);
                         if ($downloadResult['success']) {
                             $preview_path_relative = $gameBaseRelativePath . 'preview/' . $preview_filename;
                             error_log("Admin Auto Add Bulk: Preview téléchargée pour '$title'.");
                         } else {
                            if ($downloadResult['error'] == 'NOMEDIA') {
                                $current_file_infos[] = "INFO: Aucune preview vidéo disponible.";
                                // CORRECTION error_log
                                error_log("Admin Auto Add Bulk: Preview NOMEDIA for game ID $ssGameId (Fichier: $current_rom_original_name)");
                            } else {
                                $current_file_infos[] = "INFO: Échec DL preview vidéo (" . ($downloadResult['error'] ?? 'Inconnue') . ").";
                                // CORRECTION error_log
                                error_log("Admin Auto Add Bulk: Échec DL preview jeu ID $ssGameId (Fichier: $current_rom_original_name). Erreur: " . ($downloadResult['error'] ?? 'Inconnue'));
                             }
                         }
                     }
                } // Fin if (empty($current_file_errors)) pour DL/move

                // Condition pour Insertion BDD (inchangée)
                $is_complete = !empty($title) && $title !== $searchTerm && !empty($description) && $cover_download_success;

                if (empty($current_file_errors)) {
                    if ($is_complete) {
                        // AJOUT AUTOMATIQUE EN BDD
                        try {
                            $stmtInsert = $db->prepare("INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path, sort_order) VALUES (:console_id, :title, :description, :year, :publisher, :cover, :preview, :rom_path, :sort_order)");
                            $executionSuccess = $stmtInsert->execute([ /* ... bindings ... */
                                ':console_id' => $console_id, ':title' => $title, ':description' => $description, ':year' => $year, ':publisher' => $publisher, ':cover' => $cover_path_relative, ':preview' => $preview_path_relative ?: null, ':rom_path' => $rom_path_relative, ':sort_order' => 0
                            ]);
                            if($executionSuccess) {
                                $results['success'][] = ['filename' => $current_rom_original_name, 'title' => $title, 'infos' => $current_file_infos];
                                error_log("Admin Auto Add Bulk: Succès ajout BDD pour '$title' (Fichier: $current_rom_original_name)");
                            } else {
                                 $current_file_errors[] = "L'insertion BDD a échoué sans erreur PDO explicite.";
                                 // CORRECTION error_log
                                 error_log("Admin Auto Add Bulk - Final DB Insert Failed (execute false): Game '$title' File '$current_rom_original_name'");
                            }
                        } catch (PDOException $e) {
                            $errorInfo = $stmtInsert ? $stmtInsert->errorInfo() : $db->errorInfo(); // Obtenir plus d'infos
                            $sqlErrorCode = $e->getCode() ?: ($errorInfo[1] ?? 'N/A');
                            $sqlErrorMessage = $e->getMessage() ?: ($errorInfo[2] ?? 'N/A');
                            $current_file_errors[] = "Erreur finale BDD (Code: $sqlErrorCode).";
                            // CORRECTION error_log
                            error_log("Admin Auto Add Bulk - Final DB Insert PDOException: Game '$title' File '$current_rom_original_name' - SQLSTATE[{$sqlErrorCode}] {$sqlErrorMessage}");
                        }
                    } else {
                        // NE PAS AJOUTER EN BDD - MARQUER POUR AJOUT MANUEL
                        $missing_items = [];
                        if (empty($description)) $missing_items[] = 'description';
                        if (!$cover_download_success) $missing_items[] = 'jaquette valide';
                        if ($title === $searchTerm) $missing_items[] = 'titre non confirmé par API';
                        if(empty($missing_items)) $missing_items[] = 'raison inconnue (vérifier logs)'; // Fallback

                        $results['skipped_incomplete'][] = [
                            'filename' => $current_rom_original_name, 'title' => $title,
                            'reason' => "Infos manquantes: " . implode(', ', $missing_items),
                            'rom_path' => $rom_path_relative, 'slug' => $gameSlug, 'infos' => $current_file_infos
                        ];
                         error_log("Admin Auto Add Bulk: Skipped DB insert for '$title' (File: $current_rom_original_name). Reason: " . implode(', ', $missing_items));
                    }
                } // Fin if (empty($current_file_errors)) avant BDD check

            } // Fin else (jeu trouvé sur SS)

            // Gestion finale des erreurs pour CE fichier (inchangée)
            if (!empty($current_file_errors)) {
                 $results['errors'][] = [ 'filename' => $current_rom_original_name, 'errors' => array_merge($current_file_errors, $current_file_infos) ];
                 error_log("Admin Auto Add Bulk: ERREURS rencontrées pour '$current_rom_original_name': " . implode('; ', $current_file_errors));
                 if (!empty($rom_destination) && file_exists($rom_destination)) @unlink($rom_destination);
                 if (!empty($cover_destination) && file_exists($cover_destination)) @unlink($cover_destination);
                 if (!empty($preview_destination) && file_exists($preview_destination)) @unlink($preview_destination);
                 if (!empty($imagesDir) && is_dir($imagesDir)) @rmdir($imagesDir);
                 if (!empty($previewDir) && is_dir($previewDir)) @rmdir($previewDir);
                 if (!empty($gameDirAbsolute) && is_dir($gameDirAbsolute)) @rmdir($gameDirAbsolute);
            }

        } // Fin de la boucle FOR pour chaque fichier

    } // Fin if (pas d'erreurs globales && console OK && fichiers uploadés)

} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

?>
<!DOCTYPE html>
<html lang="fr">
<!-- Head et Body HTML/CSS/JS (inchangés par rapport à la version précédente) -->
<head>
    <meta charset="UTF-8">
    <title>Ajout Auto Jeux en Masse - Administration</title>
    <link rel="icon" type="image/png" href="../assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/style.css">
    <link rel="stylesheet" href="admin_style.css">
     <style>
        .results-section { margin-top: 2rem; padding: 1.5rem; background-color: rgba(0, 0, 0, 0.2); border-radius: 8px; border: 1px solid #4a5568; }
        .results-section h3 { font-family: 'Orbitron', sans-serif; font-weight: 700; margin-bottom: 1rem; font-size: 1.25rem; }
        .result-item { margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #2d3748; }
        .result-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .result-filename { font-weight: 600; color: #cbd5e0; }
        .result-info { font-style: italic; color: #a0aec0; font-size: 0.875rem; }
    </style>
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Ajouter des Jeux (Auto - En Masse)</h1>
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-errors bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded relative mb-6 animate__animated animate__shakeX">
                 <strong class="font-bold">Erreur Générale:</strong>
                <ul><?php foreach ($errors as $error): ?><li><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if ($noConsolesConfigured): ?>
             <div class="bg-yellow-800 border border-yellow-600 text-yellow-100 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Configuration requise!</strong> <span class="block sm:inline">Aucune console n'a d'ID ScreenScraper configuré...</span>
            </div>
         <?php endif; ?>

        <?php if ($processing_done): ?>
            <div class="results-section animate__animated animate__fadeIn">
                <h3 class="text-text-accent">Résultats du Traitement en Masse</h3>

                <?php if (!empty($results['success'])): ?>
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-green-400 mb-2"><i class="fas fa-check-circle mr-2"></i>Jeux Ajoutés Automatiquement (<?= count($results['success']) ?>)</h4>
                        <ul class="list-disc list-inside space-y-2">
                            <?php foreach ($results['success'] as $success): ?>
                                <li class="result-item">
                                    <span class="result-filename"><?= htmlspecialchars($success['filename']) ?></span>
                                    <span class="text-green-300"> -> Ajouté comme "<?= htmlspecialchars($success['title']) ?>"</span>
                                    <?php if (!empty($success['infos'])): ?>
                                        <ul class="list-disc list-inside ml-4 mt-1">
                                            <?php foreach ($success['infos'] as $info): ?><li class="text-blue-300 text-sm"><i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars(str_replace('INFO: ', '', $info)) ?></li><?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['skipped_incomplete'])): ?>
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-yellow-400 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Jeux Préparés (Ajout Manuel Requis - <?= count($results['skipped_incomplete']) ?>)</h4>
                        <p class="text-sm text-yellow-200 mb-3">Ces jeux n'ont pas été ajoutés car des informations essentielles manquaient (description, jaquette valide...). Le fichier ROM a été placé dans le dossier approprié. Vous pouvez les ajouter manuellement.</p>
                        <ul class="list-disc list-inside space-y-2">
                            <?php foreach ($results['skipped_incomplete'] as $skipped): ?>
                                <li class="result-item">
                                    <span class="result-filename"><?= htmlspecialchars($skipped['filename']) ?></span>
                                    <span class="text-yellow-300"> -> Préparé comme "<?= htmlspecialchars($skipped['title']) ?>"</span>
                                     <p class="text-xs text-yellow-400 ml-4">Raison: <?= htmlspecialchars($skipped['reason']) ?></p>
                                     <p class="text-xs text-gray-400 ml-4">Slug: <?= htmlspecialchars($skipped['slug']) ?> (ROM dans: <?= htmlspecialchars(dirname($skipped['rom_path'])) ?>)</p>
                                     <?php if (!empty($skipped['infos'])): ?>
                                        <ul class="list-disc list-inside ml-4 mt-1">
                                            <?php foreach ($skipped['infos'] as $info): ?><li class="text-blue-300 text-sm"><i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars(str_replace('INFO: ', '', $info)) ?></li><?php endforeach; ?>
                                        </ul>
                                     <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                         <div class="mt-4 text-center">
                            <a href="add_game.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" class="form-button add-button">
                                <i class="fas fa-plus-circle mr-2"></i>Accéder à l'ajout manuel
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['errors'])): ?>
                    <div>
                        <h4 class="text-lg font-semibold text-red-400 mb-2"><i class="fas fa-times-circle mr-2"></i>Erreurs Bloquantes (<?= count($results['errors']) ?>)</h4>
                        <ul class="list-disc list-inside space-y-3">
                            <?php foreach ($results['errors'] as $error_item): ?>
                                <li class="result-item">
                                    <span class="result-filename"><?= htmlspecialchars($error_item['filename']) ?></span>
                                    <ul class="list-disc list-inside ml-4 mt-1">
                                        <?php foreach ($error_item['errors'] as $errMsg): ?>
                                            <?php
                                                $isInfo = strpos($errMsg, 'INFO:') === 0;
                                                $message = $isInfo ? substr($errMsg, 5) : $errMsg;
                                                $iconClass = $isInfo ? 'fas fa-info-circle text-blue-400' : 'fas fa-exclamation-triangle text-red-400';
                                                $textClass = $isInfo ? 'text-blue-300' : 'text-red-300';
                                            ?>
                                            <li class="<?= $textClass ?> text-sm"><i class="<?= $iconClass ?> mr-1"></i> <?= htmlspecialchars($message) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                 <?php if (empty($results['success']) && empty($results['skipped_incomplete']) && empty($results['errors']) && empty($errors)): ?>
                     <p class="text-text-secondary">Aucun fichier n'a été traité.</p>
                <?php endif; ?>

                 <div class="mt-6 text-center">
                     <a href="add_game_auto_bulk.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" class="form-button back-button"><i class="fas fa-plus mr-2"></i> Ajouter d'autres ROMs</a>
                      <a href="index.php" class="form-button cancel-button ml-4"><i class="fas fa-list mr-2"></i> Retour à la liste</a>
                 </div>

            </div>
        <?php endif; ?>


        <?php if (!$processing_done): ?>
            <div class="admin-form-container animate__animated animate__fadeInUp">
                 <p class="text-text-secondary mb-6 text-sm">
                    Sélectionnez la console et téléchargez un ou plusieurs fichiers ROM. Les jeux avec titre, description et jaquette valides seront ajoutés automatiquement. Les autres seront préparés pour un ajout manuel.
                </p>
                <form action="add_game_auto_bulk.php" method="post" enctype="multipart/form-data" id="auto-add-bulk-form" novalidate>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        <div class="form-group md:col-span-1">
                            <label for="console_id">Console :<span class="text-red-500">*</span></label>
                            <select id="console_id" name="console_id" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                                <option value="" disabled <?= empty($console_id_selected) ? 'selected' : '' ?>>-- Sélectionner --</option>
                                <?php foreach ($consoles as $c): ?> <option value="<?= $c['id'] ?>" <?= ($console_id_selected == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?> <?= !empty($c['ss_id']) ? '(ID:'.$c['ss_id'].')' : '' ?></option> <?php endforeach; ?>
                            </select>
                            <?php if ($noConsolesConfigured): ?> <p class="text-xs text-yellow-400 mt-1">Aucune console configurée.</p> <?php endif; ?>
                        </div>
                        <div class="form-group md:col-span-1">
                            <label for="rom">Fichiers ROM :<span class="text-red-500">*</span></label>
                            <input type="file" id="rom" name="rom[]" multiple accept=".zip,.sfc,.smc,.fig,.bin,.gba,.gbc,.gb,.nes,.pce,.md,.mgd,.sms,.gg,.col,.ngp,.ngc,.ws,.wsc,.7z,.iso,.cue,.chd" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                            <p class="text-xs text-text-secondary mt-1">Sélectionnez un ou plusieurs fichiers.</p>
                            <p class="text-xs text-yellow-400 mt-1">Limites serveur: <?= ini_get('max_file_uploads') ?> fichiers, <?= ini_get('upload_max_filesize') ?>/fichier, <?= ini_get('post_max_size') ?> total.</p>
                        </div>
                    </div>
                     <div id="loading-message" class="loading-message" style="display: none;">
                          <i class="fas fa-sync fa-spin text-4xl mb-4"></i> <p class="text-lg">Traitement en cours...</p> <p class="text-sm text-text-secondary mt-2">Recherche, téléchargement et ajout... Veuillez patienter.</p>
                     </div>
                    <div class="form-actions mt-8">
                        <a href="index.php" class="form-button cancel-button"><i class="fas fa-times mr-2"></i>Annuler</a>
                        <button type="submit" class="form-button submit-button" id="submit-auto-add-bulk" <?= $noConsolesConfigured ? 'disabled' : '' ?>><i class="fas fa-cloud-upload-alt mr-2"></i>Lancer l'ajout</button>
                    </div>
                </form>
            </div>
         <?php endif; ?>
    </div>

    <script>
        const form = document.getElementById('auto-add-bulk-form');
        const submitButton = document.getElementById('submit-auto-add-bulk');
        const loadingMessage = document.getElementById('loading-message');
        const consoleSelect = document.getElementById('console_id');
        const romInput = document.getElementById('rom');

        if (form && submitButton && loadingMessage && consoleSelect && romInput) {
            form.addEventListener('submit', function(event) {
                 let valid = true; let errorMsg = '';
                 consoleSelect.style.borderColor = ''; romInput.style.borderColor = '';
                 const existingErrorDiv = form.parentNode.querySelector('.form-client-errors');
                 if (existingErrorDiv) { existingErrorDiv.remove(); }
                 if (!consoleSelect.value) { consoleSelect.style.borderColor = 'red'; valid = false; errorMsg += '<li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>Veuillez sélectionner une console.</li>'; }
                 if (romInput.files.length === 0) { romInput.style.borderColor = 'red'; valid = false; errorMsg += '<li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>Veuillez sélectionner au moins un fichier ROM.</li>'; }
                 const maxFiles = <?= (int)ini_get('max_file_uploads') ?>;
                 if (romInput.files.length > maxFiles) { errorMsg += `<li><i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i>Attention: ${romInput.files.length} fichiers sélectionnés, limite serveur ${maxFiles}.</li>`; }
                 if (!valid) {
                     event.preventDefault();
                    let errorDiv = document.createElement('div'); errorDiv.className = 'form-errors form-client-errors animate__animated animate__shakeX mb-6';
                    errorDiv.innerHTML = `<p class="font-bold mb-2">Erreurs Formulaire:</p><ul>${errorMsg}</ul>`;
                    form.parentNode.insertBefore(errorDiv, form); errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                     return;
                 }
                submitButton.disabled = true; submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Traitement...';
                loadingMessage.style.display = 'flex'; loadingMessage.classList.add('animate__animated', 'animate__fadeIn');
                form.style.display = 'none';
            });
        } else { console.error("Erreur JS: Eléments formulaire manquants."); }
    </script>
</body>
</html>