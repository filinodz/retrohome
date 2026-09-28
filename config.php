<?php
// Session : cookie HttpOnly + SameSite=Lax (bloque les requêtes intersites cachées -> CSRF)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Jeton anti-CSRF de la session (à joindre aux actions sensibles de l'admin). */
function rh_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function rh_csrf_valid($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Charger les identifiants locaux (non versionnés) générés par l'installeur.
// Voir config.example.php pour un modèle. Ne jamais committer config.local.php.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Chemin URL de la racine du site (ex. "/retrohome"), quel que soit le dossier d'installation.
function rh_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $root = str_replace('\\', '/', __DIR__);
    $name = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== '' && stripos($script, $root) === 0) {
        $rel = substr($script, strlen($root));           // ex. "/admin/index.php"
        if ($rel !== '' && substr($name, -strlen($rel)) === $rel) {
            return rtrim(substr($name, 0, -strlen($rel)), '/');
        }
    }
    return rtrim(dirname($name), '/\\');
}

// Pas encore installé (aucun config.local.php) -> assistant d'installation.
if (!defined('DB_NAME') && PHP_SAPI !== 'cli'
    && strpos(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), '/install/') === false) {
    header('Location: ' . rh_base_path() . '/install/');
    exit();
}

// Valeurs par défaut si aucune configuration locale n'est fournie (dev)
if (!defined('DB_HOST')) { define('DB_HOST', 'localhost'); }
if (!defined('DB_USER')) { define('DB_USER', 'root'); }
if (!defined('DB_PASS')) { define('DB_PASS', ''); }
if (!defined('DB_NAME')) { define('DB_NAME', 'retro'); }

// Base Path
define('BASE_PATH', __DIR__);
define('ROMS_PATH', BASE_PATH . '/roms/');

// Database Connection
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    if (PHP_SAPI === 'cli') {
        throw $e;
    }
    if (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? ''), '/install/') === false) {
        // Base configurée mais injoignable : message clair plutôt qu'une page blanche.
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>RetroHome</title>'
           . '<div style="font-family:system-ui;max-width:560px;margin:80px auto;padding:24px;border-radius:14px;background:#15131f;color:#eee">'
           . '<h2>Base de données injoignable</h2><p>Vérifiez que MySQL/MariaDB est démarré et que les identifiants de '
           . '<code>config.local.php</code> sont corrects.</p><p style="opacity:.6;font-size:13px">'
           . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
           . '<p><a style="color:#a78bfa" href="' . htmlspecialchars(rh_base_path(), ENT_QUOTES) . '/install/">Relancer l&#39;installation</a></p></div>';
        exit();
    }
}

// Load Settings System
require_once BASE_PATH . '/includes/media_security.php';
require_once BASE_PATH . '/includes/Settings.php';
require_once BASE_PATH . '/includes/ThemeManager.php';
$settings = new Settings($db);
$themeManager = new ThemeManager($db);
require_once BASE_PATH . '/includes/LanguageManager.php';
$languageManager = new LanguageManager($db);
$currentLang = $languageManager->getCurrent();
$isRTL = $languageManager->isRTL();

// Global Translation Helper
function __($key) {
    global $languageManager;
    return $languageManager->get($key);
}

// Define legacy constants for backward compatibility
// NOTE de sécurité : les identifiants ScreenScraper (compte membre + compte dev)
// sont lus depuis la BASE DE DONNÉES (table settings), qui n'est PAS versionnée.
// On ne stocke JAMAIS d'identifiant réel dans ce fichier committé.
// Renseignez vos identifiants (compte + identifiants développeur) dans Admin › Réglages.
if (!defined('SCREENSCRAPER_USER')) {
    define('SCREENSCRAPER_USER', $settings->get('screenscraper_user'));
    define('SCREENSCRAPER_PASSWORD', $settings->get('screenscraper_pass'));
    define('SCREENSCRAPER_DEV_ID', base64_decode((string)$settings->get('screenscraper_devid', '')));
    define('SCREENSCRAPER_DEV_PASSWORD', base64_decode((string)$settings->get('screenscraper_devpass', '')));
    // Mot de passe DEBUG développeur (optionnel) — pour le mode debug de l'API
    // (forceupdate, forcelevel…). Vide par défaut ; défini en base si fourni.
    define('SCREENSCRAPER_DEV_DEBUG_PASSWORD', base64_decode($settings->get('screenscraper_devdebugpass', '')));
}

// Site Configuration
// SITE_URL est adaptatif : on conserve le CHEMIN de base configuré (ex. /retrohome)
// mais on utilise l'HÔTE de la requête courante. Ainsi le site fonctionne aussi bien
// via http://localhost/... que via http://<IP-LAN>/... (indispensable pour le NetPlay
// inter-machines et pour éviter les blocages cross-origin sur les fetch/fonts).
if (!defined('SITE_URL')) {
    $configured = rtrim($settings->get('site_url', ''), '/');

    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        // Chemin de base : depuis le réglage si présent, sinon déduit de la requête.
        $basePath = '';
        if ($configured !== '') {
            $basePath = parse_url($configured, PHP_URL_PATH) ?: '';
        } else {
            $basePath = str_replace(basename($_SERVER['PHP_SELF'] ?? ''), '', $_SERVER['PHP_SELF'] ?? '');
        }
        define('SITE_URL', rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . '/' . trim($basePath, '/'), '/'));
    } else {
        // Contexte CLI / sans requête : on retombe sur le réglage.
        define('SITE_URL', $configured ?: 'http://localhost');
    }
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', $settings->get('site_name', 'RetroHome'));
}
if (!defined('SITE_THEME')) {
    define('SITE_THEME', $themeManager->getActiveTheme());
}

// Global Helper for Theme Assets
function get_theme_asset($file) {
    global $themeManager;
    return $themeManager->getThemeAsset($file);
}