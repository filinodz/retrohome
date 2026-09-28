<?php
header('Content-Type: application/json');
require_once __DIR__ . '/_guard.php';

function fail(string $msg, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Sécurité : une installation existante ne peut pas être écrasée par cet endpoint.
if (rh_is_installed()) {
    fail('RetroHome est déjà installé. Supprimez config.local.php pour relancer l\'installation.', 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Méthode non autorisée.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    fail('Données invalides.');
}

$str = function (string $k) use ($data): string {
    return isset($data[$k]) && is_scalar($data[$k]) ? trim((string)$data[$k]) : '';
};
$db_host = $str('db_host') ?: 'localhost';
$db_user = $str('db_user');
$db_pass = isset($data['db_pass']) && is_scalar($data['db_pass']) ? (string)$data['db_pass'] : '';
$db_name = $str('db_name');
$site_name = $str('site_name') ?: 'RetroHome';
$site_url = rtrim($str('site_url'), '/');
$admin_user = $str('admin_user');
$admin_email = $str('admin_email');
$admin_pass = isset($data['admin_pass']) && is_scalar($data['admin_pass']) ? (string)$data['admin_pass'] : '';

if ($db_user === '' || $db_name === '') fail('Utilisateur et nom de la base sont obligatoires.');
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_name)) fail('Nom de base invalide (lettres, chiffres et _ uniquement).');
if (!preg_match('#^https?://#i', $site_url)) fail('URL du site invalide (doit commencer par http:// ou https://).');
if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $admin_user)) fail('Nom d\'administrateur invalide (3 à 32 caractères : lettres, chiffres, _ . -).');
if (strlen($admin_pass) < 8) fail('Le mot de passe administrateur doit contenir au moins 8 caractères.');
if ($admin_email !== '' && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) fail('Adresse e-mail invalide.');

$root = dirname(__DIR__);

try {
    // 1. Connexion + création de la base
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $tmp = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, $opts);
    $tmp->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, $opts);

    // 2. Schéma (tables + consoles + réglages par défaut) puis fonctions sociales
    $files = [$root . '/sql/schema.sql', $root . '/sql/social_migration.sql'];
    if (!file_exists($files[0])) fail('Fichier sql/schema.sql manquant.');
    foreach ($files as $file) {
        if (!file_exists($file)) continue;
        $sql = file_get_contents($file);
        $queries = preg_split("/;+(?=(?:[^'\"]*['\"][^'\"]*['\"])*[^'\"]*$)/", $sql);
        foreach ($queries as $q) {
            // retirer les lignes de commentaire, garder la requête
            $q = trim(preg_replace('/^\s*--.*$/m', '', $q));
            if ($q !== '') $pdo->exec($q);
        }
    }

    // 3. Réglages saisis dans l'assistant
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ([
        ['site_name', $site_name, 'Nom du site'],
        ['site_url', $site_url, 'URL du site'],
        ['screenscraper_user', $str('ss_user'), 'Nom d\'utilisateur ScreenScraper.fr'],
        ['screenscraper_pass', isset($data['ss_pass']) && is_scalar($data['ss_pass']) ? (string)$data['ss_pass'] : '', 'Mot de passe ScreenScraper.fr'],
    ] as $s) {
        $stmt->execute($s);
    }

    // 4. Compte administrateur
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$admin_user]);
    $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')")
        ->execute([$admin_user, password_hash($admin_pass, PASSWORD_DEFAULT), $admin_email]);

    // 5. config.local.php (identifiants uniquement — non versionné)
    $config = "<?php\n// config.local.php — généré par l'installeur. NE PAS COMMITTER.\n"
        . "define('DB_HOST', " . var_export($db_host, true) . ");\n"
        . "define('DB_USER', " . var_export($db_user, true) . ");\n"
        . "define('DB_PASS', " . var_export($db_pass, true) . ");\n"
        . "define('DB_NAME', " . var_export($db_name, true) . ");\n";
    if (@file_put_contents($root . '/config.local.php', $config) === false) {
        fail("Base installée, mais impossible d'écrire config.local.php (droits d'écriture). "
            . "Créez ce fichier à la racine du site avec le contenu suivant :\n\n" . $config);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    fail('Erreur base de données : ' . $e->getMessage());
} catch (Throwable $e) {
    fail('Erreur : ' . $e->getMessage());
}
