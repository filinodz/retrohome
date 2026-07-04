<?php
// templates/profile.php (Modern Version)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil - Station Retro</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS -->
    <!-- Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <!-- CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    
    <style>
        .profile-card {
            padding: 30px;
            margin-bottom: 30px;
        }
        .fav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
        }
        .collection-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            padding: 15px;
            border-radius: 15px;
            text-align: left;
            transition: all 0.2s;
        }
        .collection-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            border-color: var(--secondary);
        }
    </style>
</head>
<body class="bg-dark">
    <div class="app-container">
        <header class="glass nav-bar animate__animated animate__fadeInDown">
            <div class="flex items-center gap-4">
                <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="logo-main">
                <div>
                    <h1 class="pixel-text" style="margin: 0; color: var(--primary); font-size: 1.2rem;">PROFIL_UTILISATEUR</h1>
                    <span class="pixel-text" style="font-size: 0.6rem; color: var(--text-muted);">USER_ID: <?= $userId ?></span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a href="/" class="pixel-text" style="font-size: 0.7rem; color: white; text-decoration: none; padding: 10px 20px; border-radius: 50px; background: rgba(255,255,255,0.05);">RETOUR_ACCUEIL</a>
                <button id="logout-btn" class="pixel-text" style="font-size: 0.7rem; color: var(--primary); border: none; background: none; cursor: pointer;">DECONNEXION</button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-10">
            <!-- Info Section -->
            <div class="lg:col-span-1">
                <div class="glass profile-card animate__animated animate__fadeInLeft">
                    <h2 class="pixel-text mb-6" style="color: var(--primary); font-size: 0.9rem;">IDENTITÉ</h2>
                    <div class="flex flex-col gap-4">
                        <div class="opacity-60 text-sm">NOM_D_UTILISATEUR</div>
                        <div class="pixel-text" style="font-size: 0.8rem;"><?= htmlspecialchars($username) ?></div>
                        
                        <div style="height: 1px; background: var(--glass-border); margin: 10px 0;"></div>
                        
                        <div class="opacity-60 text-sm">ADRESSE_EMAIL</div>
                        <div id="profile-email" style="font-weight: 600;">Chargement...</div>

                        <div style="height: 1px; background: var(--glass-border); margin: 10px 0;"></div>

                        <div class="opacity-60 text-sm">STATISTIQUES</div>
                        <div class="flex flex-col gap-2">
                            <span class="pixel-text" style="font-size: 0.5rem;"><i class="fas fa-heart mr-2 text-primary"></i> <span id="profile-favorite-count">0</span> FAVORIS</span>
                            <span class="pixel-text" style="font-size: 0.5rem;"><i class="fas fa-star mr-2 text-secondary"></i> <span id="profile-rating-count">0</span> NOTES</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="lg:col-span-2">
                <div class="glass profile-card animate__animated animate__fadeInUp">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="pixel-text" style="color: var(--secondary); font-size: 0.9rem;">MES_COLLECTIONS</h2>
                        <button id="create-collection-btn" class="action-link edit" style="width: auto; padding: 0 15px; border-radius: 50px; font-size: 0.6rem;">
                            + NOUVELLE
                        </button>
                    </div>
                    <div id="collections-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="loading-placeholder opacity-30 italic">Initialisation du système...</div>
                    </div>
                </div>

                <div class="glass profile-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                    <h2 class="pixel-text mb-6" style="color: var(--primary); font-size: 0.9rem;">BANQUE_FAVORIS</h2>
                    <div id="profile-favorites" class="fav-grid">
                        <div class="loading-placeholder opacity-30 italic">Scanning drive...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>

    <!-- Modal Création Collection -->
     <div id="collection-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center p-4" style="z-index: 100;">
         <div class="modal-content w-full max-w-md animate__animated animate__zoomIn animate__faster">
             <h3>Créer une nouvelle collection</h3>
             <div class="form-group mb-4">
                 <input type="text" id="collection-name" placeholder="Nom de la collection *" required class="modal-input w-full p-2 rounded">
            </div>
             <div class="form-group mb-4">
                 <textarea id="collection-description" placeholder="Description (facultatif)" class="modal-input w-full p-2 rounded"></textarea>
             </div>
             <div class="form-actions flex justify-end gap-2">
               <button id="cancel-collection-btn" type="button" class="form-button cancel-button px-4 py-2 border rounded">Annuler</button>
               <button id="save-collection-btn" type="button" class="form-button submit-button px-4 py-2 bg-blue-600 text-white rounded">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
             </div>
         </div>
     </div>

    <script src="<?= SITE_URL ?>/public/js/profile.js"></script>
    <script>
        // Inline scripts for logout, modal animations, etc.
        // (Similar to what's in the original but cleaned for template usage)
    </script>
</body>
</html>
