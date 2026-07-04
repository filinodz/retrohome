<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// 1. Extensions de ROM valides
$rom_extensions = ['zip', '7z', 'rar', 'sfc', 'smc', 'fig', 'bin', 'gba', 'gbc', 'gb', 'nes', 'pce', 'md', 'mgd', 'sms', 'gg', 'col', 'ngp', 'ngc', 'ws', 'wsc', 'iso', 'cue', 'chd'];

// --- Actions ---
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_game' && isset($_POST['id'])) {
        // Suppression sécurisée (déjà implémentée dans delete_game.php, on peut rediriger ou réutiliser la logique)
        $game_id = (int)$_POST['id'];
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM ratings WHERE game_id = ?")->execute([$game_id]);
            $db->prepare("DELETE FROM favorites WHERE game_id = ?")->execute([$game_id]);
            $db->prepare("DELETE FROM collection_games WHERE game_id = ?")->execute([$game_id]);
            $db->prepare("DELETE FROM games WHERE id = ?")->execute([$game_id]);
            $db->commit();
            header('Location: clean_roms?msg=Game deleted');
            exit();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }
    
    if ($_POST['action'] === 'delete_file' && isset($_POST['path'])) {
        $path = '../' . $_POST['path'];
        if (file_exists($path) && is_file($path)) {
            unlink($path);
        }
        header('Location: clean_roms?msg=File deleted');
        exit();
    }

    if ($_POST['action'] === 'reorganize' && isset($_POST['id'], $_POST['new_path'], $_POST['old_full_path'])) {
        $game_id = (int)$_POST['id'];
        $new_relative_path = $_POST['new_path'];
        $old_full_path = $_POST['old_full_path']; // Chemin ABSOLU sur disque (ou relatif au script)
        $new_full_path = '../' . ltrim($new_relative_path, '/');

        // Créer les dossiers parents
        $dir = dirname($new_full_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Déplacer le fichier
        if (file_exists($old_full_path) && rename($old_full_path, $new_full_path)) {
            $stmt = $db->prepare("UPDATE games SET rom_path = ? WHERE id = ?");
            $stmt->execute([$new_relative_path, $game_id]);
            header('Location: clean_roms?msg=Game reorganized');
            exit();
        }
    }
}

// --- Analyse ---
$roms_dir = '../roms/';
$results = [
    'missing_files' => [], // Vraiment introuvables
    'displaced_files' => [], // Trouvés ailleurs ou mal nommés
    'untracked_files' => []  // ROMs non référencées
];

// 1. Analyse de la base de données
$stmt = $db->query("SELECT g.id, g.title, g.rom_path, c.slug as console_slug FROM games g JOIN consoles c ON g.console_id = c.id");
while ($game = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_path = '../' . ltrim($game['rom_path'], '/');
    
    if (!file_exists($current_path)) {
        // Tentative de détection intelligente
        $filename = basename($game['rom_path']);
        $gameSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $game['title'])));
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $suggested_path = '/roms/' . $game['console_slug'] . '/' . $gameSlug . '/rom.' . $ext;

        // Chercher le fichier ailleurs dans le dossier console
        $console_dir = '../roms/' . $game['console_slug'] . '/';
        $found_path = null;

        if (is_dir($console_dir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($console_dir));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getFilename() === $filename) {
                    $found_path = $file->getPathname();
                    break;
                }
            }
        }

        if ($found_path) {
            $game['found_at'] = str_replace('\\', '/', $found_path);
            $game['suggested_path'] = $suggested_path;
            $results['displaced_files'][] = $game;
        } else {
            $results['missing_files'][] = $game;
        }
    }
}

