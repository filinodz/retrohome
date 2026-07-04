<?php
require_once '../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$stmtConsoles = $db->query("SELECT id, name, slug, ss_id FROM consoles ORDER BY name ASC");
$consoles = $stmtConsoles->fetchAll(PDO::FETCH_ASSOC);

$untrackedFiles = [];
$scanPerformed = false;
$consoleIdSelected = $_GET['console_id'] ?? null;

if (isset($_GET['scan'])) {
    $scanPerformed = true;
    
    // Determine which consoles to scan
    $consolesToScan = [];
    if ($consoleIdSelected && $consoleIdSelected !== 'all') {
        foreach ($consoles as $c) {
            if ($c['id'] == $consoleIdSelected) {
                $consolesToScan[] = $c;
                break;
            }
        }
    } else {
        $consolesToScan = $consoles;
    }

    foreach ($consolesToScan as $console) {
        $consolePath = ROMS_PATH . $console['slug'] . '/';
        if (is_dir($consolePath)) {
            $files = scandir($consolePath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'images' || $file === 'preview') continue;
                
                $filePath = $consolePath . $file;
                
                // If it's a directory, check if it's a game folder or just a stray dir
                // Standard structure: roms/console/title.ext OR roms/console/slug/titleslug.ext
                // The bulk add uses: /roms/console/slug/romname.ext
                
                if (is_dir($filePath)) {
                    $subFiles = scandir($filePath);
                    foreach ($subFiles as $subFile) {
                        if ($subFile === '.' || $subFile === '..' || $subFile === 'images' || $subFile === 'preview') continue;
                        $subFilePath = $filePath . DIRECTORY_SEPARATOR . $subFile;
                        if (is_file($subFilePath)) {
                            $ext = strtolower(pathinfo($subFile, PATHINFO_EXTENSION));
                            if (in_array($ext, ['zip', 'sfc', 'smc', 'fig', 'bin', 'gba', 'gbc', 'gb', 'nes', 'pce', 'md', 'mgd', 'sms', 'gg', 'col', 'ngp', 'ngc', 'ws', 'wsc', '7z', 'iso', 'cue', 'chd'])) {
                                $relativeRomPath = '/roms/' . $console['slug'] . '/' . $file . '/' . $subFile;
                                $stmt = $db->prepare("SELECT id FROM games WHERE rom_path = ?");
                                $stmt->execute([$relativeRomPath]);
                                if (!$stmt->fetch()) {
                                    $untrackedFiles[] = [
                                        'console_id' => $console['id'],
                                        'console_name' => $console['name'],
                                        'filename' => $subFile,
                                        'full_path' => $subFilePath,
                                        'relative_path' => $relativeRomPath,
                                        'game_slug' => $file
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    // It's a file directly in the console folder (legacy or simple structure)
                    $relativeRomPath = '/roms/' . $console['slug'] . '/' . $file;
                    $stmt = $db->prepare("SELECT id FROM games WHERE rom_path = ?");
                    $stmt->execute([$relativeRomPath]);
                    if (!$stmt->fetch()) {
                        $untrackedFiles[] = [
                            'console_id' => $console['id'],
                            'console_name' => $console['name'],
                            'filename' => $file,
                            'full_path' => $filePath,
                            'relative_path' => $relativeRomPath,
                            'game_slug' => pathinfo($file, PATHINFO_FILENAME)
                        ];
                    }
                }
            }
        }
    }
}

// Prepare SSE Token for import
$importToken = bin2hex(random_bytes(16));
if ($scanPerformed && !empty($untrackedFiles)) {
    $_SESSION['scan_import_data_' . $importToken] = [
        'files' => $untrackedFiles
    ];
}

?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= __('admin_scan_roms_title') ?? 'ROM Scanner' ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/admin_style.css">
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
            <div class="flex items-center gap-4">
                <div style="background: rgba(0, 242, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px rgba(0, 242, 255, 0.1);">
                    <i class="fas fa-radar text-xl text-primary animate-pulse"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_scan_roms_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;">FILESYSTEM_AUDIT_v2.0</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <main class="animate-fade-in" style="animation-delay: 0.1s;">
            <div class="glass stat-card mb-10" style="padding: 40px;">
                <form action="scan_roms" method="get" class="flex items-end gap-6 flex-wrap">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 250px;">
                        <label><?= mb_strtoupper(__('admin_console')) ?></label>
                        <select name="console_id" class="form-control">
                            <option value="all"><?= __('admin_all_consoles') ?></option>
                            <?php foreach ($consoles as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $consoleIdSelected == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="scan" value="1" class="btn-modern btn-primary" style="padding: 15px 45px;">
                        <i class="fas fa-search mr-2"></i> <?= __('admin_start_scan') ?>
                    </button>
                    <?php if ($scanPerformed): ?>
                        <a href="scan_roms.php" class="btn-modern btn-secondary" style="padding: 15px 25px;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($scanPerformed): ?>
                <div class="glass premium-table-card">
                    <?php if (empty($untrackedFiles)): ?>
                        <div class="text-center py-20 animate-fade-in">
                            <i class="fas fa-check-circle text-6xl text-success mb-6 opacity-20"></i>
                            <h2 style="color: white; margin: 0; font-size: 1.6rem; font-weight: 800;"><?= __('admin_no_untracked_roms') ?></h2>
                            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 15px; opacity: 0.6;">Your library is perfectly synchronized with the filesystem.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-header">
                            <div class="flex items-center gap-4">
                                <i class="fas fa-list-check text-2xl text-primary"></i>
                                <h2 style="margin: 0; font-size: 1.1rem; color: white; font-weight: 800;"><?= count($untrackedFiles) ?> <?= __('admin_roms_found') ?></h2>
                            </div>
                            <button id="import-btn" class="btn-modern btn-primary">
                                <i class="fas fa-bolt mr-2"></i> <?= __('admin_import_selected') ?>
                            </button>
                        </div>

                        <div class="modern-table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;"><input type="checkbox" id="select-all" checked style="width: 18px; height: 18px; border-radius: 4px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border);"></th>
                                        <th><?= __('admin_console') ?></th>
                                        <th><?= __('admin_filename') ?></th>
                                        <th><?= __('admin_path') ?></th>
                                        <th style="text-align: right;"><?= __('admin_status') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($untrackedFiles as $index => $file): ?>
                                        <tr>
                                            <td><input type="checkbox" class="file-checkbox" data-index="<?= $index ?>" checked style="width: 18px; height: 18px;"></td>
                                            <td>
                                                <span class="badge badge-secondary" style="border-radius: 8px;">
                                                    <i class="fas fa-gamepad mr-2 opacity-50"></i><?= htmlspecialchars($file['console_name']) ?>
                                                </span>
                                            </td>
                                            <td style="color: white; font-weight: 700; font-size: 0.9rem;"><?= htmlspecialchars($file['filename']) ?></td>
                                            <td style="font-family: monospace; font-size: 0.65rem; opacity: 0.4;"><?= htmlspecialchars($file['relative_path']) ?></td>
                                            <td style="text-align: right;">
                                                <span class="badge badge-secondary"><?= __('admin_untracked') ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SSE Import UI (Hidden by default) -->
                <div id="import-processing" class="glass stat-card mt-10" style="display: none; padding: 45px;">
                    <div class="flex justify-between items-end mb-8">
                        <div>
                            <h2 style="margin: 0; font-size: 1.6rem; color: white; font-weight: 800;"><?= __('admin_import_in_progress') ?></h2>
                            <p id="overall-status" style="margin: 8px 0 0 0; color: var(--primary); font-size: 0.9rem; font-weight: 800; letter-spacing: 1px;">INITIALIZING_IMPORT_PROTOCOL...</p>
                        </div>
                        <div style="font-size: 0.65rem; color: var(--text-secondary); opacity: 0.4; letter-spacing: 3px; font-weight: 900;">SCRAPER_V2_ACTIVE</div>
                    </div>

                    <div style="background: rgba(255, 255, 255, 0.03); border-radius: 18px; height: 35px; overflow: hidden; border: 1px solid var(--glass-border); position: relative; margin-bottom: 35px;">
                        <div id="progress-bar" style="height: 100%; background: linear-gradient(90deg, var(--secondary), var(--primary)); width: 0%; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 20px var(--primary-glow);"></div>
                        <div id="progress-percent" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 900; color: white; text-shadow: 0 0 10px rgba(0,0,0,0.8); z-index: 2;">0%</div>
                    </div>

                    <div id="sse-log-container" style="max-height: 350px; overflow-y: auto; background: rgba(0,0,0,0.25); border-radius: 20px; padding: 25px; border: 1px solid var(--glass-border); margin-bottom: 35px;">
                        <ul id="sse-log" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;"></ul>
                    </div>

                    <div id="sse-summary" style="display: none;" class="mt-10 pt-10 border-t border-white border-opacity-5">
                         <div class="grid grid-cols-3 gap-6 mb-10" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <div class="glass p-6 text-center">
                                <div style="font-size: 2rem; font-weight: 800; color: #00ff88;" id="summary-success">0</div>
                                <div style="font-size: 0.6rem; color: var(--text-secondary); font-weight: 700;"><?= __('admin_success_caps') ?></div>
                            </div>
                            <div class="glass p-6 text-center">
                                <div style="font-size: 2rem; font-weight: 800; color: #ffcc00;" id="summary-skipped">0</div>
                                <div style="font-size: 0.6rem; color: var(--text-secondary); font-weight: 700;"><?= __('admin_skipped_caps') ?></div>
                            </div>
                            <div class="glass p-6 text-center">
                                <div style="font-size: 2rem; font-weight: 800; color: #ff3366;" id="summary-errors">0</div>
                                <div style="font-size: 0.6rem; color: var(--text-secondary); font-weight: 700;"><?= __('admin_errors_caps') ?></div>
                            </div>
                        </div>
                        <div class="flex justify-center gap-4">
                            <a href="scan_roms.php" class="btn-modern btn-primary"><?= __('admin_done_caps') ?? 'DONE' ?></a>
                        </div>
                    </div>
                </div>

                <script>
                    const importBtn = document.getElementById('import-btn');
                    const selectAll = document.getElementById('select-all');
                    const checkboxes = document.querySelectorAll('.file-checkbox');
                    const importProcessing = document.getElementById('import-processing');
                    const sseLog = document.getElementById('sse-log');
                    const overallStatus = document.getElementById('overall-status');
                    const progressBar = document.getElementById('progress-bar');
                    const progressPercent = document.getElementById('progress-percent');
                    const sseSummary = document.getElementById('sse-summary');

                    if (selectAll) {
                        selectAll.addEventListener('change', () => {
                            checkboxes.forEach(cb => cb.checked = selectAll.checked);
                        });
                    }

                    if (importBtn) {
                        importBtn.addEventListener('click', () => {
                            const selectedIndices = Array.from(checkboxes)
                                .filter(cb => cb.checked)
                                .map(cb => cb.dataset.index);

                            if (selectedIndices.length === 0) {
                                alert('Please select at least one file.');
                                return;
                            }

                            // Prepare the payload
                            const payload = {
                                token: '<?= $importToken ?>',
                                indices: selectedIndices
                            };

                            // Start SSE
                            startImport(payload);
                        });
                    }

                    function startImport(payload) {
                        importProcessing.style.display = 'block';
                        importBtn.disabled = true;
                        importBtn.style.opacity = '0.5';
                        
                        document.querySelector('.modern-table-container').style.opacity = '0.3';
                        document.querySelector('.modern-table-container').style.pointerEvents = 'none';

                        const eventSource = new EventSource(`process_scan_sse.php?token=${payload.token}&indices=${payload.indices.join(',')}`);
                        
                        let countSuccess = 0, countSkipped = 0, countErrors = 0;

                        eventSource.addEventListener('progress', (e) => {
                            const data = JSON.parse(e.data);
                            const percent = Math.round((data.index / data.total) * 100);
                            progressBar.style.width = percent + '%';
                            progressPercent.textContent = percent + '%';
                            overallStatus.innerHTML = `<i class="fas fa-sync fa-spin mr-2"></i> PROCESSING ${data.index} OF ${data.total}: ${data.filename}`;
                        });

                        eventSource.addEventListener('success', (e) => {
                            const data = JSON.parse(e.data);
                            countSuccess++;
                            addLogItem('check-circle', '#00ff88', `<strong>${data.filename}</strong> imported as <strong>${data.title}</strong>`);
                        });

                        eventSource.addEventListener('skipped', (e) => {
                            const data = JSON.parse(e.data);
                            countSkipped++;
                            addLogItem('exclamation-triangle', '#ffcc00', `<strong>${data.filename}</strong> skipped: ${data.reason}`);
                        });

                        eventSource.addEventListener('error_event', (e) => {
                            const data = JSON.parse(e.data);
                            countErrors++;
                            addLogItem('times-circle', '#ff3366', `<strong>${data.filename}</strong> error: ${data.message}`);
                        });

                        eventSource.addEventListener('complete', (e) => {
                            const data = JSON.parse(e.data);
                            overallStatus.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${data.message}`;
                            overallStatus.style.color = '#00ff88';
                            document.getElementById('summary-success').textContent = countSuccess;
                            document.getElementById('summary-skipped').textContent = countSkipped;
                            document.getElementById('summary-errors').textContent = countErrors;
                            sseSummary.style.display = 'block';
                            eventSource.close();
                        });

                        eventSource.onerror = (err) => {
                            overallStatus.innerHTML = '<i class="fas fa-times-circle mr-2"></i> CONNECTION LOST';
                            overallStatus.style.color = '#ff3366';
                            sseSummary.style.display = 'block';
                            eventSource.close();
                        };
                    }

                    function addLogItem(icon, color, text) {
                        const li = document.createElement('li');
                        li.style.padding = '10px';
                        li.style.borderBottom = '1px solid var(--glass-border)';
                        li.style.display = 'flex';
                        li.style.alignItems = 'center';
                        li.style.gap = '15px';
                        li.innerHTML = `<i class="fas fa-${icon}" style="color: ${color}"></i> <span style="color: rgba(255,255,255,0.8)">${text}</span>`;
                        sseLog.prepend(li);
                    }
                </script>
            <?php endif; ?>
        </main>

        <footer style="margin-top: 60px; text-align: center; opacity: 0.2; font-size: 0.65rem; letter-spacing: 3px;">
            SCANNER_ENGINE // STATUS_READY // <?= date('Y') ?>
        </footer>
    </div>
</body>
</html>
