<?php
/**
 * Garde d'installation partagée par install/index.php et install/setup.php.
 *
 * Le site est considéré comme INSTALLÉ dès que config.local.php existe, que la
 * base répond et qu'un compte administrateur existe. Dans ce cas l'installeur
 * refuse de s'exécuter : sinon n'importe qui pourrait relancer setup.php et
 * remplacer le compte administrateur.
 */
function rh_is_installed(): bool
{
    $local = dirname(__DIR__) . '/config.local.php';
    if (!file_exists($local)) {
        return false;
    }
    $cfg = rh_read_local_config($local);
    if (!$cfg) {
        return true; // fichier présent mais illisible : on ne prend aucun risque
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . $cfg['DB_HOST'] . ';dbname=' . $cfg['DB_NAME'] . ';charset=utf8mb4',
            $cfg['DB_USER'],
            $cfg['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
    } catch (Throwable $e) {
        // Base injoignable ou vide : on autorise la réinstallation (ex. base supprimée).
        return false;
    }
}

/** Lit les constantes DB_* de config.local.php sans les définir globalement. */
function rh_read_local_config(string $file): ?array
{
    $src = @file_get_contents($file);
    if ($src === false) {
        return null;
    }
    $out = [];
    foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $k) {
        if (preg_match("/define\\(\\s*'" . $k . "'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\)/", $src, $m)) {
            $out[$k] = stripcslashes($m[1]);
        }
    }
    return count($out) === 4 ? $out : null;
}
