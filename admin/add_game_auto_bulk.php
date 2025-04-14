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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajout Auto Jeux en Masse - Administration</title>
    <!-- Metas, CSS (identique) -->
    <link rel="icon" type="image/png" href="../assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/style.css">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* Styles pour résultats SSE */
        #sse-results { margin-top: 2rem; padding: 1.5rem; background-color: rgba(0, 0, 0, 0.2); border-radius: 8px; border: 1px solid #4a5568; }
        #sse-results h3 { font-family: 'Orbitron', sans-serif; font-weight: 700; margin-bottom: 1rem; font-size: 1.25rem; }
        .sse-item { margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #2d3748; font-size: 0.9rem;}
        .sse-item:last-child { border-bottom: none; }
        .sse-filename { font-weight: 600; color: #cbd5e0; }
        .progress-bar-container { width: 100%; background-color: #2d3748; border-radius: 4px; overflow: hidden; margin-bottom: 1rem; }
        .progress-bar { height: 20px; background-color: #4c51bf; /* Indigo */ width: 0%; text-align: center; line-height: 20px; color: white; transition: width 0.5s ease-in-out; }
        #overall-status { margin-bottom: 1rem; font-weight: bold; }
    </style>
</head>
<body class="bg-background text-text-primary font-body">

<div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <header class="admin-header-internal mb-8">
         <a href="index.php" class="back-link-header" title="Retour à la liste">
            <i class="fas fa-arrow-left mr-2"></i>
             <h1 class="form-title inline">Ajout Auto Jeux en Masse</h1>
        </a>
    </header>

    <!-- Affichage erreurs PRE-TRAITEMENT -->
    <?php if (!empty($errors) && !$processing_done): ?>
        <div class="form-errors bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded relative mb-6 animate__animated animate__shakeX">
             <strong class="font-bold">Erreur avant traitement:</strong>
            <ul><?php foreach ($errors as $error): ?><li><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if ($noConsolesConfigured): ?>
         <div class="bg-yellow-800 border border-yellow-600 text-yellow-100 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Configuration requise!</strong> <span class="block sm:inline">Aucune console n'a d'ID ScreenScraper configuré...</span>
        </div>
     <?php endif; ?>

    
    <!-- Affichage du formulaire OU de la page de traitement SSE -->
    <?php if ($processing_done && $upload_token): // Si l'upload initial a réussi, on affiche la zone SSE ?>
        <div id="sse-processing" class="animate__animated animate__fadeIn">
            <h2 class="text-2xl font-semibold font-heading mb-4 text-text-accent">Traitement en cours...</h2>
            <p class="text-text-secondary mb-4">Le système traite les fichiers ROMs uploadés. Veuillez ne pas fermer cette page.</p>

            <div id="overall-status" class="text-text-primary">Initialisation...</div>
            <div class="progress-bar-container">
                <div id="progress-bar" class="progress-bar">0%</div>
            </div>

            <div id="sse-results">
                <h3 class="text-text-accent">Détails du traitement :</h3>
                <ul id="sse-log" class="space-y-1">
                    <!-- Les logs apparaitront ici -->
                </ul>
            </div>

             <div id="sse-summary" style="display: none;" class="mt-6">
                 <h3 class="text-text-accent">Résumé</h3>
                 <p>Traitement terminé.</p>
                 <p>Succès : <span id="summary-success">0</span></p>
                 <p>Ignorés (incomplets) : <span id="summary-skipped">0</span></p>
                 <p>Erreurs : <span id="summary-errors">0</span></p>
                 <div class="mt-4 text-center">
                     <a href="add_game_auto_bulk.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" class="form-button back-button"><i class="fas fa-plus mr-2"></i> Ajouter d'autres ROMs</a>
                      <a href="index.php" class="form-button cancel-button ml-4"><i class="fas fa-list mr-2"></i> Retour à la liste</a>
                      <!-- Optionnel: lien vers ajout manuel si des jeux ont été skipped -->
                      <a href="add_game.php<?= $console_id_selected ? '?console_id='.$console_id_selected : '' ?>" id="link-manual-add" style="display: none;" class="form-button add-button ml-4">
                            <i class="fas fa-plus-circle mr-2"></i>Voir/Ajouter les jeux ignorés
                      </a>
                 </div>
             </div>
        </div>

        <script>
            const sseLog = document.getElementById('sse-log');
            const overallStatus = document.getElementById('overall-status');
            const progressBar = document.getElementById('progress-bar');
            const sseSummary = document.getElementById('sse-summary');
            const summarySuccess = document.getElementById('summary-success');
            const summarySkipped = document.getElementById('summary-skipped');
            const summaryErrors = document.getElementById('summary-errors');
            const linkManualAdd = document.getElementById('link-manual-add');

            let countSuccess = 0;
            let countSkipped = 0;
            let countErrors = 0;
            let totalFiles = 0; // Sera mis à jour par le premier message SSE

            // Vérifier si EventSource est supporté
            if (typeof(EventSource) !== "undefined") {
                // Créer une connexion SSE vers le script de traitement
                // Passer le token d'upload en paramètre GET
                const eventSource = new EventSource('process_bulk_sse.php?token=<?= urlencode($upload_token) ?>');
                overallStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Connexion au processus de traitement...';

                // Listener pour les messages de type 'progress'
                eventSource.addEventListener('progress', function(event) {
                    const data = JSON.parse(event.data);
                    totalFiles = data.total; // Mettre à jour le total si ce n'est pas déjà fait
                    const percent = totalFiles > 0 ? Math.round((data.index / totalFiles) * 100) : 0;
                    overallStatus.innerHTML = `<i class="fas fa-sync fa-spin mr-2"></i> Traitement fichier ${data.index} sur ${data.total}: <span class="font-mono">${data.filename}</span> (${data.status})...`;
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                });

                // Listener pour les messages de type 'log' (infos générales)
                eventSource.addEventListener('log', function(event) {
                    const data = JSON.parse(event.data);
                    const logItem = document.createElement('li');
                    logItem.className = 'sse-item text-gray-400 text-xs';
                    logItem.innerHTML = `<i class="fas fa-info-circle mr-1"></i> ${data.message}`;
                    sseLog.appendChild(logItem);
                    sseLog.scrollTop = sseLog.scrollHeight; // Auto-scroll
                });


                // Listener pour les messages de type 'success'
                eventSource.addEventListener('success', function(event) {
                    const data = JSON.parse(event.data);
                    countSuccess++;
                    const logItem = document.createElement('li');
                    logItem.className = 'sse-item text-green-400';
                    logItem.innerHTML = `<i class="fas fa-check-circle mr-2"></i> <span class="sse-filename">${data.filename}</span> -> Ajouté: "${data.title}"`;
                    sseLog.appendChild(logItem);
                    sseLog.scrollTop = sseLog.scrollHeight;
                });

                // Listener pour les messages de type 'skipped'
                eventSource.addEventListener('skipped', function(event) {
                    const data = JSON.parse(event.data);
                    countSkipped++;
                    const logItem = document.createElement('li');
                    logItem.className = 'sse-item text-yellow-400';
                    logItem.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i> <span class="sse-filename">${data.filename}</span> -> Ignoré (Manuel requis): "${data.title}" - Raison: ${data.reason}`;
                    sseLog.appendChild(logItem);
                    sseLog.scrollTop = sseLog.scrollHeight;
                    linkManualAdd.style.display = 'inline-block'; // Afficher le lien vers l'ajout manuel
                });

                // Listener pour les messages de type 'error'
                eventSource.addEventListener('error_event', function(event) { // Nommé 'error_event' pour ne pas interférer avec eventSource.onerror
                    const data = JSON.parse(event.data);
                    countErrors++;
                    const logItem = document.createElement('li');
                    logItem.className = 'sse-item text-red-400';
                    logItem.innerHTML = `<i class="fas fa-times-circle mr-2"></i> <span class="sse-filename">${data.filename}</span> -> ERREUR: ${data.message}`;
                    sseLog.appendChild(logItem);
                    sseLog.scrollTop = sseLog.scrollHeight;
                });

                 // Listener pour la fin du processus
                eventSource.addEventListener('complete', function(event) {
                    const data = JSON.parse(event.data);
                    overallStatus.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${data.message}`;
                    progressBar.style.width = '100%';
                    progressBar.textContent = '100%';
                    progressBar.classList.add('bg-green-600'); // Changer couleur en vert
                    progressBar.classList.remove('bg-indigo-600');

                    // Afficher le résumé
                    summarySuccess.textContent = countSuccess;
                    summarySkipped.textContent = countSkipped;
                    summaryErrors.textContent = countErrors;
                    sseSummary.style.display = 'block';

                    // Fermer la connexion SSE
                    eventSource.close();
                     console.log("SSE connection closed.");
                });

                // Gestion des erreurs de connexion SSE
                eventSource.onerror = function(err) {
                    console.error("EventSource failed:", err);
                    overallStatus.innerHTML = '<i class="fas fa-times-circle mr-2 text-red-500"></i> Erreur de connexion avec le serveur de traitement. Le processus a peut-être été interrompu.';
                    progressBar.classList.add('bg-red-600');
                     progressBar.classList.remove('bg-indigo-600');
                     progressBar.textContent = 'Erreur';
                    sseSummary.style.display = 'block'; // Afficher le résumé partiel quand même
                    summarySuccess.textContent = countSuccess;
                    summarySkipped.textContent = countSkipped;
                    summaryErrors.textContent = countErrors;
                    eventSource.close(); // Fermer la connexion en cas d'erreur
                };

            } else {
                // Fallback si EventSource n'est pas supporté
                document.getElementById('sse-processing').innerHTML = '<p class="text-red-500">Désolé, votre navigateur ne supporte pas les mises à jour en temps réel (Server-Sent Events). Le traitement ne peut pas démarrer.</p>';
            }
        </script>

    <?php else: // Sinon (pas POST ou upload initial échoué), afficher le formulaire ?>
        <div class="admin-form-container animate__animated animate__fadeInUp">
             <p class="text-text-secondary mb-6 text-sm">
                Sélectionnez la console et téléchargez un ou plusieurs fichiers ROM. Le traitement se fera en arrière-plan avec un retour en temps réel.
            </p>
            <form action="add_game_auto_bulk.php" method="post" enctype="multipart/form-data" id="auto-add-bulk-form" novalidate>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <!-- Champs console et rom (inchangés) -->
                    <div class="form-group md:col-span-1">
                        <label for="console_id">Console :<span class="text-red-500">*</span></label>
                        <select id="console_id" name="console_id" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                            <option value="" disabled <?= empty($console_id_selected) ? 'selected' : '' ?>>-- Sélectionner --</option>
                            <?php foreach ($consoles as $c): ?> <option value="<?= $c['id'] ?>" <?= ($console_id_selected == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?> <?= !empty($c['ss_id']) ? '(ID:'.$c['ss_id'].')' : '' ?></option> <?php endforeach; ?>
                        </select>
                        <?php if ($noConsolesConfigured): ?> <p class="text-xs text-yellow-400 mt-1">Aucune console configurée.</p> <?php endif; ?>
                    </div>
                    <div class="form-group md:col-span-1">
                        <label for="rom">Fichiers ROM :<span class="text-red-500">*</span></label>
                        <input type="file" id="rom" name="rom[]" multiple accept=".zip,.sfc,.smc,.fig,.bin,.gba,.gbc,.gb,.nes,.pce,.md,.mgd,.sms,.gg,.col,.ngp,.ngc,.ws,.wsc,.7z,.iso,.cue,.chd" required <?= $noConsolesConfigured ? 'disabled' : '' ?>>
                        <p class="text-xs text-text-secondary mt-1">Sélectionnez un ou plusieurs fichiers.</p>
                        <p class="text-xs text-yellow-400 mt-1">Limites serveur: <?= ini_get('max_file_uploads') ?> fichiers, <?= ini_get('upload_max_filesize') ?>/fichier, <?= ini_get('post_max_size') ?> total.</p>
                    </div>
                </div>
                <!-- PAS de message de chargement ici, la page se recharge -->
                <div class="form-actions mt-8">
                    <a href="index.php" class="form-button cancel-button"><i class="fas fa-times mr-2"></i>Annuler</a>
                    <button type="submit" class="form-button submit-button" id="submit-auto-add-bulk" <?= $noConsolesConfigured ? 'disabled' : '' ?>><i class="fas fa-cloud-upload-alt mr-2"></i>Lancer l'ajout</button>
                </div>
            </form>
        </div>
        <!-- Script JS pour la validation du formulaire initial -->
         <script>
            const form = document.getElementById('auto-add-bulk-form');
            const submitButton = document.getElementById('submit-auto-add-bulk');
            // Pas besoin de loadingMessage ici
            const consoleSelect = document.getElementById('console_id');
            const romInput = document.getElementById('rom');

             if (form && submitButton && consoleSelect && romInput) {
                 form.addEventListener('submit', function(event) {
                     // Validation client (identique à avant)
                     let valid = true; let errorMsg = '';
                     consoleSelect.style.borderColor = ''; romInput.style.borderColor = '';
                     const existingErrorDiv = document.querySelector('.form-client-errors'); // Cibler les erreurs client uniquement
                     if (existingErrorDiv) { existingErrorDiv.remove(); }
                     if (!consoleSelect.value) { consoleSelect.style.borderColor = 'red'; valid = false; errorMsg += '<li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>Veuillez sélectionner une console.</li>'; }
                     if (romInput.files.length === 0) { romInput.style.borderColor = 'red'; valid = false; errorMsg += '<li><i class="fas fa-exclamation-circle text-red-400 mr-2"></i>Veuillez sélectionner au moins un fichier ROM.</li>'; }
                     const maxFiles = <?= (int)ini_get('max_file_uploads') ?>;
                     if (romInput.files.length > maxFiles) { errorMsg += `<li><i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i>Attention: ${romInput.files.length} fichiers sélectionnés, limite serveur ${maxFiles}.</li>`; }

                     if (!valid) {
                         event.preventDefault();
                        let errorDiv = document.createElement('div');
                        // Ajouter la classe spécifique pour pouvoir la supprimer
                        errorDiv.className = 'form-errors form-client-errors animate__animated animate__shakeX mb-6';
                        errorDiv.innerHTML = `<p class="font-bold mb-2">Erreurs Formulaire:</p><ul>${errorMsg}</ul>`;
                        // Insérer avant le formulaire, mais dans le conteneur principal
                        form.parentNode.insertBefore(errorDiv, form);
                        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                         return;
                     }
                     // Si valide, désactiver le bouton pour éviter double soumission pendant le chargement de la page suivante
                     submitButton.disabled = true;
                     submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Préparation...';
                     // Pas besoin d'afficher le message de chargement JS ici
                 });
             } else { console.error("Erreur JS: Eléments formulaire manquants."); }
         </script>
    <?php endif; ?>

</div> <!-- Fin admin-container -->

</body>
</html>