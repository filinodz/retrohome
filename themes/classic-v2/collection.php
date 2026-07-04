<?php
// themes/classic/collection.php (Restored Original Design)
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <?php if ($isRTL): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif !important; }</style>
    <?php endif; ?>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <!-- CSS -->
    <link href="<?= SITE_URL ?>/public/vendor/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/animatecss/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    <link rel="stylesheet" href="<?= get_theme_asset('css/collection.css') ?>">
</head>
<body class="bg-background theme-classic-v2">
  <div class="absolute top-4 right-4 z-50">
       <?php $langSelectorDirection = 'down'; include BASE_PATH . '/includes/LanguageSelector.php'; ?>
  </div>
  <div class="collection-container p-8">
    <header class="collection-header classic-header p-6 rounded-2xl mb-8">
      <div>
        <h1 id="collection-title" class="text-3xl font-bold text-primary"><?= __('loading') ?></h1>
        <p id="collection-description" class="text-secondary text-sm"></p>
      </div>
      <a href="<?= SITE_URL ?>/profile" class="text-secondary hover:text-primary"><i class="fas fa-arrow-left mr-2"></i><?= __('back_profile') ?></a>
    </header>

    <div class="collection-filters flex flex-col md:flex-row items-center gap-4 mb-8">
        <div class="search-box-modern flex-1 relative w-full">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary opacity-50"></i>
            <input type="text" id="game-search" class="w-full bg-black/30 border border-white/10 rounded-xl py-3 pl-12 pr-4 text-sm focus:border-primary outline-none transition-all" placeholder="<?= __('filter_games') ?>">
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
            <div class="console-filter-wrapper flex-1 md:flex-initial">
                <select id="console-filter" class="w-full bg-black/30 border border-white/10 rounded-xl py-3 px-6 text-sm focus:border-primary outline-none appearance-none transition-all cursor-pointer">
                    <option value=""><?= __('all_consoles') ?></option>
                </select>
            </div>
            <div class="bg-black/30 border border-white/10 p-1 rounded-xl flex">
                <button id="view-grid" class="p-2 w-10 h-10 rounded-lg transition-all hover:text-primary active"><i class="fas fa-th-large"></i></button>
                <button id="view-list" class="p-2 w-10 h-10 rounded-lg transition-all hover:text-primary"><i class="fas fa-list"></i></button>
            </div>
        </div>
    </div>

    <div id="collection-games-container" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
         <!-- Injected by JS -->
    </div>

    <div class="mt-12 text-center">
        <button id="add-games-btn" class="bg-primary text-black px-6 py-3 rounded font-bold">
         <?= __('add_manage_games') ?>
        </button>
    </div>

    <!-- Modal remains functional via shared JS -->
    <div id="game-modal" class="fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center p-4 z-50">
        <div class="modal-content bg-surface p-8 rounded-lg w-full max-w-2xl border border-border-color">
            <h3 class="text-2xl font-bold mb-4"><?= __('manage_collection_games') ?></h3>
            <div class="modal-search-bar mb-4">
                <input type="text" id="modal-game-search" placeholder="<?= __('search_placeholder') ?>" class="w-full bg-background border border-border-color p-2 rounded">
            </div>
            <div class="max-h-96 overflow-y-auto" id="modal-games-list">
                <!-- Injected by JS -->
            </div>
            <div class="mt-6 text-right">
              <button id="close-modal-btn" class="bg-primary text-black px-6 py-2 rounded font-bold transition-all hover:opacity-80"><?= __('close_btn') ?></button>
            </div>
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
