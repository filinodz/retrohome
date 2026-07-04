<?php
session_start();

// Charger les identifiants locaux (non versionnés) générés par l'installeur.
// Voir config.example.php pour un modèle. Ne jamais committer config.local.php.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
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
    if (strpos($_SERVER['PHP_SELF'], 'install/') === false) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        $path = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
        header("Location: {$protocol}://{$host}{$path}install/index.php");
        exit();
    }
}

// Load Settings System
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
if (!defined('SCREENSCRAPER_USER')) {
    define('SCREENSCRAPER_USER', $settings->get('screenscraper_user'));
    define('SCREENSCRAPER_PASSWORD', $settings->get('screenscraper_pass'));
    define('SCREENSCRAPER_DEV_ID', base64_decode($settings->get('screenscraper_devid', 'enVyZGkxNQ==')));
    define('SCREENSCRAPER_DEV_PASSWORD', base64_decode($settings->get('screenscraper_devpass', 'eFRKd29PRmpPUUc=')));
}

// Site Configuration
if (!defined('SITE_URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $default_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $host . str_replace(basename($_SERVER['PHP_SELF'] ?? ''), '', $_SERVER['PHP_SELF'] ?? '');
    define('SITE_URL', rtrim($settings->get('site_url', $default_url), '/'));
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