<?php
require_once '../config.php'; // Chemin relatif

// --- Initialisation et Sécurité ---
$errors = [];
$game_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$game = null;
$consoles = [];
$title = '';
$console_id = '';
$description = '';
$year = '';
$publisher = '';
$sort_order = 0; // Ajout variable sort_order

// Vérification des droits d'accès admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// --- Récupération des données initiales ---
if (!$game_id) {
    $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => 'ID de jeu manquant ou invalide.'];
    header('Location: index.php');
    exit();
}

try {
    // Récupère les infos du jeu à modifier (avec jointure pour slug console)
    $stmtGame = $db->prepare("
        SELECT g.*, c.name as console_name, c.slug as console_slug
        FROM games g
        JOIN consoles c ON g.console_id = c.id
        WHERE g.id = ?
    ");
    $stmtGame->execute([$game_id]);
    $game = $stmtGame->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
         $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => 'Jeu non trouvé.'];
        header('Location: index.php');
        exit();
    }

    // Pré-remplir les variables pour le formulaire
    $title = $game['title'];
    $console_id = $game['console_id'];
    $description = $game['description'];
    $year = $game['year'];
    $publisher = $game['publisher'];
    $sort_order = $game['sort_order']; // Récupérer sort_order

    // Récupère la liste des consoles
    $consoles = $db->query("SELECT id, name FROM consoles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des données du jeu ou des consoles.";
    error_log("Admin Edit Game - DB Fetch error: " . $e->getMessage());
    // Afficher une erreur ou rediriger ? Pour l'instant on continue pour afficher l'erreur dans le formulaire.
}

