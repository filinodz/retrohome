// public/video-preview.js
const VideoPreview = {
    currentPreviewUrl: null, // Ajout : URL de la prévisualisation en cours

    showPreview: function(url, gameTitle) {
        const videoElement = document.getElementById('preview-video');
        const titleElement = document.getElementById('preview-game-title');
        const container = document.getElementById('preview-container');
        const closeBtn = document.getElementById('close-preview-btn');


        if (!url) {
            console.error('Preview URL is missing!');
            return;
        }

        // Ferme la prévisualisation précédente (si elle existe)
        if (this.currentPreviewUrl) {
            this.hidePreview();
        }

        videoElement.src = url;
        titleElement.textContent = gameTitle;
        container.style.display = 'flex'; // Utilise flex pour centrer
        this.currentPreviewUrl = url; // Stocke l'URL actuelle


         closeBtn.onclick = () => this.hidePreview();

        // Fermeture au clic en dehors (CORRIGÉ)
        container.onclick = (event) => {
            if (event.target === container) {
                this.hidePreview();
            }
        };
    },

    hidePreview: function() {
        const videoElement = document.getElementById('preview-video');
        const container = document.getElementById('preview-container');


        videoElement.pause();
        videoElement.src = ''; // Important!
        container.style.display = 'none';
        this.currentPreviewUrl = null; // Réinitialise

         // Émet un événement personnalisé (utile si tu veux faire d'autres actions)
        const event = new Event('previewHidden');
        document.dispatchEvent(event);
    }
};