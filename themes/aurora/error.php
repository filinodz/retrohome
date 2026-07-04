<!DOCTYPE html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
        
        :root {
            --primary: #ff2d55;
            --secondary: #5856d6;
            --surface: rgba(15, 23, 42, 0.8);
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #1e1b4b, #0f172a);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .glass-panel {
            background: var(--surface);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .gradient-text {
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .error-glow {
            position: absolute;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(120px);
            opacity: 0.15;
            z-index: -1;
            animation: pulse 8s infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.2) translate(20px, 20px); }
        }

        .btn-retro {
            background: linear-gradient(135deg, var(--primary), #ef4444);
            box-shadow: 0 4px 15px rgba(255, 45, 85, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-retro:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 45, 85, 0.5);
            filter: brightness(1.1);
        }
    </style>
</head>
<body class="p-4">
    <div class="error-glow"></div>
    
    <div class="w-full max-w-xl animate__animated animate__zoomIn">
        <div class="glass-panel rounded-[2.5rem] p-12 text-center relative overflow-hidden">
            <!-- Decorative Icon -->
            <div class="mb-8 relative inline-block">
                <div class="absolute inset-0 bg-primary blur-3xl opacity-20"></div>
                <div class="relative w-24 h-24 bg-white/5 rounded-3xl border border-white/10 flex items-center justify-center mx-auto text-4xl text-primary animate__animated animate__shakeX animate__infinite animate__slower" style="--animate-duration: 5s">
                    <?php if ($error_code == 'user_not_found'): ?>
                        <i class="fas fa-user-slash"></i>
                    <?php elseif ($error_code == 'profile_private'): ?>
                        <i class="fas fa-lock"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php endif; ?>
                </div>
            </div>

            <h1 class="text-4xl md:text-5xl font-bold mb-6 gradient-text tracking-tight">
                <?= $error_title ?>
            </h1>

            <p class="text-lg text-gray-400 mb-10 leading-relaxed font-light">
                <?= $error_message ?>
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= SITE_URL ?>/" class="btn-retro px-8 py-4 rounded-2xl font-semibold flex items-center justify-center gap-2">
                    <i class="fas fa-home"></i>
                    <?= __('back_home') ?? 'Back Home' ?>
                </a>
                <button onclick="window.history.back()" class="bg-white/5 hover:bg-white/10 border border-white/10 px-8 py-4 rounded-2xl font-semibold transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_btn') ?? 'Go Back' ?>
                </button>
            </div>

            <!-- Footer Graphic -->
            <div class="mt-12 opacity-20 pointer-events-none">
                <div class="h-1 w-32 bg-gradient-to-r from-transparent via-primary to-transparent mx-auto"></div>
            </div>
        </div>
    </div>

    <!-- Background Elements -->
    <div class="fixed top-0 left-0 w-full h-full pointer-events-none z-[-2]">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-secondary blur-[160px] opacity-10"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-primary blur-[160px] opacity-10"></div>
    </div>
</body>
</html>
