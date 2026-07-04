<?php
require_once '../config.php'; // Chemin relatif vers config.php

// --- Initialisation Variables ---
$title = '';
$console_id = '';
$description = '';
$year = date('Y'); // Pré-remplir avec l'année actuelle
$publisher = '';
$sort_order = 0;
$errors = [];
$consoles = []; // Initialiser consoles

// --- Vérification des droits d'accès ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// --- Récupération des consoles ---
try {
    $consoles = $db->query("SELECT id, name FROM consoles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des consoles.";
    error_log("Admin Add Game - Error fetching consoles: " . $e->getMessage());
}


// --- Traitement du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $title = trim($_POST['title'] ?? '');
    $console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT); // Valider comme entier
    $description = trim($_POST['description'] ?? '');
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT); // Valider comme entier
    $publisher = trim($_POST['publisher'] ?? '');
    $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    // Validation des données de base
    if (empty($title)) $errors[] = "Le titre est obligatoire.";
    if ($console_id === false || $console_id === null) $errors[] = "La console est obligatoire.";
    if ($year === false || $year === null) $errors[] = "L'année doit être un nombre valide.";
    elseif ($year < 1950 || $year > date('Y') + 5) $errors[] = "L'année semble invalide."; // Validation basique de l'année
    if ($sort_order === false) $errors[] = "L'ordre de tri doit être un nombre.";


    // Vérification de la console sélectionnée
    $console = null;
    if ($console_id) {
        try {
            $stmt = $db->prepare("SELECT slug, name FROM consoles WHERE id = ?");
            $stmt->execute([$console_id]);
            $console = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$console) $errors[] = "La console sélectionnée est invalide.";
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de la vérification de la console.";
             error_log("Admin Add Game - Console check error: " . $e->getMessage());
        }
    }

    // --- Gestion des fichiers seulement si pas d'erreurs de base ---
    $rom_path_relative = '';
    $cover_path_relative = '';
    $preview_path_relative = '';

    if (empty($errors) && $console) {
        // Création slug et chemin de base
        $consoleSlug = $console['slug'];
        // Slug plus robuste
        $gameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
         if(empty($gameSlug)) $gameSlug = 'game-' . time(); // Fallback si le titre ne contient que des caractères spéciaux

        $basePath = rtrim(ROMS_PATH, '/') . '/' . $consoleSlug . '/' ; // Chemin vers dossier console
        $gameDir = $basePath . $gameSlug . '/'; // Dossier spécifique au jeu

        // Création des répertoires (plus sûr)
        $directories = [$gameDir, $gameDir . 'images/', $gameDir . 'preview/'];
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0775, true)) { // Utiliser 0775 et vérifier le retour
                    $errors[] = "Impossible de créer le répertoire : " . $dir;
                    // Stopper si un répertoire critique ne peut être créé
                    if ($dir === $gameDir) break;
                }
            } elseif (!is_writable($dir)) {
                 $errors[] = "Le répertoire n'est pas accessible en écriture : " . $dir;
            }
        }

        // Si pas d'erreur de répertoire, traiter les fichiers
        if (empty($errors)) {
            // --- ROM ---
            if (isset($_FILES['rom']) && $_FILES['rom']['error'] === UPLOAD_ERR_OK) {
                $rom_original_name = $_FILES['rom']['name'];
                $rom_ext = strtolower(pathinfo($rom_original_name, PATHINFO_EXTENSION));
                // Accepter les extensions définies dans le formulaire
                $allowed_rom_ext = ['zip','sfc','smc','fig','bin','gba','gbc','gb','nes','pce','md','mgd','sms','gg','col','ngp','ngc','ws','wsc'];
                if (!in_array($rom_ext, $allowed_rom_ext)) {
                     $errors[] = "Extension de ROM non autorisée: .$rom_ext";
                } else {
                    $rom_filename = $gameSlug . '.' . $rom_ext;
                    $rom_destination = $gameDir . $rom_filename;

                    if (!move_uploaded_file($_FILES['rom']['tmp_name'], $rom_destination)) {
                        $errors[] = "Erreur technique lors du déplacement de la ROM.";
                    } else {
                        // Chemin relatif depuis la racine du site web
                        $rom_path_relative = '/roms/' . $consoleSlug . '/' . $gameSlug . '/' . $rom_filename;
                    }
                }
            } elseif (isset($_FILES['rom']) && $_FILES['rom']['error'] !== UPLOAD_ERR_NO_FILE) {
                 // Erreur autre que "pas de fichier"
                 $errors[] = "Erreur lors de l'upload de la ROM (code: " . $_FILES['rom']['error'] . ").";
            } else {
                 $errors[] = "Le fichier ROM est obligatoire.";
            }


            // --- Cover ---
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                 $cover_original_name = $_FILES['cover']['name'];
                 $cover_ext = strtolower(pathinfo($cover_original_name, PATHINFO_EXTENSION));
                 $allowed_cover_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Ajouter webp ?

                if (!in_array($cover_ext, $allowed_cover_ext)) {
                    $errors[] = "Format de l'image de couverture non autorisé: .$cover_ext";
                } else {
                    $cover_filename = $gameSlug . '.' . $cover_ext;
                    $cover_destination = $gameDir . 'images/' . $cover_filename;
                    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $cover_destination)) {
                        $errors[] = "Erreur technique lors du déplacement de l'image de couverture.";
                    } else {
                         $cover_path_relative = '/roms/' . $consoleSlug . '/' . $gameSlug . '/images/' . $cover_filename;
                    }
                }
            } elseif (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
                 $errors[] = "Erreur lors de l'upload de l'image de couverture (code: " . $_FILES['cover']['error'] . ").";
            } else {
                 $errors[] = "L'image de couverture est obligatoire.";
            }


            // --- Preview (Optionnelle) ---
            if (isset($_FILES['preview']) && $_FILES['preview']['error'] === UPLOAD_ERR_OK) {
                 $preview_original_name = $_FILES['preview']['name'];
                 $preview_ext = strtolower(pathinfo($preview_original_name, PATHINFO_EXTENSION));

                if (!in_array($preview_ext, ['mp4', 'webm'])) { // Accepter webm aussi ?
                    $errors[] = "Format de la preview non autorisé (MP4 ou WEBM requis): .$preview_ext";
                } else {
                    $preview_filename = $gameSlug . '.' . $preview_ext;
                    $preview_destination = $gameDir . 'preview/' . $preview_filename;
                    if (!move_uploaded_file($_FILES['preview']['tmp_name'], $preview_destination)) {
                        $errors[] = "Erreur technique lors du déplacement de la preview.";
                    } else {
                        $preview_path_relative = '/roms/' . $consoleSlug . '/' . $gameSlug . '/preview/' . $preview_filename;
                    }
                }
            } elseif (isset($_FILES['preview']) && $_FILES['preview']['error'] !== UPLOAD_ERR_NO_FILE) {
                 $errors[] = "Erreur lors de l'upload de la preview (code: " . $_FILES['preview']['error'] . ").";
            }
            // Pas d'erreur si la preview est absente (elle est optionnelle)
        }
    }


    // --- Insertion en base de données (si aucune erreur) ---
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path, sort_order)
                VALUES (:console_id, :title, :description, :year, :publisher, :cover, :preview, :rom_path, :sort_order)
            ");
            $stmt->execute([
                ':console_id' => $console_id,
                ':title' => $title,
                ':description' => $description,
                ':year' => $year,
                ':publisher' => $publisher ?: null, // Insérer NULL si vide
                ':cover' => $cover_path_relative,
                ':preview' => $preview_path_relative ?: null, // Insérer NULL si vide
                ':rom_path' => $rom_path_relative,
                ':sort_order' => $sort_order
            ]);

             // Ajouter un message de succès en session pour l'afficher sur index.php
             $_SESSION['admin_flash_message'] = [
                 'type' => 'success',
                 'message' => 'Jeu "' . htmlspecialchars($title) . '" ajouté avec succès !'
             ];

            header('Location: ./'); // Redirige vers la liste
            exit();
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de l'insertion dans la base de données.";
             error_log("Admin Add Game - DB Insert error: " . $e->getMessage());
             // Optionnel : Supprimer les fichiers uploadés en cas d'échec DB ?
             // if (!empty($rom_destination) && file_exists($rom_destination)) unlink($rom_destination);
             // if (!empty($cover_destination) && file_exists($cover_destination)) unlink($cover_destination);
             // if (!empty($preview_destination) && file_exists($preview_destination)) unlink($preview_destination);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
    <link rel="stylesheet" href="public/css/admin_style.css?v=<?= @filemtime(__DIR__ . "/public/css/admin_style.css") ?>">
