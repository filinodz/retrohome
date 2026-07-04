<?php
// Disable output buffering for SSE
if (ob_get_level() > 0) { for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); } }
ob_implicit_flush(true);

require_once '../config.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized access.');
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

set_time_limit(0);

function send_sse_message($event_type, $data) {
    echo "event: " . $event_type . "\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
    @ob_flush();
}

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$indices_str = filter_input(INPUT_GET, 'indices', FILTER_SANITIZE_STRING);
$session_key = 'scan_import_data_' . $token;

if (!$token || !isset($_SESSION[$session_key]) || !$indices_str) {
    send_sse_message('error_event', ['message' => __('admin_invalid_token') ?? 'Invalid session or token.']);
    exit();
}

$indices = explode(',', $indices_str);
$all_files = $_SESSION[$session_key]['files'];
$files_to_process = [];
foreach ($indices as $idx) {
    if (isset($all_files[$idx])) {
        $files_to_process[] = $all_files[$idx];
    }
}

$total_files = count($files_to_process);

// --- ScreenScraper functions (Copied from process_bulk_sse.php for standalone capability) ---

function cleanRomFilename($filename) {
    $cleaned_filename = pathinfo($filename, PATHINFO_FILENAME);
    $cleaned_filename = preg_replace('/[\{\(\[][^\]\)]*[\)\]\]]/', '', $cleaned_filename);
    $cleaned_filename = preg_replace('/^\d+\s*-\s*/', '', $cleaned_filename);
    $cleaned_filename = str_replace(['_', '.'], ' ', $cleaned_filename);
    $cleaned_filename = trim(preg_replace('/\s+/', ' ', $cleaned_filename));
    return $cleaned_filename;
}

