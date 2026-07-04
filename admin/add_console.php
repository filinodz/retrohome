<?php
require_once '../config.php'; // Chemin relatif

// --- Initialisation Variables ---
$name = '';
$slug = ''; // Sera généré
$sort_order = 0;
$errors = [];

// --- Vérification des droits d'accès Admin ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
try {
    $stmtUser = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        // Rediriger ou afficher une erreur plus propre
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
} catch (PDOException $e) {
     // Gérer l'erreur de base de données
     error_log("Admin Add Console - User check error: " . $e->getMessage());
     // Afficher une page d'erreur générique ou rediriger
     die("Erreur lors de la vérification des permissions."); // Simple die pour l'exemple
}


// --- Traitement du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    // Slug plus robuste
    $slug = strtolower(trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^A-Za-z0-9-\s]+/', '', $name)), '-'));
    $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $logo_path_relative = ''; // Chemin relatif pour la BDD

    // Validation Nom
    if (empty($name)) {
        $errors[] = "Le nom de la console est obligatoire.";
    } elseif (empty($slug)) {
        // Si le nom ne contenait que des caractères spéciaux
        $errors[] = "Le nom de la console doit contenir des caractères alphanumériques.";
        $slug = 'console-' . time(); // Fallback slug pour vérification BDD
    }

    // Vérification unicité Slug (seulement si le nom/slug est valide)
    if (empty($errors)) {
        try {
            $stmtCheckSlug = $db->prepare('SELECT id FROM consoles WHERE slug = ?');
            $stmtCheckSlug->execute([$slug]);
            if($stmtCheckSlug->fetch()){
                $errors[] = "Une console avec un nom similaire ('" . htmlspecialchars($slug) . "') existe déjà. Veuillez choisir un nom légèrement différent.";
            }
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de la vérification du nom de la console.";
             error_log("Admin Add Console - Slug check error: " . $e->getMessage());
        }
    }

     // Validation Ordre de tri
     if($sort_order === false) { // Vérifier si filter_input a échoué
        $errors[] = "L'ordre de tri doit être un nombre entier.";
     }

    // --- Gestion du logo (seulement si pas d'erreurs précédentes) ---
    if (empty($errors)) {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_original_name = $_FILES['logo']['name'];
            $logo_ext = strtolower(pathinfo($logo_original_name, PATHINFO_EXTENSION));
            $allowed_logo_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']; // Ajouter SVG?

            if (!in_array($logo_ext, $allowed_logo_ext)) {
                $errors[] = "Le format du logo n'est pas valide (JPG, PNG, GIF, WEBP, SVG).";
            } else {
                $logo_dir_absolute = rtrim(LOGOS_PATH, '/') . '/'; // Utiliser LOGOS_PATH défini dans config.php
                $logo_filename = $slug . '.' . $logo_ext; // Nom basé sur le slug
                $logo_destination_absolute = $logo_dir_absolute . $logo_filename;

                // Créer le répertoire si nécessaire
                if (!is_dir($logo_dir_absolute)) {
                    if (!mkdir($logo_dir_absolute, 0775, true)) {
                        $errors[] = "Impossible de créer le répertoire des logos : " . $logo_dir_absolute;
                    }
                } elseif (!is_writable($logo_dir_absolute)) {
                     $errors[] = "Le répertoire des logos n'est pas accessible en écriture.";
                }

                // Déplacer le fichier si pas d'erreur de répertoire
                if (empty($errors) && !move_uploaded_file($_FILES['logo']['tmp_name'], $logo_destination_absolute)) {
                    $errors[] = "Erreur technique lors du téléchargement du logo.";
                } elseif (empty($errors)) {
                    // Chemin relatif depuis la racine WEB pour la BDD
                    // Suppose que LOGOS_PATH est en dehors de la racine web mais qu'il y a un alias
                    // ou que le dossier assets/logos est utilisé pour l'affichage.
                    // **IMPORTANT**: Adaptez ce chemin selon votre structure.
                    // Si LOGOS_PATH est '/var/www/html/assets/logos/', le chemin relatif est '/assets/logos/'
                    // Si LOGOS_PATH est ailleurs, il faudra peut-être un script pour servir l'image ou copier dans public.
                    // Pour cet exemple, on suppose un dossier 'assets/logos/' accessible depuis la racine web.
                     $logo_path_relative = '/assets/logos/' . $logo_filename; // **ADAPTER CE CHEMIN**
                }
            }
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Erreur lors de l'upload du logo (code: " . $_FILES['logo']['error'] . ").";
        } else {
             $errors[] = "Le fichier logo est obligatoire.";
        }
    }


    // --- Insertion en base de données (si aucune erreur) ---
    if (empty($errors)) {
        try {
            $stmtInsert = $db->prepare("INSERT INTO consoles (name, slug, logo, sort_order) VALUES (:name, :slug, :logo, :sort_order)");
            $stmtInsert->execute([
                ':name' => $name,
                ':slug' => $slug,
                ':logo' => $logo_path_relative, // Utilise le chemin relatif web
                ':sort_order' => $sort_order
            ]);

             $_SESSION['admin_flash_message'] = [
                 'type' => 'success',
                 'message' => 'Console "' . htmlspecialchars($name) . '" ajoutée avec succès !'
             ];
            header('Location: ./');
            exit();
        } catch (PDOException $e) {
             $errors[] = "Erreur lors de l'insertion dans la base de données.";
             error_log("Admin Add Console - DB Insert error: " . $e->getMessage());
              // Supprimer le logo uploadé si l'insertion échoue
             if (!empty($logo_destination_absolute) && file_exists($logo_destination_absolute)) {
                 @unlink($logo_destination_absolute);
             }
        }
    }
} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

