<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>RetroHome - Connexion</title>
    <link rel="icon" type="image/png" href="/assets/img/playstation.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- Variables --- */
        :root {
            /* Palette sobre pour la TV */
            --tv-body-bg-start: #383838;
            --tv-body-bg-end: #1a1a1a;
            --tv-body-border: #111;
            --tv-screen-bg: #080808;
            --tv-stand-bg-start: #404040;
            --tv-stand-bg-end: #202020;
            --tv-button-bg: #2d2d2d;
            --tv-button-border: #181818;
            --tv-button-hover-bg: #444;
            --tv-button-active-bg: #00f0ff; /* Bouton actif peut rester moderne */

            /* Palette Formulaire (peut rester moderne ou être ajustée) */
            --primary: #00f0ff;
            --secondary: #ff00ff;
            --accent: #f4ff81;
            --background: #0d0d1a;
            --surface: #1a1a2e;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0c0;
            --border-color: #30304a;
            --glow-color-primary: rgba(0, 240, 255, 0.4);
            --glow-color-secondary: rgba(255, 0, 255, 0.4); /* Ajout pour le hover du bouton */

             /* Polices */
            --font-pixel: 'Orbitron', sans-serif;
            --font-body: 'Rajdhani', sans-serif;
        }

        /* --- Styles Généraux --- */
        body {
            font-family: var(--font-body);
            background: linear-gradient(180deg, #222 0%, #050505 100%); /* Fond sombre simple */
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
            padding: 1rem; /* Espace sur les bords */
            box-sizing: border-box; /* Inclut padding dans la taille */
        }
        /* Styles pour le container principal */
         .container {
            display: flex; /* Active Flexbox */
            min-height: calc(100vh - 2rem); /* Prend la hauteur de la vue moins le padding body */
            width: 100%;
            align-items: center; /* Centre verticalement */
            justify-content: center; /* Centre horizontalement */
        }

        /* Styles pour la structure flex (TV + Auth) */
        .flex-container { /* Nouveau nom pour le div qui contient TV et Auth */
            display: flex;
            flex-direction: column; /* Empilé par défaut (mobile first) */
            gap: 2rem; /* Espace entre TV et Auth */
            align-items: center; /* Centre les éléments empilés */
            width: 100%;
            max-width: 1200px; /* Limite largeur max globale */
        }
        /* Layout pour écrans larges */
        @media (min-width: 1024px) {
            .flex-container {
                flex-direction: row; /* Côte à côte sur large */
                gap: 4rem; /* Espace plus grand */
                align-items: center; /* Garde l'alignement vertical */
            }
            .tv-container-wrapper, /* Wrapper pour la TV */
            .auth-container-wrapper { /* Wrapper pour l'authentification */
                flex: 1; /* Prend 50% de la largeur disponible */
                display: flex; /* Utiliser flex pour centrer leur contenu */
                justify-content: center;
            }
        }


        /* Utilitaires d'animation (pour décaler l'apparition) */
        .animate__delay-100ms { animation-delay: 0.1s; }
        .animate__delay-200ms { animation-delay: 0.2s; }
        .animate__delay-300ms { animation-delay: 0.3s; }
        .animate__delay-400ms { animation-delay: 0.4s; }
        .animate__delay-500ms { animation-delay: 0.5s; }

        /* --- Styles TV CRT Réaliste (Avec ajustements responsifs) --- */
        .tv-container {
            perspective: 1200px;
            width: 100%;
            max-width: 800px; /* Largeur max augmentée */
            margin: 0 auto; /* Centrage */
        }

        .tv-body {
            background: linear-gradient(140deg, var(--tv-body-bg-start), var(--tv-body-bg-end));
            border-radius: 22px;
            padding: 25px 25px 35px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.55), inset 0 0 20px rgba(0, 0, 0, 0.35), 0 2px 4px rgba(255, 255, 255, 0.06);
            position: relative;
            border: 2px solid var(--tv-body-border);
            transform-style: preserve-3d;
            transform: rotateX(-4deg) rotateY(0deg);
            transition: transform 0.3s ease;
        }
        .tv-body:hover {
             transform: rotateX(-2deg) rotateY(0deg) scale(1.01);
        }

        .tv-screen-container {
            position: relative;
            background: var(--tv-screen-bg);
            border-radius: 12px 12px 80px / 20px;
            overflow: hidden;
            border: 3px inset #151515;
            box-shadow: inset 0 0 25px rgba(0, 0, 0, 0.85);
            transform: translateZ(18px);
            aspect-ratio: 4 / 3;
        }

        .tv-screen-effects {
             position: absolute; inset: 0;
             pointer-events: none; z-index: 2;
             border-radius: inherit; overflow: hidden;
        }
        .tv-screen-effects::before { /* Scanlines */
            content: ''; position: absolute; inset: 0;
            background: repeating-linear-gradient( to bottom, transparent 0, transparent 1px, rgba(0, 0, 0, 0.3) 1px, rgba(0, 0, 0, 0.15) 3px );
            animation: subtle-scanline 12s linear infinite; opacity: 0.7; z-index: 3;
        }
        @keyframes subtle-scanline { 0% { transform: translateY(-1px); } 100% { transform: translateY(1px); } }
        .tv-screen-effects::after { /* Bruit/Grain */
             content: ''; position: absolute; inset: -50%;
             background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAMAAAAp4XiDAAAAUVBMVEWFhYWDg4N3d3dtbW17e3t1dXWBgYGHh4d5eXlzc3OLi4ubm5uVlZWPj4+NjY19fX2JiYl/f39ra2uRkZGZmZlpaWmXl5dvb29xcXGTk5NnZ2c8TV1mAAAAG3RSTlNAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEAvEOwtAAAFVklEQVR4XpWWB67c2BUFb3g557T/hRo9/WUMZHlgr4Bg8Z4qQg7VUfKpJMPg5wCS6MlTrYUkmMyVnqpCS6MlLUtnMY2RmCZमाजリエステル جي جي હેલ્પ હેલ્પ હેલ્પ મિળતViewModelsViewModelsViewModels');
             opacity: 0.09; animation: grain 0.3s steps(1) infinite; z-index: 2; mix-blend-mode: overlay;
        }
         @keyframes grain { 0%, 100% { transform: translate(0, 0); } 10% { transform: translate(-1%, -1%); } 20% { transform: translate(1%, 1%); } 30% { transform: translate(-1%, 1%); } 40% { transform: translate(1%, -1%); } 50% { transform: translate(-2%, -2%); } 60% { transform: translate(2%, 2%); } 70% { transform: translate(-2%, 2%); } 80% { transform: translate(2%, -2%); } 90% { transform: translate(0, 1%); } }
        .tv-glass-reflection { /* Reflet */
             position: absolute; inset: 0;
             background: linear-gradient( 155deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.04) 30%, transparent 55% );
             pointer-events: none; z-index: 4; border-radius: inherit;
         }

        #gamePreview {
           position: absolute; inset: 0;
           width: 100%; height: 100%; object-fit: cover;
           transform: scale(1.02);
           z-index: 1; opacity: 0; transition: opacity 0.5s ease;
        }

          .loading-spinner {
              width: 50px; height: 50px;
              border: 5px solid rgba(255, 255, 255, 0.2);
              border-left-color: #fff;
              border-radius: 50%;
              animation: spin 1s linear infinite;
              position: relative; z-index: 5;
          }
          @keyframes spin { to { transform: rotate(360deg); } }

        .game-info {
          position: absolute; bottom: 0; left: 0; right: 0;
          background: linear-gradient(to top, rgba(10, 10, 10, 0.95) 0%, transparent 100%);
          padding: 1rem 1.2rem;
          transform: translateY(101%); transition: transform 0.4s ease-out;
          border-bottom-left-radius: inherit; border-bottom-right-radius: inherit;
          pointer-events: none; z-index: 5;
        }
        .tv-screen-container:hover .game-info { transform: translateY(0); }
        .game-info-content{
          display: flex; align-items: center; gap: 0.6rem; animation: fadeInUp 0.5s 0.1s both;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .game-info img#consoleLogo {
          height: 28px; width: auto; filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.6));
        }
        .game-info-text { overflow: hidden; }
        .game-info h3#gameTitle {
          font-size: 1.1rem; font-weight: 700; color: #f0f0f0; text-shadow: 1px 1px 2px #000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; line-height: 1.2;
        }
        .game-info p#gameDescription {
          font-size: 0.8rem; color: #ccc; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;
        }

        .tv-controls {
             display: flex; justify-content: center; gap: 15px; margin-top: 18px; transform: translateZ(15px);
         }
        .tv-button {
            width: 35px; height: 35px; background: var(--tv-button-bg); border: 1px solid var(--tv-button-border); border-radius: 50%; box-shadow: inset 0 -2px 3px rgba(0, 0, 0, 0.4), 0 2px 3px rgba(0, 0, 0, 0.3); cursor: pointer; transition: all 0.1s ease; display: flex; justify-content: center; align-items: center; color: #bbb; font-size: 0.9rem;
        }
        .tv-button:hover { background: var(--tv-button-hover-bg); color: #fff; }
        .tv-button:active { transform: translateY(1px) scale(0.96); box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5), 0 1px 1px rgba(0, 0, 0, 0.2); }
        .tv-button.active { background-color: var(--tv-button-active-bg); color: var(--background); box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.4), 0 0 8px var(--glow-color-primary); }

        .tv-stand {
            width: 60%; max-width: 380px;
            height: 25px;
            background: linear-gradient(to bottom, var(--tv-stand-bg-start), var(--tv-stand-bg-end));
            margin: 18px auto -18px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.45);
            position: relative; z-index: -1;
        }


        /* --- Formulaire d'Auth --- */
        .auth-container {
            background: rgba(26, 26, 46, 0.85); /* --surface avec opacité */
            backdrop-filter: blur(10px); /* Effet de flou derrière */
            border-radius: 15px; /* Bords arrondis */
            padding: 2rem 2.5rem; /* Espace intérieur */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4); /* Ombre portée */
            max-width: 450px; /* Largeur maximale */
            width: 100%; /* Prend la largeur disponible */
            border: 1px solid var(--border-color); /* Bordure subtile */
            position: relative; /* Pour le positionnement du pseudo-élément */
            overflow: hidden; /* Cache le dépassement du halo */
        }
        /* Halo rotatif */
        .auth-container::before {
            content: ''; position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient( transparent, var(--glow-color-primary), transparent 30% );
            animation: rotate-glow 5s linear infinite;
            pointer-events: none; z-index: 0;
        }
        @keyframes rotate-glow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        /* Contenu au-dessus du halo */
        .auth-content {
            position: relative;
            z-index: 1;
            display: flex; /* Utiliser flex pour centrer verticalement (approximatif) */
            flex-direction: column;
            align-items: center; /* Centre horizontalement */
        }
        /* Logo */
        #logo-image {
            display: block;
            max-width: 250px; /* Taille logo ajustée */
            height: auto;
            margin: 0 auto 0.8rem; /* Espace réduit en bas */
            filter: drop-shadow(0 0 8px var(--glow-color-primary)); /* Halo ajusté */
            transition: transform 0.3s ease;
        }
        #logo-image:hover { transform: scale(1.05); }
        /* Sous-titre */
        .auth-subtitle {
            color: var(--text-secondary);
            margin-bottom: 1.8rem; /* Espace ajusté */
            font-size: 1rem;
            text-align: center;
        }
        /* Groupe Input */
        .input-group {
            position: relative;
            margin-bottom: 1.2rem;
            width: 100%; /* Assure que le groupe prend toute la largeur */
        }
        .input-group input {
            width: 100%;
            padding: 1.1rem 1.1rem 0.7rem; /* Padding pour label flottant */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: rgba(13, 13, 26, 0.7); /* --background avec opacité */
            color: var(--text-primary);
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            outline: none;
        }
        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 12px var(--glow-color-primary);
        }
        /* Label flottant */
        .input-group label {
            position: absolute;
            top: 0.9rem; /* Position initiale */
            left: 1.1rem;
            color: var(--text-secondary);
            pointer-events: none;
            transition: all 0.25s ease;
            font-size: 1rem;
            background-color: transparent; /* Nécessaire pour la superposition */
            padding: 0 0.3rem; /* Empêche le texte de toucher les bords */
        }
        /* Style du label quand l'input est focus ou rempli */
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label,
        .input-group input.label-floated + label { /* Classe ajoutée par JS pour autofill */
            transform: translateY(-0.7rem) translateX(-0.2rem) scale(0.8); /* Monte et rétrécit */
            color: var(--primary); /* Change la couleur */
            /* Fond pour masquer la ligne de l'input derrière */
            background-color: var(--surface); /* Doit correspondre au fond de .auth-container ou input */
            z-index: 1; /* Pour être au-dessus de la bordure de l'input */
        }
         /* Style spécifique pour l'input Email quand il est caché */
         #email-group[style*="display: none"] {
             margin-bottom: 0; /* Évite l'espace vide */
         }

        /* Bouton Submit */
        .auth-form button[type="submit"] {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: #050510; /* Texte très sombre */
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex; /* Pour aligner icône */
            align-items: center;
            justify-content: center;
        }
        .auth-form button[type="submit"]:hover {
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 8px 20px var(--glow-color-primary), 0 8px 20px var(--glow-color-secondary);
        }
        .auth-form button[type="submit"]:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }
         /* Style pour l'icône dans le bouton */
        .auth-form button[type="submit"] i {
            transition: transform 0.3s ease;
        }
        .auth-form button[type="submit"]:hover i {
             transform: translateX(3px);
        }


        /* Bouton Toggle Inscription/Connexion */
        #auth-toggle {
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
            margin-top: 1.5rem; /* Espace au-dessus */
            display: inline-block;
            font-weight: 500;
            background: none;
            border: none;
            padding: 0.5rem; /* Zone de clic */
            font-size: 0.9rem;
            text-align: center; /* Assure centrage */
            width: 100%; /* Prend toute la largeur pour centrage facile */
        }
        #auth-toggle:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Message d'erreur/succès */
        #auth-message {
            margin-top: 1rem;
            font-weight: 500;
            min-height: 1.5em;
            text-align: center;
            transition: opacity 0.3s ease;
            width: 100%; /* Prend toute la largeur */
        }
        #auth-message.error { color: #ff4d4d; }
        #auth-message.success { color: var(--accent); }


        /* --- Responsive --- */
        @media (max-width: 1023px) { /* Styles pour tablettes et moins (si pas déjà dans .flex-container) */
            .flex-container {
                 max-width: 700px; /* Limite largeur contenu empilé */
             }
            .tv-container-wrapper { /* Pas nécessaire si .tv-container gère sa largeur */
                width: 100%;
             }
             .tv-container {
                 max-width: 650px;
             }
             .auth-container-wrapper { /* Pas nécessaire si .auth-container gère sa largeur */
                width: 100%;
             }
            .auth-container {
                max-width: 450px;
                padding: 1.5rem 2rem;
            }
        }

        @media (max-width: 767px) { /* Styles pour mobile */
            body { padding: 0.5rem; } /* Réduit padding body */
             .flex-container { gap: 1.5rem; } /* Espace réduit */

            .tv-body { padding: 18px 18px 25px; border-radius: 15px; transform: none; }
            .tv-screen-container { border-radius: 10px 10px 50px / 12px; border-width: 2px; }
            .tv-controls { gap: 12px; margin-top: 15px; }
            .tv-button { width: 32px; height: 32px; font-size: 0.8rem; }
            .tv-stand { height: 20px; margin-top: 15px; border-radius: 0 0 10px 10px; max-width: 70%; }
            .game-info { padding: 0.7rem 0.9rem; }
            .game-info img#consoleLogo { height: 24px; }
            .game-info h3#gameTitle { font-size: 1rem; }
            .game-info p#gameDescription { font-size: 0.75rem; }
            .loading-spinner { width: 40px; height: 40px; border-width: 4px; }

            .auth-container { padding: 1.5rem; border-radius: 12px;}
            #logo-image { max-width: 200px; margin-bottom: 0.8rem;}
            .auth-subtitle { font-size: 0.95rem; margin-bottom: 1.5rem;}
            .input-group input { padding: 1rem 1rem 0.6rem; font-size: 0.95rem; border-radius: 6px;}
            .input-group label { top: 0.8rem; left: 1rem; font-size: 0.95rem; }
            .input-group input:focus + label, .input-group input:not(:placeholder-shown) + label, .input-group input.label-floated + label { transform: translateY(-0.65rem) translateX(-0.15rem) scale(0.75); }
            .auth-form button[type="submit"] { padding: 0.8rem 1rem; font-size: 1rem; border-radius: 6px;}
            #auth-toggle { margin-top: 1rem; font-size: 0.85rem;}
        }

        @media (max-width: 479px) { /* Styles pour petits mobiles */
             .flex-container { gap: 1rem; }

            .tv-body { padding: 12px 12px 18px; border-radius: 10px; border-width: 1px;}
            .tv-screen-container { border-radius: 8px 8px 35px / 9px; }
            .tv-controls { gap: 10px; margin-top: 12px;}
            .tv-button { width: 28px; height: 28px; font-size: 0.7rem; }
            .tv-stand { height: 16px; margin-top: 12px; border-radius: 0 0 8px 8px; max-width: 65%; }
            .game-info { padding: 0.5rem 0.7rem; }
            .game-info-content { gap: 0.4rem; }
            .game-info img#consoleLogo { height: 18px; }
            .game-info h3#gameTitle { font-size: 0.85rem; }
            .game-info p#gameDescription { font-size: 0.7rem; }
            .loading-spinner { width: 30px; height: 30px; border-width: 3px; }

            .auth-container { padding: 1.2rem 1rem; border-radius: 10px; }
            #logo-image { max-width: 160px; margin-bottom: 0.5rem; }
            .auth-subtitle { font-size: 0.9rem; margin-bottom: 1.2rem; }
            .input-group { margin-bottom: 1rem; }
            .input-group input { padding: 0.9rem 0.8rem 0.5rem; font-size: 0.9rem; border-radius: 5px;}
            .input-group label { top: 0.7rem; left: 0.8rem; font-size: 0.9rem; }
             .input-group input:focus + label, .input-group input:not(:placeholder-shown) + label, .input-group input.label-floated + label { transform: translateY(-0.6rem) translateX(-0.1rem) scale(0.7); }
            .auth-form button[type="submit"] { padding: 0.7rem 0.9rem; font-size: 0.95rem; border-radius: 5px;}
            #auth-toggle { margin-top: 0.8rem; font-size: 0.8rem;}
        }

    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="container mx-auto px-4 min-h-screen flex items-center justify-center">
        <div class="flex flex-col lg:flex-row gap-8 lg:gap-16 items-center w-full max-w-6xl">

            <!-- TV Section (Gauche) -->
            <div class="tv-container w-full lg:w-1/2 animate__animated animate__fadeInLeft animate__slow">
                <div class="tv-body">
                    <div class="tv-screen-container"> 
                       
                        <div id="loading" class="absolute inset-0 flex items-center justify-center z-[5]">
                             <div class="loading-spinner"></div>
                        </div>
                      
                        <video id="gamePreview" loop muted autoplay playsinline></video>

                        <div class="tv-screen-effects">
                        
                        </div>
   
                        <div class="tv-glass-reflection"></div>

                        <div class="game-info">
                            <div class="game-info-content">
                                <img id="consoleLogo" src="" alt="Console Logo">
                                <div class="game-info-text">
                                    <h3 id="gameTitle"></h3>
                                     <p id="gameDescription"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tv-controls">
                        <button id="prevButton" class="tv-button" aria-label="Previous Game"><i class="fas fa-backward-step"></i></button>
                        <button id="playPauseButton" class="tv-button active" aria-label="Pause"><i class="fas fa-pause"></i></button>
                        <button id="nextButton" class="tv-button" aria-label="Next Game"><i class="fas fa-forward-step"></i></button>
                        <button id="muteButton" class="tv-button" aria-label="Mute"><i class="fas fa-volume-xmark"></i></button>
                    </div>
                </div>
                 <div class="tv-stand"></div>
            </div>

            <div class="auth-container w-full lg:w-1/2 animate__animated animate__fadeInRight animate__slow">
                <div class="auth-content">
                    <img src="public/img/logo.png" alt="RetroHome Logo" id="logo-image" class="animate__animated animate__zoomIn animate__delay-100ms">
                    <p class="auth-subtitle animate__animated animate__fadeInUp animate__delay-200ms">
                        Revivez la magie des jeux rétro.
                    </p>
                    <form id="auth-form" class="space-y-4 animate__animated animate__fadeInUp animate__delay-300ms">
                        <div class="input-group">
                            <input type="text" name="username" id="username" placeholder=" " required autocomplete="username">
                            <label for="username">Nom d'utilisateur</label>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                            <label for="password">Mot de passe</label>
                        </div>
                       <div class="input-group" id="email-group" style="display: none;">
                            <input type="email" name="email" id="email" placeholder=" " autocomplete="email">
                            <label for="email">Adresse Email</label>
                        </div>
                        <button type="submit" id="auth-submit-button">
                            Connexion <i class="fas fa-sign-in-alt ml-2"></i>
                        </button>
                    </form>
                    <div class="mt-6 text-center animate__animated animate__fadeInUp animate__delay-400ms">
                        <button type="button" id="auth-toggle">
                            Pas encore de compte ? Inscrivez-vous !
                        </button>
                    </div>
                    <div id="auth-message" class="mt-4 min-h-[1.5em] text-center animate__animated animate__fadeInUp animate__delay-500ms"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
       document.addEventListener('DOMContentLoaded', () => {
          const authForm = document.getElementById('auth-form');
          const authSubmitButton = document.getElementById('auth-submit-button');
          const authToggle = document.getElementById('auth-toggle');
          const emailGroup = document.getElementById('email-group');
          const emailInput = document.getElementById('email');
          const authMessage = document.getElementById('auth-message');
          const gamePreview = document.getElementById('gamePreview');
          const loading = document.getElementById('loading');
          const consoleLogo = document.getElementById('consoleLogo');
          const gameTitle = document.getElementById('gameTitle');
          const gameDescription = document.getElementById('gameDescription');
          const prevButton = document.getElementById('prevButton');
          const playPauseButton = document.getElementById('playPauseButton');
          const nextButton = document.getElementById('nextButton');
          const muteButton = document.getElementById('muteButton');

           console.log("Vérification éléments DOM:");
           console.log(" - #gamePreview:", gamePreview ? 'Trouvé' : 'MANQUANT!');
           console.log(" - #loading:", loading ? 'Trouvé' : 'MANQUANT!');
           console.log(" - #consoleLogo:", consoleLogo ? 'Trouvé' : 'MANQUANT!');
           console.log(" - #gameTitle:", gameTitle ? 'Trouvé' : 'MANQUANT!');
           console.log(" - #gameDescription:", gameDescription ? 'Trouvé' : 'MANQUANT!');
           //... (autres vérifications si besoin)

          let isRegisterMode = false;
          let games = [];
          let currentGameIndex = -1;
          let isPlaying = true;
          let isMuted = true;

          async function loadGames() {
             if (!gamePreview || !loading || !consoleLogo || !gameTitle || !gameDescription) {
                console.error("loadGames: Arrêt car des éléments de l'interface TV sont manquants.");
                 if (loading) loading.innerHTML = '<p class="text-xs text-red-500">Erreur init.</p>';
                 else console.error("L'élément #loading est manquant.");
                return;
             }
             console.log("loadGames: Tentative de chargement des jeux...");
             try {
                 const response = await fetch('api.php?action=getGames');
                 console.log("loadGames: Réponse API reçue, statut:", response.status);
                 if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                 const data = await response.json();
                 console.log("loadGames: Données API brutes:", data);
                 games = data.filter(game => game.preview && game.preview.trim() !== '' && typeof game.preview === 'string');
                 console.log(`loadGames: ${games.length} jeux trouvés avec une preview valide.`);
                 if (games.length > 0) {
                     currentGameIndex = Math.floor(Math.random() * games.length);
                     console.log(`loadGames: Index de jeu initial choisi: ${currentGameIndex}`);
                     showGamePreview(currentGameIndex);
                 } else {
                     console.warn("loadGames: Aucun jeu avec une preview valide.");
                     loading.innerHTML = '<p class="text-xs text-gray-400">Aucune preview</p>';
                     if (gamePreview) gamePreview.style.display = 'none';
                 }
             } catch (error) {
                 console.error("loadGames: Erreur:", error);
                 if(loading) loading.innerHTML = '<p class="text-xs text-red-400">Erreur chargement</p>';
                 if(authMessage) { authMessage.textContent = "Erreur chargement preview."; authMessage.className = 'mt-4 min-h-[1.5em] text-center error'; }
             }
          }

          function showGamePreview(index) {
             if (!gamePreview || !loading || !consoleLogo || !gameTitle || !gameDescription) { console.error("showGamePreview: Éléments manquants."); return; }
             console.log(`showGamePreview: Affichage index ${index}`);
             if (index < 0 || index >= games.length) { console.warn(`showGamePreview: Index invalide (${index}).`); return; }
             const game = games[index];
             console.log("showGamePreview: Jeu sélectionné:", game);
             consoleLogo.src = game?.console_logo || '';
             consoleLogo.alt = game?.console_name ? `${game.console_name} Logo` : 'Console Logo';
             gameTitle.textContent = game?.title || 'Titre Indisponible';
             gameDescription.textContent = game?.description || '';
             gamePreview.style.opacity = '0';
             loading.style.display = 'flex';
             gamePreview.removeEventListener('canplay', onCanPlayHandler);
             gamePreview.removeEventListener('error', onErrorHandler);
             gamePreview.removeEventListener('loadeddata', onLoadedDataHandler);
             const previewSrc = game?.preview;
             if (typeof previewSrc === 'string' && previewSrc.trim() !== '') {
                 console.log(`showGamePreview: Définition src = "${previewSrc}"`);
                 gamePreview.src = previewSrc;
                 gamePreview.addEventListener('canplay', onCanPlayHandler);
                 gamePreview.addEventListener('error', onErrorHandler);
                 gamePreview.addEventListener('loadeddata', onLoadedDataHandler);
                 console.log("showGamePreview: Appel load()");
                 gamePreview.load();
             } else {
                 console.warn(`showGamePreview: URL preview invalide pour index ${index}.`, game);
                 loading.innerHTML = '<p class="text-xs text-gray-400">Preview N/A</p>';
                 gamePreview.style.opacity = '0';
             }
             gamePreview.muted = isMuted;
             updatePlayPauseButtonIcon();
             updateMuteButtonIcon();
          }

          const onCanPlayHandler = () => {
             if (!gamePreview || !loading) return;
             console.log("onCanPlayHandler: canplay. Source:", gamePreview.src);
             gamePreview.style.opacity = '1';
             loading.style.display = 'none';
             if (isPlaying) { gamePreview.play().catch(e => console.warn("onCanPlayHandler: Autoplay échoué.", e)); }
          };
          const onLoadedDataHandler = () => { if (!gamePreview) return; console.log("onLoadedDataHandler: loadeddata. Source:", gamePreview.src); };
          const onErrorHandler = (e) => {
             if (!gamePreview || !loading) return;
             console.error("onErrorHandler: Erreur vidéo. Source:", gamePreview.src, "Erreur:", e, "Code:", gamePreview.error?.code, "Msg:", gamePreview.error?.message);
             loading.innerHTML = '<p class="text-xs text-red-400">Erreur vidéo</p>';
             gamePreview.style.opacity = '0';
          };

           function updatePlayPauseButtonIcon() { if (!playPauseButton) return; playPauseButton.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>'; playPauseButton.setAttribute('aria-label', isPlaying ? 'Pause' : 'Play'); playPauseButton.classList.toggle('active', isPlaying); }
           function updateMuteButtonIcon() { if (!muteButton) return; muteButton.innerHTML = isMuted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-high"></i>'; muteButton.setAttribute('aria-label', isMuted ? 'Unmute' : 'Mute'); muteButton.classList.toggle('active', !isMuted); }
           function loadNextGame() { if (games.length === 0) return; currentGameIndex = (currentGameIndex + 1) % games.length; console.log("loadNextGame: Index", currentGameIndex); showGamePreview(currentGameIndex); }
           function loadPrevGame() { if (games.length === 0) return; currentGameIndex = (currentGameIndex - 1 + games.length) % games.length; console.log("loadPrevGame: Index", currentGameIndex); showGamePreview(currentGameIndex); }

           if (authForm && authToggle && emailGroup && authSubmitButton && authMessage) {
               authToggle.addEventListener('click', () => {
                 isRegisterMode = !isRegisterMode;
                 authMessage.textContent = '';
                 authMessage.className = 'mt-4 min-h-[1.5em] text-center';
                 if (isRegisterMode) {
                    emailGroup.style.display = 'block';
                    emailGroup.classList.remove('animate__fadeOut'); emailGroup.classList.add('animate__animated', 'animate__fadeIn');
                    authSubmitButton.innerHTML = 'Inscription <i class="fas fa-user-plus ml-2"></i>';
                    authToggle.textContent = 'Déjà un compte ? Connectez-vous.';
                    emailInput.required = true;
                 } else {
                    emailGroup.classList.remove('animate__fadeIn'); emailGroup.classList.add('animate__animated', 'animate__fadeOut');
                    emailGroup.addEventListener('animationend', () => { if (!isRegisterMode) emailGroup.style.display = 'none'; emailGroup.classList.remove('animate__animated', 'animate__fadeOut'); }, { once: true });
                    authSubmitButton.innerHTML = 'Connexion <i class="fas fa-sign-in-alt ml-2"></i>';
                    authToggle.textContent = "Pas encore de compte ? Inscrivez-vous !";
                    emailInput.required = false;
                 }
               });
               authForm.addEventListener('submit', async (e) => {
                 e.preventDefault(); authMessage.textContent = ''; authMessage.className = 'mt-4 min-h-[1.5em] text-center';
                 const username = authForm.username.value.trim(); const password = authForm.password.value.trim(); const email = authForm.email.value.trim();
                 if (!username || !password || (isRegisterMode && !email)) { authMessage.textContent = "Champs requis."; authMessage.className += ' error animate__animated animate__headShake'; authMessage.addEventListener('animationend', () => authMessage.classList.remove('animate__animated', 'animate__headShake'), { once: true }); return; }
                 if (isRegisterMode && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { authMessage.textContent = "Email invalide."; authMessage.className += ' error animate__animated animate__headShake'; authMessage.addEventListener('animationend', () => authMessage.classList.remove('animate__animated', 'animate__headShake'), { once: true }); return; }
                 const formData = new FormData(authForm); if (!isRegisterMode) formData.delete('email'); const action = isRegisterMode ? 'register' : 'login';
                 authSubmitButton.disabled = true; authSubmitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Patientez...';
                 try {
                     const response = await fetch(`api.php?action=${action}`, { method: 'POST', body: new URLSearchParams(formData) });
                     const responseData = await response.json();
                     if (response.ok) { authMessage.textContent = "Succès ! Redirection..."; authMessage.className += ' success'; setTimeout(() => { window.location.href = 'index.php'; }, 1000);
                     } else { authMessage.textContent = responseData.error || 'Erreur.'; authMessage.className += ' error animate__animated animate__shakeX'; authMessage.addEventListener('animationend', () => authMessage.classList.remove('animate__animated', 'animate__shakeX'), { once: true }); authSubmitButton.disabled = false; authSubmitButton.innerHTML = isRegisterMode ? 'Inscription <i class="fas fa-user-plus ml-2"></i>' : 'Connexion <i class="fas fa-sign-in-alt ml-2"></i>'; }
                 } catch (error) { console.error("Erreur Fetch:", error); authMessage.textContent = "Erreur réseau."; authMessage.className += ' error animate__animated animate__shakeX'; authMessage.addEventListener('animationend', () => authMessage.classList.remove('animate__animated', 'animate__shakeX'), { once: true }); authSubmitButton.disabled = false; authSubmitButton.innerHTML = isRegisterMode ? 'Inscription <i class="fas fa-user-plus ml-2"></i>' : 'Connexion <i class="fas fa-sign-in-alt ml-2"></i>'; }
               });
           } else { console.error("Éléments auth manquants."); }

           if(prevButton) prevButton.addEventListener('click', loadPrevGame);
           if(nextButton) nextButton.addEventListener('click', loadNextGame);
           if(gamePreview) gamePreview.addEventListener('ended', loadNextGame);
           if(playPauseButton) playPauseButton.addEventListener('click', () => { if(!gamePreview) return; isPlaying = !isPlaying; if (isPlaying) gamePreview.play().catch(e => {}); else gamePreview.pause(); updatePlayPauseButtonIcon(); });
           if(muteButton) muteButton.addEventListener('click', () => { if(!gamePreview) return; isMuted = !isMuted; gamePreview.muted = isMuted; updateMuteButtonIcon(); });

           loadGames();

            document.querySelectorAll('.input-group input').forEach(input => {
              const label = input.nextElementSibling;
              if (!label || label.tagName !== 'LABEL') return;
              const checkAutofill = () => {
                 // Vérifier si le champ est autofilled OU a une valeur
                 const isFilled = input.matches(':-webkit-autofill') || input.value !== '';
                 label.classList.toggle('label-floated', isFilled);
              };
               input.addEventListener('blur', checkAutofill);
               input.addEventListener('input', checkAutofill);
               setTimeout(checkAutofill, 100); // Vérification initiale
           });
       });
    </script>
</body>
</html>