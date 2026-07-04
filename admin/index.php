<?php
require_once '../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$stmtUser = $db->prepare("SELECT username, role FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    header('Location: ../index.php?error=unauthorized');
    exit();
}

// Pagination & Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$console_id = (isset($_GET['console_id']) && $_GET['console_id'] !== 'all') ? (int)$_GET['console_id'] : null;

// Build Query
$query = "SELECT g.*, c.name as console_name, c.slug as console_slug 
          FROM games g 
          JOIN consoles c ON g.console_id = c.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND g.title LIKE ?";
    $params[] = "%$search%";
}
if ($console_id) {
    $query .= " AND g.console_id = ?";
    $params[] = $console_id;
}

// Get Total for Pagination
$stmtTotal = $db->prepare($query);
$stmtTotal->execute($params);
$totalGames = $stmtTotal->rowCount();
$totalPages = ceil($totalGames / $limit);

// Get Paginated Results
$query .= " ORDER BY g.id DESC LIMIT $limit OFFSET $offset";
$stmtGames = $db->prepare($query);
$stmtGames->execute($params);
$games = $stmtGames->fetchAll(PDO::FETCH_ASSOC);

$stmtConsoles = $db->query("SELECT * FROM consoles ORDER BY name");
$consoles = $stmtConsoles->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <title><?= __('admin_panel') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="public/css/admin_style.css">
    <style>
        .animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
                <div class="flex items-center gap-4">
                    <img src="../public/img/logo_new.png" alt="Logo" style="height: 45px; filter: drop-shadow(0 0 10px var(--primary-glow));">
                    <div>
                        <h1 class="pixel-text" style="margin:0; font-size: 1.4rem;"><?= __('admin_panel') ?></h1>
                        <span style="font-size: 0.65rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 3px; font-weight: 700;"><?= __('admin_node_retro_version') ?></span>
                    </div>
                </div>

                <div class="nav-right">
                    <a href="../index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                        <i class="fas fa-external-link-alt"></i> <?= __('back_to_site') ?>
                    </a>
                    <div class="flex items-center gap-3 glass" style="padding: 10px 20px; border-radius: 16px; border-color: rgba(0, 242, 255, 0.2);">
                        <i class="fas fa-user-shield text-primary"></i>
                        <span style="font-size: 0.85rem; font-weight: 800; letter-spacing: 1px;"><?= strtoupper(htmlspecialchars($user['username'])) ?></span>
                    </div>
                    <a href="../logout.php" class="btn-modern btn-danger" style="padding: 12px 18px;" title="<?= __('logout') ?>">
                        <i class="fas fa-power-off"></i>
                    </a>
                </div>
            </header>

            <main class="animate-fade-in" style="animation-delay: 0.1s;">
                <!-- Main Dashboard Grid -->
                <div class="admin-grid mb-10">
                    <!-- Stat Games -->
                    <div class="glass stat-card col-4">
                        <div class="flex justify-between items-start">
                            <div class="stat-icon" style="background: rgba(0, 242, 255, 0.1); color: var(--primary);">
                                <i class="fas fa-gamepad"></i>
                            </div>
                            <span class="badge badge-success"><?= $totalGames ?></span>
                        </div>
                        <h3><?= __('games_caps') ?></h3>
                        <span class="stat-value"><?= $totalGames ?></span>
                        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 25px; opacity: 0.6;"><?= __('admin_identified_game_entries') ?></p>
                        
                        <div class="flex gap-3 mt-auto">
                            <a href="add_game_auto.php" class="btn-modern btn-primary" style="flex: 1; font-size: 0.7rem;">
                                <i class="fas fa-robot"></i> <?= __('admin_smart_add_btn') ?>
                            </a>
                            <a href="add_game.php" class="btn-modern btn-secondary" style="flex: 1; font-size: 0.7rem;">
                                <i class="fas fa-plus"></i> <?= __('admin_manual_btn') ?>
                            </a>
                        </div>
                    </div>

                    <!-- Stat Consoles -->
                    <div class="glass stat-card col-4">
                        <div class="flex justify-between items-start">
                            <div class="stat-icon" style="background: rgba(112, 0, 255, 0.1); color: var(--secondary);">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <span class="badge badge-secondary" style="color: var(--secondary); border-color: var(--secondary);"><?= count($consoles) ?></span>
                        </div>
                        <h3><?= __('consoles_caps') ?></h3>
                        <span class="stat-value"><?= count($consoles) ?></span>
                        <p style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 25px; opacity: 0.6;"><?= __('admin_emulated_systems_active') ?></p>
                        
                        <a href="add_console.php" class="btn-modern btn-secondary w-full mt-auto" style="border-color: var(--secondary-glow); color: var(--secondary);">
                            <i class="fas fa-plus-circle"></i> <?= __('admin_new_sys') ?>
                        </a>
                    </div>

                    <!-- Tools Card -->
                    <div class="glass stat-card col-4">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="stat-icon" style="background: rgba(255, 45, 85, 0.1); color: var(--accent); margin-bottom: 0; width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h3 style="margin:0;"><?= __('admin_bulk_tools') ?></h3>
                        </div>
                        
                        <div class="flex flex-col gap-3">
                            <a href="add_game_auto_bulk.php" class="btn-modern btn-secondary w-full" style="justify-content: flex-start; padding-left: 20px;">
                                <i class="fas fa-cloud-upload-alt text-primary"></i> <?= __('admin_bulk_import') ?>
                            </a>
                            <a href="scan_roms.php" class="btn-modern btn-secondary w-full" style="justify-content: flex-start; padding-left: 20px;">
                                <i class="fas fa-satellite-dish text-primary"></i> <?= __('admin_rom_scanner') ?>
                            </a>
                            <a href="clean_roms.php" class="btn-modern btn-secondary w-full" style="justify-content: flex-start; padding-left: 20px;">
                                <i class="fas fa-broom text-accent"></i> <?= __('admin_clean_roms_title') ?>
                            </a>
                            <div class="flex gap-3">
                                <a href="themes.php" class="btn-modern btn-secondary" style="flex: 1;" title="<?= __('admin_manage_themes') ?>">
                                    <i class="fas fa-palette"></i>
                                </a>
                                <a href="settings.php" class="btn-modern btn-secondary" style="flex: 1;" title="<?= __('admin_sys_config') ?>">
                                    <i class="fas fa-sliders-h"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Full Width Scanner Hero -->
                    <div class="glass stat-card col-12 scanner-hero" style="flex-direction: row; align-items: center; justify-content: space-between; overflow: visible;">
                        <div class="flex items-center gap-8">
                            <div style="background: rgba(0, 242, 255, 0.1); padding: 25px; border-radius: 20px; box-shadow: 0 0 20px rgba(0, 242, 255, 0.1);">
                                <i class="fas fa-satellite-dish text-5xl text-primary animate-pulse"></i>
                            </div>
                            <div>
                                <h2 style="margin: 0; font-size: 1.8rem; color: white; font-weight: 900; letter-spacing: 1px;"><?= __('admin_rom_scanner') ?></h2>
                                <p style="margin: 8px 0 0 0; color: var(--text-secondary); font-size: 0.95rem; max-width: 500px; line-height: 1.5;"><?= __('admin_rom_scanner_desc') ?></p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-3">
                            <a href="scan_roms.php" class="btn-modern btn-primary" style="padding: 18px 50px; font-size: 1rem;">
                                <i class="fas fa-search-location mr-2"></i> <?= __('admin_start_scan') ?>
                            </a>
                            <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.4; letter-spacing: 4px; font-weight: 800;"><?= __('admin_fst_audit_ready') ?></span>
                        </div>
                    </div>

                    <!-- Recent Games Table -->
                    <div class="glass stat-card col-12 premium-table-card">
                        <div class="table-header" style="flex-wrap: wrap; gap: 20px;">
                            <div class="flex items-center gap-4">
                                <i class="fas fa-database text-primary"></i>
                                <h3 style="margin:0; color: white;"><?= __('admin_game_db') ?></h3>
                                <span class="badge badge-secondary"><?= $totalGames ?> <?= __('admin_entries') ?></span>
                            </div>
                            
                            <form method="GET" class="flex gap-3 flex-1" style="min-width: 300px;">
                                <div class="search-container">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('admin_search_placeholder') ?>" class="form-control">
                                </div>
                                <select name="console_id" class="form-control" style="width: auto;">
                                    <option value="all"><?= __('admin_filter_console') ?></option>
                                    <?php foreach ($consoles as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (isset($_GET['console_id']) && $_GET['console_id'] == $c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-modern btn-primary" style="height: 52px; padding: 0 20px;">
                                    <i class="fas fa-filter"></i>
                                </button>
                                <?php if ($search || $console_id): ?>
                                    <a href="index.php" class="btn-modern btn-secondary" style="height: 52px; padding: 0 20px; display: flex; align-items: center;">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    
                    <div class="modern-table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th><?= __('admin_title') ?></th>
                                    <th><?= __('admin_console') ?></th>
                                    <th style="text-align: right;"><?= __('admin_actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($games)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 60px; opacity: 0.3;">
                                            <i class="fas fa-search text-4xl mb-4"></i><br>
                                            <?= __('admin_no_games_found') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($games as $game): ?>
                                <tr>
                                    <td style="width: 60px;">
                                        <div style="width: 45px; height: 45px; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);">
                                            <?php if ($game['cover']): ?>
                                                <img src="../<?= htmlspecialchars($game['cover']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; opacity: 0.2;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="font-weight: 700; color: white; font-size: 0.9rem;"><?= htmlspecialchars($game['title']) ?></td>
                                    <td>
                                        <span class="badge badge-secondary" style="border-radius: 8px;">
                                            <i class="fas fa-gamepad mr-2 opacity-50"></i><?= htmlspecialchars($game['console_name']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="flex justify-end gap-2">
                                            <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn-modern btn-secondary" style="padding: 10px; border-radius: 12px;" title="<?= __('edit') ?>">
                                                <i class="fas fa-pencil-alt text-primary"></i>
                                            </a>
                                            <a href="delete_game?id=<?= $game['id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>&console_id=<?= $console_id ?>" class="btn-modern btn-secondary" style="padding: 10px; border-radius: 12px;" onclick="return confirm('<?= __('admin_confirm_delete') ?>');" title="<?= __('delete') ?>">
                                                <i class="fas fa-trash-alt text-accent"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div style="padding: 25px; display: flex; justify-content: center; gap: 10px; border-top: 1px solid var(--glass-border);">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&console_id=<?= $console_id ?>" class="btn-modern btn-secondary" style="padding: 10px 15px;"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&console_id=<?= $console_id ?>" class="btn-modern <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>" style="width: 40px; justify-content: center;"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&console_id=<?= $console_id ?>" class="btn-modern btn-secondary" style="padding: 10px 15px;"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer style="margin-top: 40px; text-align: center; padding: 40px; color: var(--text-secondary); opacity: 0.3; font-size: 0.7rem; letter-spacing: 5px; font-weight: 700; border-top: 1px solid rgba(255,255,255,0.05);">
            &copy; <?= date('Y') ?> // RETROHOME_CORE // SECURE_ACCESS_LEVEL_10
        </footer>
    </div>
</body>
</html>
