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

            header('Location: index.php'); // Redirige vers la liste
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
<head>
    <meta charset="UTF-8">
    <title>Ajouter un jeu - Administration</title>
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
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Header simplifié pour les pages internes -->
        <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Ajouter un Nouveau Jeu</h1>
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

        <div class="admin-form-container animate__animated animate__fadeInUp">
            <form action="add_game.php" method="post" enctype="multipart/form-data" novalidate>
                <!-- Utilisation de Grid pour layout flexible -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                    <div class="form-group md:col-span-2">
                        <label for="title">Titre du jeu :</label>
                        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="console_id">Console :</label>
                        <select id="console_id" name="console_id" required>
                            <option value="" disabled <?= empty($console_id) ? 'selected' : '' ?>>-- Choisir une console --</option>
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
                        <label for="rom">Fichier ROM<span class="text-red-500">*</span> :</label>
                        <input type="file" id="rom" name="rom" accept=".zip,.sfc,.smc,.fig,.bin,.gba,.gbc,.gb,.nes,.pce,.md,.mgd,.sms,.gg,.col,.ngp,.ngc,.ws,.wsc,.7z,.iso,.cue" required>
                         <p class="text-xs text-text-secondary mt-1">Formats supportés: zip, sfc, smc, fig, bin, gba, gbc, gb, nes, pce, md, mgd, sms, gg, col, ngp, ngc, ws, wsc, 7z, iso, cue.</p>
                    </div>

                    <div class="form-group">
                        <label for="cover">Image de couverture<span class="text-red-500">*</span> :</label>
                        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/gif,image/webp" required>
                         <p class="text-xs text-text-secondary mt-1">Formats: JPG, PNG, GIF, WEBP.</p>
                        <div id="cover-preview" class="file-preview mt-2" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label for="preview">Vidéo Preview (Optionnel) :</label>
                        <input type="file" id="preview" name="preview" accept="video/mp4,video/webm">
                        <p class="text-xs text-text-secondary mt-1">Format: MP4 ou WEBM.</p>
                        <div id="video-preview" class="file-preview mt-2" style="display: none;"></div>
                    </div>

                 </div> <!-- Fin Grid -->

                <div class="form-actions">
                    <a href="index.php" class="form-button cancel-button">
                         <i class="fas fa-times mr-2"></i>Annuler
                    </a>
                    <button type="submit" class="form-button submit-button">
                        <i class="fas fa-save mr-2"></i>Ajouter le jeu
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