</head>
<body class="bg-background text-text-primary">

    <div class="app-container">
        <!-- Premium Header -->
        <header class="glass nav-bar animate-fade-in" style="margin-bottom: 40px;">
            <div class="flex items-center gap-4">
                <div style="background: rgba(0, 242, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px var(--primary-glow);">
                    <i class="fas fa-plus-circle text-xl text-primary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_add_game_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;"><?= __('admin_node_retro_version') ?></span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-errors animate-fade-in">
                <p class="font-bold mb-2"><?= __('error') ?> :</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="glass p-8 animate-fade-in" style="animation-delay: 0.2s;">
            <form action="add_game" method="post" enctype="multipart/form-data" novalidate>
                 <div class="form-grid">

                    <div class="form-group col-span-2">
                        <label for="title"><?= __('admin_title_label') ?></label>
                        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="e.g. Super Mario World" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="console_id"><?= __('admin_console') ?></label>
                        <select id="console_id" name="console_id" class="form-control" required>
                            <option value="" disabled <?= empty($console_id) ? 'selected' : '' ?>><?= __('admin_choose_console') ?></option>
                            <?php foreach ($consoles as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($console_id == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year"><?= __('admin_year_label') ?></label>
                        <input type="number" id="year" name="year" min="1950" max="<?= date('Y') + 5 ?>" step="1" value="<?= htmlspecialchars($year) ?>" class="form-control" required>
                    </div>

                     <div class="form-group col-span-2">
                        <label for="description"><?= __('admin_desc_label') ?></label>
                        <textarea id="description" name="description" rows="4" class="form-control" placeholder="<?= __('admin_desc_placeholder') ?>"><?= htmlspecialchars($description) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="publisher"><?= __('admin_pub_label') ?></label>
                        <input type="text" id="publisher" name="publisher" value="<?= htmlspecialchars($publisher) ?>" class="form-control" placeholder="CAPCOM, Nintendo...">
                    </div>

                     <div class="form-group">
                        <label for="sort_order"><?= __('admin_sort_label') ?> (<?= __('admin_optional') ?>)</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($sort_order)?>" step="1" class="form-control" placeholder="0">
                    </div>


                    <div class="form-group col-span-2 pt-6" style="border-top: 1px solid var(--glass-border);">
                        <label for="rom"><?= __('admin_rom_label') ?> *</label>
                        <input type="file" id="rom" name="rom" class="form-control" required>
                         <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; opacity: 0.6;"><?= __('admin_formats_supported') ?>: zip, sfc, smc, bin, gba, gbc, gb, nes, pce, md, 7z, iso, cue.</p>
                    </div>

                    <div class="form-group">
                        <label for="cover"><?= __('admin_cover_label') ?> *</label>
                        <input type="file" id="cover" name="cover" accept="image/*" class="form-control" required>
                         <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; opacity: 0.6;"><?= __('admin_formats_supported') ?>: JPG, PNG, WEBP.</p>
                        <div id="cover-preview" class="file-preview" style="display: none; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 16px; border: 1px dashed var(--glass-border);"></div>
                    </div>

                    <div class="form-group">
                        <label for="preview"><?= __('admin_video_label') ?> (<?= __('admin_optional') ?>)</label>
                        <input type="file" id="preview" name="preview" accept="video/mp4,video/webm" class="form-control">
                        <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; opacity: 0.6;"><?= __('admin_formats_supported') ?>: MP4, WEBM.</p>
                        <div id="video-preview" class="file-preview" style="display: none; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 16px; border: 1px dashed var(--glass-border);"></div>
                    </div>

                 </div>

                <div class="flex justify-end gap-4 mt-12 pt-8" style="border-top: 1px solid var(--glass-border);">
                    <a href="index.php" class="btn-modern btn-secondary">
                         <i class="fas fa-times"></i> <?= __('admin_cancel') ?>
                    </a>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="fas fa-save"></i> <?= __('admin_save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setupFilePreview(inputId, previewContainerId) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);

            if (input && previewContainer) {
                input.addEventListener('change', function(event) {
                    previewContainer.innerHTML = ''; // Vide la prévisualisation précédente
                    previewContainer.style.display = 'none'; // Cache par défaut

                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            let previewElement;
                            if (file.type.startsWith('image/')) {
                                previewElement = document.createElement('img');
                                previewElement.alt = 'Prévisualisation';
                            } else if (file.type.startsWith('video/')) {
                                previewElement = document.createElement('video');
                                previewElement.controls = true;
                                previewElement.muted = true; // Pour autoplay éventuel
                            }

                            if (previewElement) {
                                previewElement.src = e.target.result;
                                previewContainer.appendChild(previewElement);
                                previewContainer.style.display = 'block'; // Affiche le conteneur
                            }
                        }
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                 console.warn(`Élément input #${inputId} ou preview #${previewContainerId} non trouvé.`);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupFilePreview('cover', 'cover-preview');
            setupFilePreview('preview', 'video-preview');
        });
    </script>
</body>
</html>
