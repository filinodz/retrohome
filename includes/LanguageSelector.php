<?php
// includes/LanguageSelector.php
global $languageManager;
$currentLang = $languageManager->getCurrent();
$availableLangs = $languageManager->getAvailableLanguages();
?>
<?php
// Default direction is 'up' (for footer). Set $langSelectorDirection = 'down' for header.
$direction = $langSelectorDirection ?? 'up';
$dropdownClasses = ($direction === 'up') 
    ? 'origin-bottom-right bottom-full mb-2' 
    : 'origin-top-right top-full mt-2';
?>
<div class="language-selector-wrapper relative inline-block text-left z-[100000]">
    <button type="button" class="language-menu-button inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75 items-center gap-2 border border-gray-600 shadow-lg transition-all duration-300 transform hover:scale-105" style="font-family: 'Cairo', sans-serif !important;" aria-expanded="false" aria-haspopup="true">
        <img src="<?= $availableLangs[$currentLang]['flag'] ?>" alt="<?= $currentLang ?>" class="w-6 h-auto rounded-sm shadow-sm">
        <span class="uppercase font-bold tracking-wider"><?= $currentLang ?></span>
        <svg class="chevron-icon -mr-1 ml-2 h-5 w-5 transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>
    
    <div class="language-menu-dropdown <?= $dropdownClasses ?> absolute right-0 w-40 rounded-md shadow-2xl bg-gray-900 border border-gray-700 ring-1 ring-black ring-opacity-5 focus:outline-none opacity-0 invisible transition-all duration-300 transform translate-y-2 z-[100001]" role="menu" aria-orientation="vertical" tabindex="-1">
        <div class="py-1" role="none">
            <?php foreach ($availableLangs as $code => $lang): ?>
                <a href="?lang=<?= $code ?>" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors duration-200 <?= $currentLang === $code ? 'bg-gray-800 font-bold text-white' : '' ?>" style="font-family: 'Cairo', sans-serif !important;" role="menuitem" tabindex="-1">
                    <img src="<?= $lang['flag'] ?>" alt="<?= $code ?>" class="w-6 h-auto rounded-sm shadow-sm">
                    <span class="font-medium"><?= $lang['name'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!defined('LANGUAGE_SELECTOR_SCRIPT_INCLUDED')): ?>
<?php define('LANGUAGE_SELECTOR_SCRIPT_INCLUDED', true); ?>
<script>
document.addEventListener('click', function(e) {
    const button = e.target.closest('.language-menu-button');
    if (button) {
        e.preventDefault();
        e.stopPropagation();
        const wrapper = button.closest('.language-selector-wrapper');
        const dropdown = wrapper.querySelector('.language-menu-dropdown');
        const chevron = button.querySelector('.chevron-icon');
        const isClosed = dropdown.classList.contains('invisible');
        
        document.querySelectorAll('.language-menu-dropdown').forEach(d => {
            if (d !== dropdown) {
                d.classList.add('opacity-0', 'invisible', 'translate-y-2');
                d.classList.remove('opacity-100', 'visible', 'translate-y-0');
                const otherButton = d.closest('.language-selector-wrapper').querySelector('.language-menu-button');
                const otherChevron = otherButton.querySelector('.chevron-icon');
                otherChevron.classList.remove('rotate-180');
            }
        });

        if (isClosed) {
            dropdown.classList.remove('opacity-0', 'invisible', 'translate-y-2');
            dropdown.classList.add('opacity-100', 'visible', 'translate-y-0');
            chevron.classList.add('rotate-180');
        } else {
            dropdown.classList.add('opacity-0', 'invisible', 'translate-y-2');
            dropdown.classList.remove('opacity-100', 'visible', 'translate-y-0');
            chevron.classList.remove('rotate-180');
        }
    } else {
        if (!e.target.closest('.language-menu-dropdown')) {
            document.querySelectorAll('.language-menu-dropdown').forEach(d => {
                d.classList.add('opacity-0', 'invisible', 'translate-y-2');
                d.classList.remove('opacity-100', 'visible', 'translate-y-0');
                const wrapper = d.closest('.language-selector-wrapper');
                if (wrapper) {
                    const chevron = wrapper.querySelector('.chevron-icon');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            });
        }
    }
});
</script>
<?php endif; ?>
