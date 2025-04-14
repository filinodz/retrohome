<?php
require_once '../config.php'; // Chemin relatif

// --- Initialisation et Sécurité ---
$errors = [];
$console_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$console = null;
$name = ''; // Initialise les variables pour le formulaire
$slug = '';
$logo_path = '';
$sort_order = 0;

// Vérification des droits d'accès admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
try {
    $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
} catch (PDOException $e) {
     error_log("Admin Edit Console - User check error: " . $e->getMessage());
     die("Erreur lors de la vérification des permissions.");
}

// --- Récupération des données initiales ---
if (!$console_id) {
    $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => 'ID de console manquant ou invalide.'];
    header('Location: index.php');
    exit();
}

try {
    // Récupère les infos de la console à modifier
    $stmtConsole = $db->prepare("SELECT * FROM consoles WHERE id = ?");
    $stmtConsole->execute([$console_id]);
    $console = $stmtConsole->fetch(PDO::FETCH_ASSOC);

    if (!$console) {
        $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => 'Console non trouvée.'];
        header('Location: index.php');
        exit();
    }

    // Pré-remplir les variables pour le formulaire
    $name = $console['name'];
    $slug = $console['slug']; // Slug actuel (sera recalculé si le nom change)
    $logo_path = $console['logo']; // Logo actuel
    $sort_order = $console['sort_order'];

} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des données de la console.";
    error_log("Admin Edit Console - DB Fetch error: " . $e->getMessage());
    // On continue pour afficher l'erreur dans le formulaire si $console est null
}


