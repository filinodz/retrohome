// public/js/auth.js
document.addEventListener('DOMContentLoaded', () => {
    const authForm = document.getElementById('auth-form');
    const authSubmitButton = document.getElementById('auth-submit-button');
    const authToggle = document.getElementById('auth-toggle');
    const emailGroup = document.getElementById('email-group');
    const authMessage = document.getElementById('auth-message');

    if (!authForm) return;

    let isRegisterMode = false;

    if (authToggle) {
        authToggle.addEventListener('click', () => {
            isRegisterMode = !isRegisterMode;
            if (isRegisterMode) {
                if (emailGroup) emailGroup.style.display = 'block';
                authSubmitButton.innerHTML = 'REGISTRATION_INITIATED <i class="fas fa-user-plus ml-2"></i>';
                authToggle.textContent = 'SYSTEM_ID_ALREADY_EXISTS?_LOGIN';
            } else {
                if (emailGroup) emailGroup.style.display = 'none';
                authSubmitButton.innerHTML = 'INITIATE_SESSION <i class="fas fa-arrow-right ml-2"></i>';
                authToggle.textContent = 'Create_New_System_Profile?';
            }
        });
    }

    authForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = isRegisterMode ? 'register' : 'login';
        authSubmitButton.disabled = true;
        const originalContent = authSubmitButton.innerHTML;
        authSubmitButton.innerHTML = 'HANDSHAKING... <i class="fas fa-spinner fa-spin ml-2"></i>';

        try {
            const formData = new FormData(authForm);
            const response = await fetch(`${SITE_URL}/api?action=${action}`, {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const result = await response.json();

            if (response.ok) {
                authMessage.textContent = "ACCESS_GRANTED_REDIRECTING...";
                authMessage.className = "mt-4 text-xs font-mono text-center text-green-400";
                setTimeout(() => {
                    location.href = SITE_URL + '/';
                }, 1000);
            } else {
                authMessage.textContent = `CRITICAL_ERROR: ${result.error || 'UNKNOWN_FAILURE'}`;
                authMessage.className = "mt-4 text-xs font-mono text-center text-red-500";
            }
        } catch (err) {
            console.error('Auth error:', err);
            authMessage.textContent = "NETWORK_PROTOCOL_ERROR";
            authMessage.className = "mt-4 text-xs font-mono text-center text-red-500 animate__animated animate__shakeX";
        } finally {
            authSubmitButton.disabled = false;
            if (!authMessage.textContent.includes('GRANTED')) {
                authSubmitButton.innerHTML = originalContent;
            }
        }
    });

    // --- CyberPunk Specific Video Loop for Login Page ---
    const videoPreview = document.getElementById('gamePreview');
    if (videoPreview) {
        async function fetchRandomGame() {
            try {
                const res = await fetch('api?action=getRandomGame');
                if (!res.ok) return;
                const game = await res.json();
                if (game && game.preview) {
                    playLoginPreview(game);
                }
            } catch (e) { console.error('Error fetching random game for login preview:', e); }
        }

        function playLoginPreview(game) {
            videoPreview.src = game.preview;
            const title = document.getElementById('gameTitle');
            const desc = document.getElementById('gameDescription');
            const logo = document.getElementById('consoleLogo');

            if (title) title.textContent = game.title;
            if (desc) desc.textContent = game.description || 'NO_DATA_AVAILABLE';

            if (logo && game.console_logo) {
                const logoPath = game.console_logo;
                // Use SITE_URL if available or try relative
                const baseUrl = (typeof SITE_URL !== 'undefined') ? SITE_URL : '';
                logo.src = logoPath.startsWith('http') ? logoPath : `${baseUrl}/${logoPath}`;
                logo.style.display = 'block';
            } else if (logo) {
                logo.style.display = 'none';
            }
        }

        fetchRandomGame();
    }
});
