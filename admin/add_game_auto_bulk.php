<?php
require_once '../config.php'; // Chemin relatif
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- Initialisation et Sécurité (Basique) ---
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_file_uploads', '50');

$errors = [];
$console_id_selected = null;
$consoles = [];
$noConsolesConfigured = false;
$upload_token = null; // Identifiant unique pour ce lot d'upload
$processing_done = false;
$upload_token = null; // Assurez-vous que upload_token est aussi initialisé à null
// --- Vérification Admin (inchangé) ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { /* ... redirection login ... */ header('Location: ../login.php'); exit(); }
try {
    $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    if (!$stmtUser->fetch()) { /* ... redirection unauthorized ... */ header('Location: ../index.php?error=unauthorized'); exit(); }
} catch (PDOException $e) { /* ... erreur interne ... */ die("Erreur BDD user check."); }

// --- Récupération Consoles (inchangé) ---
try {
    $consoles = $db->query("SELECT id, name, ss_id FROM consoles WHERE ss_id IS NOT NULL AND ss_id > 0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($consoles)) { $noConsolesConfigured = true; }
} catch (PDOException $e) { $errors[] = "Erreur DB récupération consoles."; error_log("Admin Auto Add Bulk - Error fetching consoles: " . $e->getMessage()); }

// --- Traitement du formulaire POST (Uniquement Upload et Préparation) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noConsolesConfigured) {
    $console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT);
    $rom_files_data = $_FILES['rom'] ?? null;

    // Validation Console
    if (!$console_id) { $errors[] = "Veuillez sélectionner une console."; }
    else {
        // Vérifier si la console existe et a un ss_id
        $valid_console = false;
        foreach ($consoles as $c) { if ($c['id'] == $console_id) { $valid_console = true; break; } }
        if (!$valid_console) { $errors[] = "Console sélectionnée invalide ou non configurée."; $console_id = null; }
        else { $console_id_selected = $console_id; } // Garder l'ID pour le formulaire suivant
    }

    // Validation et Déplacement des Fichiers Uploadés vers un dossier temporaire
    $uploaded_files_info = [];
    if (empty($errors) && isset($rom_files_data['name']) && is_array($rom_files_data['name'])) {
        $num_files = count($rom_files_data['name']);
        $upload_batch_id = session_id() . '_' . time(); // Identifiant unique pour ce lot
        // Créer un dossier temporaire spécifique à ce lot
        $temp_upload_dir = sys_get_temp_dir() . '/bulk_upload_' . $upload_batch_id;

        if (!is_dir($temp_upload_dir) && !mkdir($temp_upload_dir, 0777, true)) {
            $errors[] = "Impossible de créer le dossier temporaire pour les uploads.";
            error_log("Admin Auto Add Bulk - Failed to create temp dir: " . $temp_upload_dir);
        } else {
             for ($i = 0; $i < $num_files; $i++) {
                 $error_code = $rom_files_data['error'][$i];
                 $tmp_name = $rom_files_data['tmp_name'][$i];
                 $original_name = $rom_files_data['name'][$i];
                 $size = $rom_files_data['size'][$i];

                 if ($error_code === UPLOAD_ERR_OK && $size > 0) {
                    $temp_file_path = $temp_upload_dir . '/' . uniqid('rom_', true) . '_' . basename($original_name); // Nom de fichier temporaire unique
                    if (move_uploaded_file($tmp_name, $temp_file_path)) {
                        $uploaded_files_info[] = [
                            'original_name' => $original_name,
                            'temp_path' => $temp_file_path // Chemin vers le fichier dans NOTRE dossier temporaire
                        ];
                        error_log("Admin Auto Add Bulk - Moved '$original_name' to '$temp_file_path'");
                    } else {
                        $errors[] = "Échec du déplacement du fichier uploadé: " . htmlspecialchars($original_name);
                        error_log("Admin Auto Add Bulk - Failed to move '$tmp_name' to '$temp_file_path'");
                    }
                 } elseif ($error_code !== UPLOAD_ERR_NO_FILE) {
                    $phpFileUploadErrors = [ /* ... map ... */ ];
                    $error_message = $phpFileUploadErrors[$error_code] ?? "Erreur inconnue (Code: $error_code)";
                    $errors[] = "Erreur upload fichier '" . htmlspecialchars($original_name) . "': $error_message";
                 }
             } // Fin boucle for

             if (empty($uploaded_files_info) && empty($errors)) {
                 $errors[] = "Aucun fichier ROM valide n'a été téléversé.";
             } elseif (!empty($uploaded_files_info)) {
                 // Stocker les informations nécessaires pour le script SSE dans la session
                 $_SESSION['bulk_upload_data_' . $upload_batch_id] = [
                     'console_id' => $console_id,
                     'region' => filter_input(INPUT_POST, 'region', FILTER_SANITIZE_STRING) ?: 'fr',
                     'files' => $uploaded_files_info,
                     'status' => 'pending' // Statut initial
                 ];
                 $upload_token = $upload_batch_id; // Passer ce token à la page de traitement
                 error_log("Admin Auto Add Bulk - Prepared batch $upload_token with " . count($uploaded_files_info) . " files for console $console_id.");
                 // Nettoyer les anciennes données de session si nécessaire (pour éviter l'accumulation)
                  foreach ($_SESSION as $key => $value) {
                      if (strpos($key, 'bulk_upload_data_') === 0 && $key !== 'bulk_upload_data_' . $upload_batch_id) {
                          // Potentiellement nettoyer les fichiers temporaires associés à d'anciennes sessions ici aussi
                          unset($_SESSION[$key]);
                      }
                  }

             } else {
                  // Si aucun fichier n'a été déplacé et qu'il n'y avait pas d'autres erreurs, on le signale.
                  // Les erreurs d'upload spécifiques ont déjà été ajoutées.
                  if (empty($errors)) $errors[] = "Aucun fichier valide à traiter.";
             }
        } // Fin else (dossier temp créé)

    } elseif (empty($errors)) {
         $errors[] = "Aucun fichier sélectionné.";
    }
