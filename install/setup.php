<?php
header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit;
}

$db_host = $data['db_host'];
$db_user = $data['db_user'];
$db_pass = $data['db_pass'];
$db_name = $data['db_name'];

try {
    // 1. Database Connection & Creation
    $tmp_pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tmp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 2. Import Schema (schema.sql propre en priorité, sinon dump complet)
    $sql_file = file_exists('../sql/schema.sql') ? '../sql/schema.sql' : '../sql/retro.sql';
    if (!file_exists($sql_file)) {
        echo json_encode(['success' => false, 'message' => 'Fichier SQL manquant.']);
        exit;
    }

    $sql = file_get_contents($sql_file);
    // Remove delimiters and split by semicolon (naive but works for standard dumps)
    // Better: split by semicolon but ignore inside quotes
    $queries = preg_split("/;+(?=(?:[^'\"]*['\"][^'\"]*['\"])*[^'\"]*$)/", $sql);

    foreach ($queries as $query) {
        $query = trim($query);
        if ($query) {
            $pdo->exec($query);
        }
    }

    // 3. Create Settings Table (if not in retro.sql)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `description` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Save Settings
    $settings_to_save = [
        ['site_name', $data['site_name'], 'Nom du site'],
        ['site_url', $data['site_url'], 'URL du site'],
        ['screenscraper_user', $data['ss_user'], 'Nom d\'utilisateur ScreenScraper.fr'],
        ['screenscraper_pass', $data['ss_pass'], 'Mot de passe ScreenScraper.fr'],
        ['screenscraper_devid', 'enVyZGkxNQ==', 'Dev ID ScreenScraper.fr (base64)'],
        ['screenscraper_devpass', 'eFRKd29PRmpPUUc=', 'Dev Password ScreenScraper.fr (base64)'],
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($settings_to_save as $s) {
        $stmt->execute([$s[0], $s[1], $s[2], $s[1]]);
    }

    // 5. Create Admin Account
    $admin_user = $data['admin_user'];
    $admin_email = $data['admin_email'];
    $admin_pass = password_hash($data['admin_pass'], PASSWORD_DEFAULT);

    // Delete existing admin if any
    $pdo->exec("DELETE FROM users WHERE role = 'admin'");
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$admin_user, $admin_pass, $admin_email]);

    // 6. Écrire config.local.php (identifiants uniquement — non versionné)
    //    Le fichier config.php maintenu dans le dépôt le chargera automatiquement.
    $config_content = "<?php
// config.local.php — généré par l'installeur. NE PAS COMMITTER.
define('DB_HOST', " . var_export($db_host, true) . ");
define('DB_USER', " . var_export($db_user, true) . ");
define('DB_PASS', " . var_export($db_pass, true) . ");
define('DB_NAME', " . var_export($db_name, true) . ");
";

    file_put_contents('../config.local.php', $config_content);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Erreur PDO: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