// --- Traitement du formulaire (si soumis) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $console) { // Vérifie que $console n'est pas null
    // Récupération et nettoyage des données postées
    $name = trim($_POST['name'] ?? '');
    // Recalculer le slug basé sur le nouveau nom potentiel
    $new_slug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $name)), '-'));
    $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT, ['options' => ['default' => $console['sort_order']]]); // Garde l'ancien si invalide
    $logo_path = $console['logo']; // Garde l'ancien logo par défaut
    $new_logo_uploaded = false; // Flag pour savoir si un nouveau logo a été uploadé

    // --- Validation ---
    if (empty($name)) {
        $errors[] = "Le nom de la console est obligatoire.";
    } elseif (empty($new_slug)) {
         $errors[] = "Le nom de la console doit contenir des caractères alphanumériques.";
         $new_slug = $console['slug']; // Garder l'ancien slug en cas d'erreur
    }

     // Vérification unicité Slug (si le slug a changé)
     if (empty($errors) && $new_slug !== $console['slug']) {
         try {
            $stmtCheckSlug = $db->prepare("SELECT id FROM consoles WHERE slug = ? AND id != ?");
            $stmtCheckSlug->execute([$new_slug, $console_id]);
            if($stmtCheckSlug->fetch()){
                $errors[] = "Une console avec un nom similaire ('" . htmlspecialchars($new_slug) . "') existe déjà.";
            }
         } catch (PDOException $e) {
            $errors[] = "Erreur lors de la vérification du nouveau nom.";
            error_log("Admin Edit Console - Slug check error: " . $e->getMessage());
         }
     }

     // Validation Ordre de tri
     if($sort_order === false) {
        $errors[] = "L'ordre de tri doit être un nombre entier.";
        $sort_order = $console['sort_order']; // Revenir à l'ancienne valeur
     }


    // --- Gestion du nouveau logo (si fourni et pas d'erreurs) ---
   if (empty($errors) && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_original_name = $_FILES['logo']['name'];
        $logo_ext = strtolower(pathinfo($logo_original_name, PATHINFO_EXTENSION));
        $allowed_logo_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        if (!in_array($logo_ext, $allowed_logo_ext)) {
            $errors[] = "Le format du logo n'est pas valide (JPG, PNG, GIF, WEBP, SVG).";
        } else {
            $logo_dir_absolute = rtrim(LOGOS_PATH, '/') . '/';
            $logo_filename = $new_slug . '.' . $logo_ext; // Nom basé sur le NOUVEAU slug
            $logo_destination_absolute = $logo_dir_absolute . $logo_filename;

            // Créer le répertoire si nécessaire
            if (!is_dir($logo_dir_absolute)) {
                if (!mkdir($logo_dir_absolute, 0775, true)) {
                    $errors[] = "Impossible de créer le répertoire des logos.";
                }
            } elseif (!is_writable($logo_dir_absolute)) {
                $errors[] = "Le répertoire des logos n'est pas accessible en écriture.";
            }

            // Déplacer le fichier et supprimer l'ancien si différent
            if (empty($errors)) {
                 // Supprimer l'ancien logo seulement si le nouveau fichier va être sauvegardé
                 // et si l'ancien logo existe.
                 $old_logo_absolute_path = !empty($console['logo']) ? rtrim(ROMS_PATH, '/') . '/' . ltrim($console['logo'], '/') : null; // Attention : LOGOS_PATH ici?

                 // Recalculer le chemin relatif web potentiel du nouveau logo
                 // **ADAPTER CE CHEMIN**
                 $potential_new_logo_relative = '/assets/logos/' . $logo_filename;

                 if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_destination_absolute)) {
                     $errors[] = "Erreur technique lors du téléchargement du nouveau logo.";
                 } else {
                     $new_logo_uploaded = true;
                     $logo_path = $potential_new_logo_relative; // Met à jour avec le nouveau chemin relatif web

                     // Supprimer l'ancien seulement si le déplacement a réussi
                     // et si l'ancien chemin est différent du nouveau (important si seule l'extension change)
                     if ($old_logo_absolute_path && file_exists($old_logo_absolute_path) && $logo_path !== $console['logo'] ) {
                         @unlink($old_logo_absolute_path);
                     }
                 }
            }
        }
   } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Erreur lors de l'upload du logo (code: " . $_FILES['logo']['error'] . ").";
   }

   // --- Mise à jour BDD (si aucune erreur) ---
    if (empty($errors)) {
        try {
            // Si le slug a changé mais qu'aucun nouveau logo n'a été uploadé,
            // on doit peut-être renommer l'ancien fichier logo sur le disque.
            $final_logo_path = $logo_path; // Commence avec le chemin actuel ou celui du nouveau logo

            if ($new_slug !== $console['slug'] && !$new_logo_uploaded && !empty($console['logo'])) {
                 $old_logo_absolute_path = rtrim(LOGOS_PATH, '/') . '/' . basename($console['logo']); // Chemin disque basé sur LOGOS_PATH
                 $logo_ext_old = strtolower(pathinfo($console['logo'], PATHINFO_EXTENSION));
                 $new_logo_filename_disk = $new_slug . '.' . $logo_ext_old;
                 $new_logo_absolute_path_disk = rtrim(LOGOS_PATH, '/') . '/' . $new_logo_filename_disk;

                if (file_exists($old_logo_absolute_path) && $old_logo_absolute_path !== $new_logo_absolute_path_disk) {
                     if (@rename($old_logo_absolute_path, $new_logo_absolute_path_disk)) {
                         // Met à jour le chemin relatif pour la BDD
                         // **ADAPTER CE CHEMIN**
                         $final_logo_path = '/assets/logos/' . $new_logo_filename_disk;
                     } else {
                        // Ne pas bloquer la mise à jour pour ça, mais logguer une erreur
                         error_log("Admin Edit Console - Failed to rename logo file: {$old_logo_absolute_path} to {$new_logo_absolute_path_disk}");
                         // Garder l'ancien chemin dans la BDD si le renommage échoue ?
                         // $final_logo_path = $console['logo']; // Ou laisser $logo_path qui est l'ancien
                     }
                 }
            }

            // Requête de mise à jour
            $stmtUpdate = $db->prepare("UPDATE consoles SET name = :name, slug = :slug, logo = :logo, sort_order = :sort_order WHERE id = :id");
            $stmtUpdate->execute([
                ':name' => $name,
                ':slug' => $new_slug, // Utilise le nouveau slug
                ':logo' => $final_logo_path, // Utilise le chemin final (potentiellement renommé)
                ':sort_order' => $sort_order,
                ':id' => $console_id
            ]);

             $_SESSION['admin_flash_message'] = [
                 'type' => 'success',
                 'message' => 'Console "' . htmlspecialchars($name) . '" modifiée avec succès !'
             ];
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de la mise à jour dans la base de données.";
             error_log("Admin Edit Console - DB Update error: " . $e->getMessage());
              // Si un nouveau logo a été uploadé mais la BDD échoue, le supprimer ?
             // if ($new_logo_uploaded && !empty($logo_destination_absolute) && file_exists($logo_destination_absolute)) {
             //    @unlink($logo_destination_absolute);
             // }
        }
    }
    // Si erreurs, le formulaire est réaffiché avec les nouvelles valeurs $name, $sort_order, etc.
} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier : <?= htmlspecialchars($console['name'] ?? 'Console') ?> - Administration</title>
    <link rel="icon" type="image/png" href="../assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS -->
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

         <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Modifier : <?= htmlspecialchars($console['name'] ?? 'Console') ?></h1>
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

        <?php if ($console): // Affiche le formulaire seulement si $console a été chargée ?>
            <div class="admin-form-container animate__animated animate__fadeInUp">
                <form action="edit_console.php?id=<?= $console_id ?>" method="post" enctype="multipart/form-data" novalidate>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                        <div class="form-group md:col-span-2">
                            <label for="name">Nom de la console :<span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                             <p class="text-xs text-text-secondary mt-1">Modifier le nom changera aussi le 'slug' utilisé pour les URLs et les dossiers.</p>
                        </div>

                        <div class="form-group">
                            <label for="logo">Remplacer le logo :</label>
                            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                             <?php if (!empty($console['logo'])): ?>
                                 <p class="current-file-info"><i class="fas fa-image"></i>Logo actuel :</p>
                                 <img src="..<?= htmlspecialchars($console['logo']) ?>?t=<?= time() ?>" alt="Logo actuel de <?= htmlspecialchars($console['name']) ?>" class="current-preview-image">
                                 <!-- Ajout ?t=time() pour forcer le rafraichissement si l'image est remplacée par une autre du même nom -->
                            <?php else: ?>
                                <p class="current-file-info text-orange-400"><i class="fas fa-image"></i>Aucun logo actuel.</p>
                            <?php endif; ?>
                             <div id="logo-preview" class="file-preview mt-2" style="display: none;"></div>
                             <p class="text-xs text-text-secondary mt-1">Laisser vide pour conserver le logo actuel.</p>
                        </div>

                        <div class="form-group">
                            <label for="sort_order">Ordre de tri :</label>
                            <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($sort_order)?>" step="1" placeholder="0">
                            <p class="text-xs text-text-secondary mt-1">Nombre entier. Tri croissant.</p>
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
             <p class="text-center text-accent">La console demandée n'a pas pu être chargée.</p>
              <div class="text-center mt-6">
                  <a href="index.php" class="form-button cancel-button">
                    <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
                 </a>
             </div>
        <?php endif; ?>

    </div>

    <script>
        // --- Même fonction de prévisualisation que add_console.php ---
         function setupFilePreview(inputId, previewContainerId, currentPreviewSelector = null) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);

            if (input && previewContainer) {
                input.addEventListener('change', function(event) {
                    previewContainer.innerHTML = '';
                    previewContainer.style.display = 'none';

                    if(currentPreviewSelector) {
                        const currentPreview = input.closest('.form-group').querySelector(currentPreviewSelector);
                        if (currentPreview) currentPreview.style.display = 'none';
                         const currentFileInfo = input.closest('.form-group').querySelector('.current-file-info');
                          if(currentFileInfo) currentFileInfo.style.display = 'none';
                    }

                    const file = event.target.files[0];
                    if (file && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewElement = document.createElement('img');
                            previewElement.alt = 'Nouvelle prévisualisation';
                            previewElement.src = e.target.result;
                            previewContainer.appendChild(previewElement);
                            previewContainer.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    } else {
                        if(currentPreviewSelector) {
                            const currentPreview = input.closest('.form-group').querySelector(currentPreviewSelector);
                            if (currentPreview) currentPreview.style.display = 'block'; // Ou 'inline-block'
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
            setupFilePreview('logo', 'logo-preview', '.current-preview-image');
        });
    </script>
</body>
</html>