// 2. Analyse des fichiers non suivis (Filtrage média)
function scanRoms($dir, &$untracked, $db, $rom_extensions) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . $file;
        if (is_dir($path)) {
            scanRoms($path . '/', $untracked, $db, $rom_extensions);
        } else {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $rom_extensions)) continue; // Ignorer images/videos

            $db_path = str_replace('../', '', str_replace('\\', '/', $path));
            $stmt = $db->prepare("SELECT id FROM games WHERE rom_path = ? OR rom_path = ?");
            $stmt->execute([$db_path, '/' . $db_path]);
            if (!$stmt->fetch()) {
                $untracked[] = [
                    'path' => $db_path,
                    'size' => round(filesize($path) / (1024 * 1024), 2)
                ];
            }
        }
    }
}
scanRoms($roms_dir, $results['untracked_files'], $db, $rom_extensions);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= __('admin_clean_roms_title') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/admin_style.css">
</head>
<body class="bg-background text-text-primary">
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
            <div class="flex items-center gap-4">
                <div style="background: rgba(112, 0, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px rgba(112, 0, 255, 0.1);">
                    <i class="fas fa-broom text-xl text-secondary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_clean_roms_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;">FILE_SYSTEM_PURGE_v2.5</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <main class="animate-fade-in px-4 py-8 max-w-7xl mx-auto">
            
            <!-- 1. Displaced Files (SMART REORGANIZE) -->
            <?php if (!empty($results['displaced_files'])): ?>
            <div class="glass premium-table-card mb-10 overflow-hidden" style="border-color: var(--primary);">
                <div class="table-header" style="background: rgba(0, 242, 255, 0.05);">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-wand-magic-sparkles text-2xl text-primary"></i>
                        <h2 style="margin: 0; font-size: 1.1rem; color: white; font-weight: 800;"><?= __('admin_displaced_files_title') ?></h2>
                    </div>
                </div>
                <div class="p-6">
                    <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= __('admin_displaced_files_desc') ?></p>
                </div>
                
                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?= __('admin_title') ?></th>
                                <th><?= __('admin_status') ?></th>
                                <th style="text-align: right;"><?= __('admin_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['displaced_files'] as $game): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: white;"><?= htmlspecialchars($game['title']) ?></div>
                                        <div style="font-size: 0.65rem; opacity: 0.5; font-family: monospace;"><?= htmlspecialchars($game['found_at']) ?></div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-1">
                                            <span class="badge badge-primary" style="font-size: 0.6rem;"><?= __('admin_found_displaced') ?></span>
                                            <div style="font-size: 0.6rem; opacity: 0.6;">→ Suggest: <span style="color: var(--primary);"><?= htmlspecialchars($game['suggested_path']) ?></span></div>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $game['id'] ?>">
                                            <input type="hidden" name="new_path" value="<?= htmlspecialchars($game['suggested_path']) ?>">
                                            <input type="hidden" name="old_full_path" value="<?= htmlspecialchars($game['found_at']) ?>">
                                            <input type="hidden" name="action" value="reorganize">
                                            <button type="submit" class="btn-modern btn-primary" style="font-size: 0.7rem; padding: 8px 15px;">
                                                <i class="fas fa-shuffle"></i> <?= __('admin_reorganize') ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. Missing Files (NOT FOUND) -->
            <div class="glass premium-table-card mb-10 overflow-hidden">
                <div class="table-header">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-ghost text-2xl text-accent"></i>
                        <h2 style="margin: 0; font-size: 1.1rem; color: white; font-weight: 800;"><?= __('admin_missing_files_title') ?></h2>
                    </div>
                </div>
                <div class="p-6">
                    <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= __('admin_missing_files_desc') ?></p>
                </div>
                
                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?= __('admin_title') ?></th>
                                <th><?= __('admin_path') ?></th>
                                <th style="text-align: right;"><?= __('admin_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results['missing_files'])): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; opacity: 0.3; padding: 40px; font-size: 0.8rem;">
                                        <?= __('admin_no_issues') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results['missing_files'] as $game): ?>
                                    <tr>
                                        <td><div style="font-weight: 700; color: white;"><?= htmlspecialchars($game['title']) ?></div></td>
                                        <td style="font-family: monospace; font-size: 0.7rem; opacity: 0.6;"><?= htmlspecialchars($game['rom_path']) ?></td>
                                        <td style="text-align: right;">
                                            <form method="POST" onsubmit="return confirm('<?= __('admin_confirm_delete_entry') ?>');">
                                                <input type="hidden" name="id" value="<?= $game['id'] ?>">
                                                <input type="hidden" name="action" value="delete_game">
                                                <button type="submit" class="btn-modern btn-danger" style="font-size: 0.7rem; padding: 8px 15px;">
                                                    <i class="fas fa-trash"></i> <?= __('admin_delete_entry') ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. Untracked Files (ROMs ONLY) -->
            <div class="glass premium-table-card overflow-hidden">
                <div class="table-header">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-link-slash text-2xl text-secondary"></i>
                        <h2 style="margin: 0; font-size: 1.1rem; color: white; font-weight: 800;"><?= __('admin_untracked_files_title') ?></h2>
                    </div>
                </div>
                <div class="p-6">
                    <p style="font-size: 0.85rem; color: var(--text-secondary);"><?= __('admin_untracked_files_desc') ?></p>
                </div>
                
                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?= __('admin_path') ?></th>
                                <th><?= __('admin_size') ?></th>
                                <th style="text-align: right;"><?= __('admin_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results['untracked_files'])): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; opacity: 0.3; padding: 40px; font-size: 0.8rem;">
                                        <?= __('admin_no_issues') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results['untracked_files'] as $file): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-size: 0.7rem; color: white;"><?= htmlspecialchars($file['path']) ?></td>
                                        <td><span class="badge badge-secondary" style="font-size: 0.65rem;"><?= $file['size'] ?> MB</span></td>
                                        <td style="text-align: right;">
                                            <form method="POST" onsubmit="return confirm('<?= __('admin_confirm_delete_file') ?>');">
                                                <input type="hidden" name="path" value="<?= htmlspecialchars($file['path']) ?>">
                                                <input type="hidden" name="action" value="delete_file">
                                                <button type="submit" class="btn-modern btn-danger" style="font-size: 0.7rem; padding: 8px 15px;">
                                                    <i class="fas fa-trash-alt"></i> <?= __('admin_delete_file') ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <footer style="margin-top: 60px; text-align: center; opacity: 0.2; font-size: 0.65rem; letter-spacing: 3px; padding-bottom: 40px;">
            CLEANER_LOG // STATUS_OPTIMIZED // <?= date('Y') ?>
        </footer>
    </div>
</body>
</html>


