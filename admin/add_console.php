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
            header('Location: index.php');
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
<head>
    <meta charset="UTF-8">
    <title>Ajouter une console - Administration</title>
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
    <link rel="stylesheet" href="admin_style.css"> <!-- Styles admin -->
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <header class="admin-header-internal mb-8">
             <a href="index.php" class="back-link-header" title="Retour à la liste">
                <i class="fas fa-arrow-left mr-2"></i>
                 <h1 class="form-title inline">Ajouter une Nouvelle Console</h1>
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
            <form action="add_console.php" method="post" enctype="multipart/form-data" novalidate>
                <!-- Grid Layout -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                    <div class="form-group md:col-span-2">
                        <label for="name">Nom de la console :<span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                        <p class="text-xs text-text-secondary mt-1">Le 'slug' (identifiant unique pour les URLs et dossiers) sera généré automatiquement.</p>
                    </div>

                    <div class="form-group">
                        <label for="logo">Fichier Logo<span class="text-red-500">*</span> :</label>
                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required>
                         <p class="text-xs text-text-secondary mt-1">Formats: JPG, PNG, GIF, WEBP, SVG.</p>
                        <div id="logo-preview" class="file-preview mt-2" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label for="sort_order">Ordre de tri (Optionnel) :</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($sort_order)?>" step="1" placeholder="0">
                         <p class="text-xs text-text-secondary mt-1">Nombre entier (ex: 10, 20...). Les consoles seront triées par ordre croissant.</p>
                    </div>

                </div> <!-- Fin Grid -->

                <div class="form-actions">
                    <a href="index.php" class="form-button cancel-button">
                         <i class="fas fa-times mr-2"></i>Annuler
                    </a>
                    <button type="submit" class="form-button submit-button">
                        <i class="fas fa-save mr-2"></i>Ajouter la console
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