?>
<!DOCTYPE html>
<html lang="fr">
    <link rel="stylesheet" href="public/css/admin_style.css">
    <style>
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .form-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 15px 20px;
            color: white;
            font-family: inherit;
        }
    </style>
</head>
<body class="bg-background text-text-primary">

    <div class="app-container">
        <!-- Premium Header -->
        <header class="glass nav-bar animate-fade-in" style="margin-bottom: 40px;">
            <div class="flex items-center gap-4">
                <div style="background: rgba(112, 0, 255, 0.1); padding: 12px; border-radius: 16px; box-shadow: 0 0 15px var(--secondary-glow);">
                    <i class="fas fa-tv text-xl text-secondary"></i>
                </div>
                <div>
                    <h1 class="pixel-text" style="margin: 0; font-size: 1.3rem;"><?= __('admin_add_console_title') ?></h1>
                    <span style="font-size: 0.6rem; color: var(--text-secondary); opacity: 0.6; letter-spacing: 2px; font-weight: 700;">SYSTEM_PROVISION_v1</span>
                </div>
            </div>
            <a href="index.php" class="btn-modern btn-secondary" style="font-size: 0.75rem;">
                <i class="fas fa-chevron-left mr-2"></i><?= __('back_caps') ?>
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

        <div class="glass p-8 animate-fade-in" style="animation-delay: 0.2s;">
            <form action="add_console" method="post" enctype="multipart/form-data" novalidate>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                    <div class="form-group md:col-span-2">
                        <label for="name"><?= __('admin_name') ?> *</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Nintendo 64" required>
                    </div>

                    <div class="form-group">
                        <label for="logo"><?= __('admin_cover_label') ?> *</label>
                        <input type="file" id="logo" name="logo" accept="image/*" required>
                         <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; opacity: 0.6;"><?= __('admin_formats_supported') ?>: JPG, PNG, WEBP, SVG.</p>
                        <div id="logo-preview" class="file-preview mt-4" style="display: none; border: 1px dashed var(--glass-border); padding: 10px; border-radius: 16px;"></div>
                    </div>

                    <div class="form-group">
                        <label for="sort_order"><?= __('admin_sort_label') ?> (<?= __('admin_optional') ?>)</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($sort_order)?>" step="1" placeholder="0">
                    </div>

                </div>

                <div class="flex justify-end gap-4 mt-12 pt-8" style="border-top: 1px solid var(--glass-border);">
                    <a href="index.php" class="btn-modern btn-secondary">
                         <i class="fas fa-times"></i> <?= __('admin_cancel') ?>
                    </a>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="fas fa-save"></i> <?= __('admin_add_console_title') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Même fonction de prévisualisation que add_game.php ---
        function setupFilePreview(inputId, previewContainerId) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);

            if (input && previewContainer) {
                input.addEventListener('change', function(event) {
                    previewContainer.innerHTML = '';
                    previewContainer.style.display = 'none';

                    const file = event.target.files[0];
                    if (file && file.type.startsWith('image/')) { // Seulement pour images ici
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewElement = document.createElement('img');
                            previewElement.alt = 'Prévisualisation du logo';
                            previewElement.src = e.target.result;
                            previewContainer.appendChild(previewElement);
                            previewContainer.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                 console.warn(`Élément input #${inputId} ou preview #${previewContainerId} non trouvé.`);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupFilePreview('logo', 'logo-preview');
        });
    </script>
</body>
</html>