// Si des erreurs se sont produites AVANT de pouvoir lancer le traitement SSE
if (!empty($errors) && $upload_token === null) {
    $processing_done = false; // Assignation explicite pour éviter l'avertissement
} elseif ($upload_token !== null) {
    $processing_done = true; // Set to true ONLY if upload was successful
}
// Si ce n'est pas un POST ou si c'est un POST avec erreurs (token null),
// $processing_done sera false (soit par l'initialisation, soit par l'assignation ci-dessus)

} // Fin if POST

?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= __('admin_add_game_bulk_title') ?> - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/admin_style.css?v=<?= @filemtime(__DIR__ . "/public/css/admin_style.css") ?>">
    <style>
        .dropzone-container {
            border: 2px dashed var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            position: relative;
        }
        .dropzone-container:hover, .dropzone-container.dragover {
            border-color: var(--primary);
            background: rgba(0, 242, 255, 0.05);
            box-shadow: 0 0 20px rgba(0, 242, 255, 0.1);
        }
        .dropzone-container i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .dropzone-container:hover i {
            color: var(--primary);
            transform: translateY(-5px);
        }
        #rom {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .progress-wrapper {
            background: var(--glass);
            border-radius: 15px;
            height: 30px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
            position: relative;
            margin: 30px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            width: 0%;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 15px var(--glow-primary);
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 0 5px rgba(0,0,0,0.5);
            z-index: 1;
        }
        .sse-log-container {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0,0,0,0.2);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid var(--glass-border);
        }
        .sse-item {
            padding: 12px;
            border-bottom: 1px solid var(--glass-border);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sse-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="glass nav-bar animate-fade-in">
            <div class="flex items-center gap-4">
                <div style="background: rgba(0, 242, 255, 0.1); padding: 10px; border-radius: 12px;">
                    <i class="fas fa-bolt text-xl text-primary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; color: var(--primary); font-size: 1.2rem;"><?= __('admin_add_game_bulk_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px;">MASS_IMPORT_PROTOCOL</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.7rem;"><?= __('back_caps') ?></a>
        </header>

        <main class="animate-fade-in">
            <?php if (!empty($errors) && !$processing_done): ?>
                <div class="badge badge-danger w-full mb-8 py-4 flex flex-col items-center gap-2" style="font-size: 0.8rem; border-radius: 12px; height: auto;">
                    <div class="flex items-center gap-2 mb-2 font-bold">
                        <i class="fas fa-exclamation-triangle"></i> <?= __('admin_error_before_process') ?>
                    </div>
                    <ul style="text-align: left; margin: 0;"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php if ($noConsolesConfigured): ?>
                <div class="badge badge-danger w-full mb-8 py-4 flex items-center justify-center gap-2" style="font-size: 0.8rem; border-radius: 12px; background: rgba(255, 165, 0, 0.1); color: orange; border: 1px solid orange;">
                    <i class="fas fa-exclamation-circle"></i> <?= __('admin_no_console_ss_id') ?>
                </div>
            <?php endif; ?>

            <?php if ($processing_done && $upload_token): ?>
                <div id="sse-processing" class="glass p-10">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h2 style="margin: 0; font-size: 1.4rem; color: white;"><?= __('admin_processing_in_progress') ?></h2>
                            <p id="overall-status" style="margin: 5px 0 0 0; color: var(--primary); font-size: 0.85rem; font-weight: 700;"><?= __('admin_initialization') ?>...</p>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-secondary); opacity: 0.5; letter-spacing: 1px;">SSE_STREAM_ACTIVE</div>
                    </div>

                    <div class="progress-wrapper">
                        <div id="progress-bar" class="progress-fill"></div>
                        <div id="progress-percent" class="progress-text">0%</div>
                    </div>

                    <div class="sse-log-container">
                        <div style="font-size: 0.65rem; color: var(--text-secondary); opacity: 0.5; margin-bottom: 15px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">
                            <i class="fas fa-terminal mr-2"></i> Processing Logs
                        </div>
                        <ul id="sse-log" style="list-style: none; padding: 0; margin: 0;"></ul>
                    </div>

                    <div id="sse-summary" style="display: none;" class="mt-10 pt-10 border-t border-white border-opacity-5">
                        <div class="grid grid-cols-3 gap-6 mb-10">
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
                            <a href="add_game_auto_bulk.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" class="btn-modern btn-primary">
                                <i class="fas fa-plus"></i> <?= __('admin_add_more_roms') ?>
                            </a>
                            <a href="index.php" class="btn-modern btn-secondary"><?= __('back_caps') ?></a>
                            <a href="add_game.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" id="link-manual-add" style="display: none;" class="btn-modern btn-accent">
                                <i class="fas fa-edit"></i> <?= __('admin_view_skipped') ?>
                            </a>
                        </div>
                    </div>
                </div>

                <script>
                    const sseLog = document.getElementById('sse-log');
                    const overallStatus = document.getElementById('overall-status');
                    const progressBar = document.getElementById('progress-bar');
                    const progressPercent = document.getElementById('progress-percent');
                    const sseSummary = document.getElementById('sse-summary');
                    const summarySuccess = document.getElementById('summary-success');
                    const summarySkipped = document.getElementById('summary-skipped');
                    const summaryErrors = document.getElementById('summary-errors');
                    const linkManualAdd = document.getElementById('link-manual-add');

                    let countSuccess = 0, countSkipped = 0, countErrors = 0, totalFiles = 0;

                    if (typeof(EventSource) !== "undefined") {
                        const eventSource = new EventSource('process_bulk_sse.php?token=<?= urlencode($upload_token) ?>');
                        overallStatus.innerHTML = '<i class="fas fa-satellite fa-spin mr-2"></i> ESTABLISHING_UPLINK...';

                        eventSource.addEventListener('progress', function(event) {
                            const data = JSON.parse(event.data);
                            totalFiles = data.total;
                            const percent = totalFiles > 0 ? Math.round((data.index / totalFiles) * 100) : 0;
                            overallStatus.innerHTML = `<i class="fas fa-sync fa-spin mr-2"></i> PROCESSING ${data.index} OF ${data.total}: ${data.filename}`;
                            progressBar.style.width = percent + '%';
                            progressPercent.textContent = percent + '%';
                        });

                        eventSource.addEventListener('log', function(event) {
                            const data = JSON.parse(event.data);
                            const li = document.createElement('li');
                            li.className = 'sse-item';
                            li.style.opacity = '0.5';
                            li.innerHTML = `<i class="fas fa-info-circle" style="color: var(--primary)"></i> <span>${data.message}</span>`;
                            sseLog.appendChild(li);
                            sseLog.parentElement.scrollTop = sseLog.parentElement.scrollHeight;
                        });

                        eventSource.addEventListener('success', function(event) {
                            const data = JSON.parse(event.data);
                            countSuccess++;
                            const li = document.createElement('li');
                            li.className = 'sse-item';
                            li.innerHTML = `<i class="fas fa-check-circle" style="color: #00ff88"></i> <div><span style="color: white; font-weight: 700;">${data.filename}</span> <span style="opacity: 0.5; margin: 0 10px;">➔</span> <span style="color: var(--primary)">${data.title}</span></div>`;
                            sseLog.appendChild(li);
                            sseLog.parentElement.scrollTop = sseLog.parentElement.scrollHeight;
                        });

                        eventSource.addEventListener('skipped', function(event) {
                            const data = JSON.parse(event.data);
                            countSkipped++;
                            const li = document.createElement('li');
                            li.className = 'sse-item';
                            li.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #ffcc00"></i> <div><span style="color: white; font-weight: 700;">${data.filename}</span> <span style="opacity: 0.5; margin: 0 10px;">➔</span> <span style="color: #ffcc00">SKIPPED: ${data.reason}</span></div>`;
                            sseLog.appendChild(li);
                            sseLog.parentElement.scrollTop = sseLog.parentElement.scrollHeight;
                            linkManualAdd.style.display = 'flex';
                        });

                        eventSource.addEventListener('error_event', function(event) {
                            const data = JSON.parse(event.data);
                            countErrors++;
                            const li = document.createElement('li');
                            li.className = 'sse-item';
                            li.innerHTML = `<i class="fas fa-times-circle" style="color: #ff3366"></i> <div><span style="color: white; font-weight: 700;">${data.filename}</span> <span style="opacity: 0.5; margin: 0 10px;">➔</span> <span style="color: #ff3366">ERROR: ${data.message}</span></div>`;
                            sseLog.appendChild(li);
                            sseLog.parentElement.scrollTop = sseLog.parentElement.scrollHeight;
                        });

                        eventSource.addEventListener('complete', function(event) {
                            const data = JSON.parse(event.data);
                            overallStatus.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${data.message}`;
                            overallStatus.style.color = '#00ff88';
                            progressBar.style.width = '100%';
                            progressPercent.textContent = '100%';
                            summarySuccess.textContent = countSuccess;
                            summarySkipped.textContent = countSkipped;
                            summaryErrors.textContent = countErrors;
                            sseSummary.style.display = 'block';
                            eventSource.close();
                        });

                        eventSource.onerror = function(err) {
                            overallStatus.innerHTML = '<i class="fas fa-times-circle mr-2"></i> CONNECTION_LOST';
                            overallStatus.style.color = '#ff3366';
                            sseSummary.style.display = 'block';
                            eventSource.close();
                        };
                    }
                </script>

            <?php else: ?>
                <div class="glass p-10">
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 30px;">
                        <?= __('admin_add_game_bulk_desc') ?>
                    </p>

                    <form action="add_game_auto_bulk" method="post" enctype="multipart/form-data" id="bulk-form">
                        <div class="grid gap-8 mb-10">
                            <div class="form-group">
                                <label><?= mb_strtoupper(__('admin_console')) ?></label>
                                <select name="console_id" required class="form-control" <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                                    <option value="" disabled selected><?= __('admin_choose_console') ?></option>
                                    <?php foreach ($consoles as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><?= mb_strtoupper(__('admin_description_lang') ?? 'LANGUE DESCRIPTION') ?></label>
                                <select name="region" class="form-control">
                                    <option value="fr">FRANÇAIS (FR)</option>
                                    <option value="en">ENGLISH (EN)</option>
                                    <option value="es">ESPAÑOL (ES)</option>
                                    <option value="pt">PORTUGUÊS (PT)</option>
                                    <option value="de">DEUTSCH (DE)</option>
                                    <option value="it">ITALIANO (IT)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><?= mb_strtoupper(__('admin_rom_files_label')) ?></label>
                                <div class="dropzone-container" id="drop-zone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h3 style="margin: 0; color: white; font-size: 1rem;"><?= __('admin_drag_drop_roms') ?? 'DRAG & DROP ROMS HERE' ?></h3>
                                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin: 10px 0 0 0;" id="file-count">
                                        <?= __('admin_server_limits') ?>: <?= ini_get('max_file_uploads') ?> files, <?= ini_get('upload_max_filesize') ?>/file
                                    </p>
                                    <input type="file" id="rom" name="rom[]" multiple required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center gap-4">
                            <a href="index.php" class="btn-modern btn-secondary" style="padding: 15px 40px;"><?= __('admin_cancel') ?></a>
                            <button type="submit" class="btn-modern btn-primary" style="padding: 15px 60px;" id="submit-btn" <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                                <i class="fas fa-bolt mr-2"></i> <?= __('admin_start_add') ?>
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                    const romInput = document.getElementById('rom');
                    const dropZone = document.getElementById('drop-zone');
                    const fileCount = document.getElementById('file-count');
                    const submitBtn = document.getElementById('submit-btn');
                    const form = document.getElementById('bulk-form');

                    romInput.addEventListener('change', () => {
                        const count = romInput.files.length;
                        fileCount.innerHTML = `<span style="color: var(--primary); font-weight: 700;">${count}</span> FILES SELECTED`;
                        dropZone.style.borderColor = 'var(--primary)';
                    });

                    ['dragenter', 'dragover'].forEach(name => {
                        dropZone.addEventListener(name, (e) => {
                            e.preventDefault();
                            dropZone.classList.add('dragover');
                        });
                    });

                    ['dragleave', 'drop'].forEach(name => {
                        dropZone.addEventListener(name, (e) => {
                            e.preventDefault();
                            dropZone.classList.remove('dragover');
                        });
                    });

                    form.addEventListener('submit', (e) => {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> PREPARING_DATA...';
                    });
                </script>
            <?php endif; ?>
        </main>

        <footer style="margin-top: 60px; text-align: center; opacity: 0.2; font-size: 0.65rem; letter-spacing: 3px;">
            BATCH_ENGINE // STATUS_READY // <?= date('Y') ?>
        </footer>
    </div>
</body>
</html>