// --- Traitement du formulaire (si soumis) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données postées
    $title = trim($_POST['title'] ?? '');
    $new_console_id = filter_input(INPUT_POST, 'console_id', FILTER_VALIDATE_INT);
    $description = trim($_POST['description'] ?? '');
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
    $publisher = trim($_POST['publisher'] ?? '');
    $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]); // Mise à jour sort_order

    // --- Validation ---
    if (empty($title)) $errors[] = "Le titre est obligatoire.";
    if ($new_console_id === false || $new_console_id === null) $errors[] = "La console est obligatoire.";
    if ($year === false || $year === null) $errors[] = "L'année doit être un nombre valide.";
    elseif ($year < 1950 || $year > date('Y') + 5) $errors[] = "L'année semble invalide.";
    if ($sort_order === false) $errors[] = "L'ordre de tri doit être un nombre."; // Validation sort_order

    // Vérification de la NOUVELLE console sélectionnée
    $selectedConsole = null;
    if ($new_console_id) {
         try {
            $stmtConsoleCheck = $db->prepare("SELECT slug, name FROM consoles WHERE id = ?");
            $stmtConsoleCheck->execute([$new_console_id]);
            $selectedConsole = $stmtConsoleCheck->fetch(PDO::FETCH_ASSOC);
            if (!$selectedConsole) $errors[] = "La nouvelle console sélectionnée est invalide.";
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de la vérification de la nouvelle console.";
             error_log("Admin Edit Game - New Console check error: " . $e->getMessage());
        }
    }

    // --- Gestion déplacement/renommage des fichiers si console ou titre change ---
    $consoleHasChanged = $game['console_id'] != $new_console_id;
    $titleHasChanged = $game['title'] != $title;
    $pathsNeedUpdate = $consoleHasChanged || $titleHasChanged;
    $newBasePath = ''; // Chemin absolu disque
    $newBaseRelativePath = ''; // Chemin relatif web
    $oldGameDirAbsolute = rtrim(ROMS_PATH, '/') . '/' . $game['console_slug'] . '/' . strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $game['title'])), '-'));

    if (empty($errors) && $selectedConsole && $pathsNeedUpdate) {
        $newConsoleSlug = $selectedConsole['slug'];
        $newGameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
        if(empty($newGameSlug)) $newGameSlug = 'game-' . time(); // Fallback

        $newGameDirAbsolute = rtrim(ROMS_PATH, '/') . '/' . $newConsoleSlug . '/' . $newGameSlug . '/';
        $newBaseRelativePath = '/roms/' . $newConsoleSlug . '/' . $newGameSlug . '/'; // Pour la BDD

         // Vérifier si l'ancien dossier existe et si le nouveau n'existe pas déjà
         if (is_dir($oldGameDirAbsolute)) {
            if (is_dir($newGameDirAbsolute)) {
                 // Si les deux existent et sont différents, c'est un conflit
                 if (realpath($oldGameDirAbsolute) != realpath($newGameDirAbsolute)) {
                    $errors[] = "Conflit: Un dossier pour '$title' sur la console '{$selectedConsole['name']}' existe déjà.";
                 }
                 // Si ce sont les mêmes (juste casse différente), pas besoin de renommer
            } else {
                 // Créer le répertoire parent si nécessaire
                if (!is_dir(dirname($newGameDirAbsolute))) {
                     if (!mkdir(dirname($newGameDirAbsolute), 0775, true)) {
                         $errors[] = "Impossible de créer le répertoire parent : " . dirname($newGameDirAbsolute);
                     }
                 }
                 // Essayer de renommer/déplacer le dossier
                 if (empty($errors) && !rename($oldGameDirAbsolute, $newGameDirAbsolute)) {
                     $errors[] = "Erreur lors du déplacement/renommage du dossier du jeu de '$oldGameDirAbsolute' vers '$newGameDirAbsolute'. Vérifiez les permissions.";
                 } else if (empty($errors)) {
                     // Succès du renommage, mettre à jour le chemin de base pour les fichiers
                     $newBasePath = $newGameDirAbsolute;
                     // Les chemins relatifs seront recalculés plus bas
                 }
            }
        } else {
             // L'ancien dossier n'existe pas, créer le nouveau si nécessaire
              $newBasePath = $newGameDirAbsolute;
              $directories = [$newBasePath, $newBasePath . 'images/', $newBasePath . 'preview/'];
              foreach ($directories as $dir) {
                  if (!is_dir($dir)) {
                      if (!mkdir($dir, 0775, true)) {
                          $errors[] = "Impossible de créer le répertoire : " . $dir;
                          if ($dir === $newBasePath) break;
                      }
                  } elseif (!is_writable($dir)) {
                       $errors[] = "Le répertoire n'est pas accessible en écriture : " . $dir;
                  }
              }
        }
    } elseif (empty($errors) && $selectedConsole) {
         // Pas de changement de console/titre, on utilise les chemins existants
         $newGameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $game['title'])), '-'));
         $newBasePath = $oldGameDirAbsolute . '/'; // Chemin disque existant
         $newBaseRelativePath = '/roms/' . $game['console_slug'] . '/' . $newGameSlug . '/'; // Chemin relatif existant
         // S'assurer que les sous-dossiers existent au cas où
          $directories = [$newBasePath . 'images/', $newBasePath . 'preview/'];
           foreach ($directories as $dir) {
               if (!is_dir($dir)) { mkdir($dir, 0775, true); }
           }
    }

    // --- Gestion upload fichiers (SI pas d'erreur précédente) ---
    $rom_path_relative = $game['rom_path']; // Garde l'ancien par défaut
    $cover_path_relative = $game['cover'];
    $preview_path_relative = $game['preview'];

    if (empty($errors)) {
        // --- ROM ---
        if (isset($_FILES['rom']) && $_FILES['rom']['error'] === UPLOAD_ERR_OK) {
            $rom_original_name = $_FILES['rom']['name'];
            $rom_ext = strtolower(pathinfo($rom_original_name, PATHINFO_EXTENSION));
            $allowed_rom_ext = ['zip','sfc','smc','fig','bin','gba','gbc','gb','nes','pce','md','mgd','sms','gg','col','ngp','ngc','ws','wsc','7z','iso','cue'];
            if (!in_array($rom_ext, $allowed_rom_ext)) {
                 $errors[] = "Extension de ROM non autorisée: .$rom_ext";
            } else {
                // Utiliser le nouveau slug pour le nom de fichier
                $newGameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
                $rom_filename = $newGameSlug . '.' . $rom_ext;
                $rom_destination = $newBasePath . $rom_filename; // Utilise le nouveau chemin de base

                // Supprimer l'ancienne ROM seulement si elle existe et est différente du nouveau chemin
                if (!empty($game['rom_path']) && $game['rom_path'] !== $newBaseRelativePath . $rom_filename) {
                    $old_rom_absolute_path = rtrim(ROMS_PATH, '/') . '/' . ltrim($game['rom_path'], '/');
                     if(file_exists($old_rom_absolute_path)) {
                        @unlink($old_rom_absolute_path); // Supprime l'ancienne ROM (utiliser @ pour ignorer les erreurs si déjà supprimée)
                     }
                }

                if (!move_uploaded_file($_FILES['rom']['tmp_name'], $rom_destination)) {
                    $errors[] = "Erreur technique lors du déplacement de la nouvelle ROM.";
                } else {
                    $rom_path_relative = $newBaseRelativePath . $rom_filename; // Met à jour avec le nouveau chemin relatif
                }
            }
        } elseif (isset($_FILES['rom']) && $_FILES['rom']['error'] !== UPLOAD_ERR_NO_FILE) {
             $errors[] = "Erreur lors de l'upload de la ROM (code: " . $_FILES['rom']['error'] . ").";
        } elseif ($pathsNeedUpdate && !empty($game['rom_path'])) {
            // Si on a renommé/déplacé le dossier, mettre à jour le chemin relatif de la ROM existante
            $rom_path_relative = $newBaseRelativePath . basename($game['rom_path']);
        }

        // --- Cover --- (Logique similaire à ROM)
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
             $cover_original_name = $_FILES['cover']['name'];
             $cover_ext = strtolower(pathinfo($cover_original_name, PATHINFO_EXTENSION));
             $allowed_cover_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
             if (!in_array($cover_ext, $allowed_cover_ext)) {
                $errors[] = "Format de l'image de couverture non autorisé: .$cover_ext";
             } else {
                $newGameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
                $cover_filename = $newGameSlug . '.' . $cover_ext;
                $cover_destination = $newBasePath . 'images/' . $cover_filename;

                // Supprimer l'ancienne cover si elle existe et est différente
                if (!empty($game['cover']) && $game['cover'] !== $newBaseRelativePath . 'images/' . $cover_filename) {
                    $old_cover_absolute_path = rtrim(ROMS_PATH, '/') . '/' . ltrim($game['cover'], '/');
                    if(file_exists($old_cover_absolute_path)) {
                       @unlink($old_cover_absolute_path);
                    }
                }

                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $cover_destination)) {
                    $errors[] = "Erreur technique lors du déplacement de la nouvelle image de couverture.";
                } else {
                    $cover_path_relative = $newBaseRelativePath . 'images/' . $cover_filename;
                }
             }
        } elseif (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
             $errors[] = "Erreur lors de l'upload de l'image de couverture (code: " . $_FILES['cover']['error'] . ").";
        } elseif ($pathsNeedUpdate && !empty($game['cover'])) {
            // Si on a renommé/déplacé le dossier, mettre à jour le chemin relatif de la cover existante
             $cover_path_relative = $newBaseRelativePath . 'images/' . basename($game['cover']);
        }

        // --- Preview --- (Logique similaire)
         if (isset($_FILES['preview']) && $_FILES['preview']['error'] === UPLOAD_ERR_OK) {
             $preview_original_name = $_FILES['preview']['name'];
             $preview_ext = strtolower(pathinfo($preview_original_name, PATHINFO_EXTENSION));
             $allowed_preview_ext = ['mp4', 'webm'];
             if (!in_array($preview_ext, $allowed_preview_ext)) {
                $errors[] = "Format de la preview non autorisé (MP4 ou WEBM requis): .$preview_ext";
             } else {
                $newGameSlug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $title)), '-'));
                $preview_filename = $newGameSlug . '.' . $preview_ext;
                $preview_destination = $newBasePath . 'preview/' . $preview_filename;

                // Supprimer l'ancienne preview si elle existe et est différente
                if (!empty($game['preview']) && $game['preview'] !== $newBaseRelativePath . 'preview/' . $preview_filename) {
                     $old_preview_absolute_path = rtrim(ROMS_PATH, '/') . '/' . ltrim($game['preview'], '/');
                     if(file_exists($old_preview_absolute_path)) {
                        @unlink($old_preview_absolute_path);
                     }
                }

                if (!move_uploaded_file($_FILES['preview']['tmp_name'], $preview_destination)) {
                    $errors[] = "Erreur technique lors du déplacement de la nouvelle preview.";
                } else {
                    $preview_path_relative = $newBaseRelativePath . 'preview/' . $preview_filename;
                }
             }
        } elseif (isset($_FILES['preview']) && $_FILES['preview']['error'] !== UPLOAD_ERR_NO_FILE) {
             $errors[] = "Erreur lors de l'upload de la preview (code: " . $_FILES['preview']['error'] . ").";
        } elseif ($pathsNeedUpdate && !empty($game['preview'])) {
             // Si on a renommé/déplacé le dossier, mettre à jour le chemin relatif de la preview existante
             $preview_path_relative = $newBaseRelativePath . 'preview/' . basename($game['preview']);
        }

    } // Fin if(empty($errors)) pour upload

    // --- Mise à jour en base de données (si toujours pas d'erreur) ---
    if (empty($errors)) {
        try {
            $stmtUpdate = $db->prepare("
                UPDATE games
                SET title = :title,
                    console_id = :console_id,
                    description = :description,
                    year = :year,
                    publisher = :publisher,
                    rom_path = :rom_path,
                    cover = :cover,
                    preview = :preview,
                    sort_order = :sort_order
                WHERE id = :game_id
            ");
            $stmtUpdate->execute([
                ':title' => $title,
                ':console_id' => $new_console_id,
                ':description' => $description,
                ':year' => $year,
                ':publisher' => $publisher ?: null,
                ':rom_path' => $rom_path_relative,
                ':cover' => $cover_path_relative,
                ':preview' => $preview_path_relative ?: null,
                ':sort_order' => $sort_order, // Ajout sort_order
                ':game_id' => $game_id
            ]);

            $_SESSION['admin_flash_message'] = [
                'type' => 'success',
                'message' => 'Jeu "' . htmlspecialchars($title) . '" modifié avec succès !'
            ];
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de la mise à jour dans la base de données.";
             error_log("Admin Edit Game - DB Update error: " . $e->getMessage());
        }
    }

    // Si on arrive ici avec des erreurs, le formulaire sera réaffiché avec les erreurs
    // et les valeurs postées (sauf fichiers)
    $console_id = $new_console_id; // Garde la sélection de console en cas d'erreur

} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier : <?= htmlspecialchars($game['title'] ?? 'Jeu') ?> - Administration</title>
    <link rel="icon" type="image/png" href="../assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS (comme admin/index.php) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/style.css">
    <link rel="stylesheet" href="admin_style.css"> <!-- Inclure les styles admin -->
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Modifier : <?= htmlspecialchars($game['title']) ?></h1>
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="form-errors animate__animated animate__shakeX mb-6">
                <p class="font-bold mb-2">Erreurs rencontrées :</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

         <?php if ($game): // Affiche le formulaire seulement si $game a été chargé ?>
            <div class="admin-form-container animate__animated animate__fadeInUp">
                <form action="edit_game.php?id=<?= $game_id ?>" method="post" enctype="multipart/form-data" novalidate>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                        <div class="form-group md:col-span-2">
                            <label for="title">Titre du jeu :</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="console_id">Console :</label>
                            <select id="console_id" name="console_id" required>
                                <option value="" disabled>-- Choisir une console --</option>
                                <?php foreach ($consoles as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($console_id == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="year">Année de sortie :</label>
                            <input type="number" id="year" name="year" min="1950" max="<?= date('Y') + 5 ?>" step="1" value="<?= htmlspecialchars($year) ?>" required>
                        </div>

                         <div class="form-group md:col-span-2">
                            <label for="description">Description :</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="publisher">Éditeur :</label>
                            <input type="text" id="publisher" name="publisher" value="<?= htmlspecialchars($publisher) ?>">
                        </div>

                         <div class="form-group">
                            <label for="sort_order">Ordre de tri (Optionnel) :</label>
                            <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($sort_order)?>" step="1" placeholder="0">
                        </div>


                        <div class="form-group md:col-span-2 border-t border-border-color pt-6 mt-4">
                            <label for="rom">Remplacer la ROM :</label>
                            <input type="file" id="rom" name="rom" accept=".zip,.sfc,.smc,.fig,.bin,.gba,.gbc,.gb,.nes,.pce,.md,.mgd,.sms,.gg,.col,.ngp,.ngc,.ws,.wsc,.7z,.iso,.cue">
                             <?php if (!empty($game['rom_path'])): ?>
                                <p class="current-file-info"><i class="fas fa-file-archive"></i>Fichier actuel : <?= htmlspecialchars(basename($game['rom_path'])) ?></p>
                             <?php else: ?>
                                 <p class="current-file-info text-red-400"><i class="fas fa-exclamation-triangle"></i>Aucune ROM actuelle.</p>
                            <?php endif; ?>
                             <p class="text-xs text-text-secondary mt-1">Laisser vide pour conserver la ROM actuelle.</p>
                        </div>

                        <div class="form-group">
                            <label for="cover">Remplacer l'image de couverture :</label>
                            <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/gif,image/webp">
                             <?php if (!empty($game['cover'])): ?>
                                <p class="current-file-info"><i class="fas fa-image"></i>Image actuelle :</p>
                                <img src="../<?= htmlspecialchars($game['cover']) ?>" alt="Couverture actuelle" class="current-preview-image">
                                <!-- Chemin relatif depuis admin/ vers la racine du site (..) -->
                            <?php else: ?>
                                <p class="current-file-info text-red-400"><i class="fas fa-exclamation-triangle"></i>Aucune couverture actuelle.</p>
                            <?php endif; ?>
                            <div id="cover-preview" class="file-preview mt-2" style="display: none;"></div> <!-- Zone pour nouvelle prévisu -->
                             <p class="text-xs text-text-secondary mt-1">Laisser vide pour conserver l'image actuelle.</p>
                        </div>

                        <div class="form-group">
                            <label for="preview">Remplacer la vidéo Preview :</label>
                            <input type="file" id="preview" name="preview" accept="video/mp4,video/webm">
                             <?php if (!empty($game['preview'])): ?>
                                <p class="current-file-info"><i class="fas fa-film"></i>Preview actuelle : <?= htmlspecialchars(basename($game['preview'])) ?></p>
                                <!-- Optionnel : afficher la vidéo actuelle -->
                                <!-- <video src="..<?= htmlspecialchars($game['preview']) ?>" controls class="current-preview-image" style="max-height:150px;"></video> -->
                             <?php else: ?>
                                <p class="current-file-info"><i class="fas fa-video-slash"></i>Aucune preview actuelle.</p>
                            <?php endif; ?>
                            <div id="video-preview" class="file-preview mt-2" style="display: none;"></div> <!-- Zone pour nouvelle prévisu -->
                            <p class="text-xs text-text-secondary mt-1">Laisser vide pour conserver la preview actuelle.</p>
                        </div>

                    </div> <!-- Fin Grid -->

                    <div class="form-actions">
                        <a href="index.php" class="form-button cancel-button">
                             <i class="fas fa-times mr-2"></i>Annuler
                        </a>
                        <button type="submit" class="form-button submit-button">
                            <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
             <p class="text-center text-accent">Le jeu demandé n'a pas pu être chargé.</p>
        <?php endif; ?>

    </div>

    <script>
         // --- Même fonction de prévisualisation que add_game.php ---
         function setupFilePreview(inputId, previewContainerId, currentPreviewSelector = null) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);

            if (input && previewContainer) {
                input.addEventListener('change', function(event) {
                    previewContainer.innerHTML = ''; // Vide la prévisualisation précédente
                    previewContainer.style.display = 'none'; // Cache par défaut

                     // Cache aussi la prévisualisation actuelle si elle existe
                     if(currentPreviewSelector) {
                         const currentPreview = input.closest('.form-group').querySelector(currentPreviewSelector);
                         if (currentPreview) {
                             // Plutôt que de le supprimer, on peut juste le cacher
                             currentPreview.style.display = 'none';
                         }
                          // Cacher aussi l'info "Fichier actuel"
                         const currentFileInfo = input.closest('.form-group').querySelector('.current-file-info');
                          if(currentFileInfo) currentFileInfo.style.display = 'none';
                     }


                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            let previewElement;
                            if (file.type.startsWith('image/')) {
                                previewElement = document.createElement('img');
                                previewElement.alt = 'Nouvelle prévisualisation';
                            } else if (file.type.startsWith('video/')) {
                                previewElement = document.createElement('video');
                                previewElement.controls = true;
                                previewElement.muted = true;
                            }

                            if (previewElement) {
                                previewElement.src = e.target.result;
                                previewContainer.appendChild(previewElement);
                                previewContainer.style.display = 'block';
                            }
                        }
                        reader.readAsDataURL(file);
                    } else {
                         // Si l'utilisateur déselectionne un fichier, réafficher la prévisu actuelle
                         if(currentPreviewSelector) {
                            const currentPreview = input.closest('.form-group').querySelector(currentPreviewSelector);
                            if (currentPreview) currentPreview.style.display = 'block'; // Ou 'inline-block' etc.
                             // Réafficher aussi l'info "Fichier actuel"
                            const currentFileInfo = input.closest('.form-group').querySelector('.current-file-info');
                            if(currentFileInfo) currentFileInfo.style.display = 'inline-block';
                         }
                    }
                });
            } else {
                 console.warn(`Élément input #${inputId} ou preview #${previewContainerId} non trouvé.`);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
             // Appeler setupFilePreview en indiquant le sélecteur de l'image/vidéo actuelle
            setupFilePreview('cover', 'cover-preview', '.current-preview-image');
            setupFilePreview('preview', 'video-preview'); // Pas de prévisu directe pour la vidéo actuelle par défaut
        });
    </script>
</body>
</html>