<?php
// includes/js_translations.php
?>
<script>
    window.RetroHome_Translations = {
        play: "<?= __('play') ?>",
        preview: "<?= __('preview') ?>",
        all: "<?= __('all_consoles') ?>",
        no_games: "<?= __('no_description') ?>",
        close: "<?= __('close_btn') ?>",
        back: "<?= __('back_home') ?>",
        loading: "<?= __('loading') ?>",
        error_games: "<?= __('error') ?>",
        error_consoles: "<?= __('error') ?>",
        stats_games: "<?= __('stats_games') ?>",
        stats_consoles: "<?= __('stats_consoles') ?>"
    };

    // Helper function for JS
    function _t(key, fallback = '') {
        return window.RetroHome_Translations[key] || fallback || key;
    }
</script>