function callScreenScraperAPI($url) {
    if (!defined('SCREENSCRAPER_USER') || !defined('SCREENSCRAPER_PASSWORD') || !defined('SCREENSCRAPER_DEV_ID') || !defined('SCREENSCRAPER_DEV_PASSWORD')) {
        return ['error' => "ScreenScraper credentials not defined in config.php", 'http_code' => 500];
    }
    $full_url = $url . (strpos($url, '?') === false ? '?' : '&')
                   . http_build_query([
                       'ssid' => SCREENSCRAPER_USER,
                       'sspassword' => SCREENSCRAPER_PASSWORD,
                       'devid' => SCREENSCRAPER_DEV_ID,
                       'devpassword' => SCREENSCRAPER_DEV_PASSWORD,
                       'softname' => 'RetroHomeScanner',
                       'output' => 'json'
                   ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RetroHomeScanner/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        return ['error' => "SS API Error (Code: $http_code)", 'http_code' => $http_code];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['error' => "Invalid JSON from SS API", 'http_code' => $http_code];
    return $data;
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

function downloadMedia($url, $destination_path, $is_image = false) {
    if (empty($url)) return ['success' => false, 'error' => 'URL missing'];
    $dir = dirname($destination_path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    
    $ch = curl_init($url);
    $fp = fopen($destination_path, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($result && $http_code == 200) return ['success' => true];
    if (file_exists($destination_path)) unlink($destination_path);
    return ['success' => false, 'error' => "HTTP $http_code"];
}

// --- Main Processing Loop ---

for ($i = 0; $i < $total_files; $i++) {
    $file = $files_to_process[$i];
    $filename = $file['filename'];
    $console_id = $file['console_id'];
    
    send_sse_message('progress', [
        'index' => ($i + 1),
        'total' => $total_files,
        'filename' => $filename
    ]);

    try {
        // 1. Fetch Console System ID
        $stmt = $db->prepare("SELECT ss_id, slug FROM consoles WHERE id = ?");
        $stmt->execute([$console_id]);
        $console = $stmt->fetch();
        $systemId = $console['ss_id'];
        $consoleSlug = $console['slug'];

        // 2. Search on ScreenScraper
        $searchTerm = cleanRomFilename($filename);
        $searchUrl = 'https://api.screenscraper.fr/api2/jeuRecherche.php?recherche=' . urlencode($searchTerm) . '&systemeid=' . $systemId;
        $searchResult = callScreenScraperAPI($searchUrl);

        if (isset($searchResult['error']) || empty($searchResult['response']['jeux'])) {
             send_sse_message('skipped', ['filename' => $filename, 'reason' => $searchResult['error'] ?? 'No match found on ScreenScraper']);
             continue;
        }

        $ssGame = findBestLevenMatch($searchResult['response']['jeux'], $searchTerm);
        $ssGameId = $ssGame['id'];

        // 3. Get Game Details
        $infoUrl = 'https://api.screenscraper.fr/api2/jeuInfos.php?gameid=' . $ssGameId;
        $infoResult = callScreenScraperAPI($infoUrl);
        $gameData = $infoResult['response']['jeu'] ?? $ssGame;

        // 4. Extract Data
        $title = $searchTerm;
        foreach(['ss', 'us', 'eu', 'wor', 'jp'] as $region) { 
            foreach ($gameData['noms'] ?? [] as $nom) { 
                if (isset($nom['region']) && $nom['region'] == $region && !empty($nom['text'])) { 
                    $title = $nom['text']; break 2; 
                }
            }
        }
        $title = trim($title);

        // --- DEBUG SSE ---
        send_sse_message('log', ['message' => "INFO: Recherche pour '$searchTerm' - Jeu identifié: ID ".($ssGame['id'] ?? 'N/A')." ('$title')"]);

        $description = '';
        foreach(['fr', 'en'] as $lang) { 
            foreach ($gameData['synopsis'] ?? [] as $syn) { 
                if (isset($syn['langue']) && $syn['langue'] == $lang && !empty($syn['text'])) { 
                    $description = $syn['text']; break 2; 
                }
            }
        }
        $description = trim(preg_replace('/\s+/', ' ', $description));

        $year = null; 
        foreach ($gameData['dates'] ?? [] as $date) { 
            if (isset($date['text']) && preg_match('/^(\d{4})/', $date['text'], $matches)) { 
                $year = (int)$matches[1]; break; 
            }
        }

        $publisher = $gameData['editeur']['text'] ?? null;

        // 5. Media URLs
        $coverUrl = '';
        foreach (['mixrbv1', 'box-2D', 'box-3D', 'screenshot'] as $type) { 
            foreach (['wor', 'ss', 'us', 'eu', 'jp'] as $region) { 
                foreach($gameData['medias'] ?? [] as $media) { 
                    if (isset($media['type'], $media['region'], $media['url']) && $media['type'] == $type && $media['region'] == $region) { 
                        $coverUrl = $media['url']; break 3; 
                    }
                }
            }
        }

        // 6. Ensure Standardized File Structure
        $gameSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        if (empty($gameSlug)) $gameSlug = pathinfo($filename, PATHINFO_FILENAME);
        
        $targetDir = ROMS_PATH . $consoleSlug . '/' . $gameSlug . '/';
        $relativeDir = '/roms/' . $consoleSlug . '/' . $gameSlug . '/';
        
        if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
        
        $currentPath = $file['full_path'];
        $targetPath = $targetDir . $filename;
        $relativeRomPath = $relativeDir . $filename;

        if ($currentPath !== $targetPath) {
            rename($currentPath, $targetPath);
        }

        // 7. Download Media
        $coverPath = '';
        if ($coverUrl) {
            $coverExt = pathinfo(parse_url($coverUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $coverTarget = $targetDir . 'images/' . $gameSlug . '.' . $coverExt;
            if (downloadMedia($coverUrl, $coverTarget, true)['success']) {
                $coverPath = $relativeDir . 'images/' . $gameSlug . '.' . $coverExt;
            }
        }

        // 8. Video Preview (using same logic as bulk add)
        $previewPath = '';
        $previewApiUrl = 'https://www.screenscraper.fr/api2/mediaJeu.php?' . http_build_query([
            'devid' => SCREENSCRAPER_DEV_ID,
            'devpassword' => SCREENSCRAPER_DEV_PASSWORD,
            'softname' => 'RetroHomeScanner',
            'ssid' => SCREENSCRAPER_USER,
            'sspassword' => SCREENSCRAPER_PASSWORD,
            'romnom' => $filename,
            'systemeid' => $systemId,
            'media' => 'video'
        ]);
        
        $videoTarget = $targetDir . 'preview/' . $gameSlug . '.mp4';
        $videoData = @file_get_contents($previewApiUrl);
        if ($videoData && !str_starts_with($videoData, 'Problème') && stripos($videoData, 'NOMEDIA') === false) {
            if (!is_dir(dirname($videoTarget))) mkdir(dirname($videoTarget), 0775, true);
            file_put_contents($videoTarget, $videoData);
            $previewPath = $relativeDir . 'preview/' . $gameSlug . '.mp4';
        }

        // 9. Insert into Database
        $stmt = $db->prepare("INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$console_id, $title, $description, $year, $publisher, $coverPath, $previewPath, $relativeRomPath]);

        send_sse_message('success', [
            'filename' => $filename,
            'title' => $title
        ]);

    } catch (Exception $e) {
        send_sse_message('error_event', [
            'filename' => $filename,
            'message' => $e->getMessage()
        ]);
    }

    usleep(500000); // 0.5s pause to avoid hitting SS API too hard
}

unset($_SESSION[$session_key]);
send_sse_message('complete', ['message' => __('admin_processing_complete') ?? 'Processing complete.']);
exit();
