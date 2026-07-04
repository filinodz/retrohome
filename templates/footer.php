<?php
// templates/footer.php
?>
<footer class="main-footer mt-20">
    <div class="container mx-auto px-6">
        <div class="footer-grid grid grid-cols-1 md:grid-cols-3 gap-12 py-12 border-t border-white/10">
            <!-- Branding Section -->
            <div class="footer-branding">
                <div class="flex items-center gap-4 mb-4">
                    <img src="<?= SITE_URL ?>/public/img/logo_new.png" alt="Logo" class="w-10 h-10 opacity-80">
                    <h3 class="pixel-font text-xl text-primary"><?= SITE_NAME ?></h3>
                </div>
                <p class="text-sm opacity-50 leading-relaxed">
                    Votre destination ultime pour le jeu rétro. Redécouvrez les classiques, gérez votre collection et jouez instantanément dans votre navigateur.
                </p>
            </div>

            <!-- Quick Links -->
            <div class="footer-links">
                <h4 class="font-bold text-white mb-6 uppercase tracking-wider text-xs">Navigation Express</h4>
                <ul class="space-y-3 text-sm opacity-60">
                    <li><a href="<?= SITE_URL ?>/" class="hover:text-primary transition-colors">Accueil / Bibliothèque</a></li>
                    <li><a href="<?= SITE_URL ?>/profile" class="hover:text-primary transition-colors">Mon Profil de Joueur</a></li>
                    <li><a href="#" class="hover:text-primary transition-colors">Politique de Confidentialité</a></li>
                </ul>
            </div>

            <!-- Social/Contact -->
            <div class="footer-social">
                <h4 class="font-bold text-white mb-6 uppercase tracking-wider text-xs">Rejoignez la Communauté</h4>
                <div class="flex gap-4">
                    <a href="#" class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center hover:bg-primary hover:text-black transition-all">
                        <i class="fab fa-discord"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center hover:bg-primary hover:text-black transition-all">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center hover:bg-primary hover:text-black transition-all">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom py-8 border-t border-white/5 text-center">
            <p class="text-xs opacity-40 uppercase tracking-[0.2em]">
                RetroHome <span class="mx-2">•</span> Made with <i class="fas fa-heart text-red-500 animate-pulse mx-1"></i> by <span class="text-white font-bold">FilinoDZ</span>
            </p>
            <p class="text-[10px] opacity-20 mt-2 italic">
                &copy; <?= date('Y') ?> <?= SITE_NAME ?> Engine. Toutes les marques déposées appartiennent à leurs propriétaires respectifs.
            </p>
        </div>
    </div>
</footer>
