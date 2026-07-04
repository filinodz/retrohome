<?php
// themes/modern/collection.php (Restored Original Design)
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Dépendances CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <!-- CSS (Principal + Styles Profil/Admin) -->
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/css/collection.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/css/footer.css">
</head>
<body class="bg-background text-text-primary font-body theme-modern">
  <div class="collection-container"> <!-- Conteneur spécifique -->
    <header class="collection-header animate__animated animate__fadeInDown">
      <div class="title-description">
        <h1 id="collection-title" class="page-title"><?= __('loading') ?></h1>
        <p id="collection-description" class="text-secondary"></p>
      </div>
        <a href="<?= SITE_URL ?>/profile" class="back-profile-link" title="Retour au profil">
            <i class="fas fa-arrow-left"></i> <?= __('back_profile') ?>
        </a>
    </header>

    <div class="collection-filters my-6 flex flex-col sm:flex-row gap-4 justify-center items-center animate__animated animate__fadeInUp animate__delay-100ms">
        <div class="search-box relative flex-grow w-full sm:w-auto sm:flex-grow-0">
             <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-text-secondary pointer-events-none"><i class="fas fa-search"></i></span>
            <input type="text" id="game-search" placeholder="   <?= __('filter_games') ?>" class="w-full">
        </div>
        <div class="console-filter-wrapper relative flex-grow w-full sm:w-auto sm:flex-grow-0">
            <label for="console-filter" class="sr-only"><?= __('filter_by_console') ?></label>
            <select id="console-filter" class="w-full">
              <option value=""><?= __('all_consoles') ?></option>
            </select>
        </div>
    </div>

    <div id="collection-games-container" class="animate__animated animate__fadeInUp animate__delay-200ms">
         <div class="loading-placeholder">
              <i class="fas fa-spinner fa-spin"></i><?= __('loading_games_placeholder') ?>
         </div>
    </div>

    <div class="add-games-button-container animate__animated animate__fadeInUp animate__delay-300ms">
        <button id="add-games-btn">
         <i class = "fas fa-plus-circle mr-2"></i> <?= __('add_manage_games') ?>
        </button>
    </div>

  </div> <!-- Fin .collection-container -->

    <div id="game-modal" class="fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center p-4 z-50">
        <div class="modal-content w-full max-w-3xl animate__animated animate__faster">
            <h3><?= __('manage_collection_games') ?></h3>
            <div class="modal-search-bar">
                 <label for="modal-game-search" class="sr-only"><?= __('search_placeholder') ?></label>
                 <input type="text" id="modal-game-search" placeholder="<?= __('search_placeholder') ?>">
            </div>
            <div class="modal-scroll-container">
               <div id="modal-games-list">
                    <div class="loading-placeholder py-8"> 
                        <i class="fas fa-spinner fa-spin"></i><?= __('loading_games_placeholder') ?>
                    </div>
               </div>
            </div>
            <div class="modal-actions">
              <button id="close-modal-btn" type="button" class="btn-neon"><?= __('close_btn') ?></button>
            </div>
          </div>
      </div>
 
    <?php include __DIR__ . '/footer.php'; ?>
 
  <script>const SITE_URL = "<?= SITE_URL ?>";</script>
  <script>
      const collectionId = <?= json_encode($collectionId) ?>;
      const collectionName = <?= json_encode($collectionInfo['name'] ?? 'Collection') ?>;
  </script>
  <script src="<?= SITE_URL ?>/public/js/collection.js"></script>
</body>
</html>
