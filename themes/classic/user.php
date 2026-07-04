<?php
// themes/classic/user.php
// Public User Profile Page - Modern Social Design
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
    
    <!-- Fonts & Styles -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/public/vendor/fonts/fonts.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="<?= SITE_URL ?>/public/vendor/fontawesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= get_theme_asset('style.css') ?>">
    
    <style>
        :root {
            --primary: #ff2d55;
            --secondary: #fbbf24;
            --bg-dark: #0b0b0f;
            --glass-bg: rgba(22, 22, 30, 0.7);
        }
        
        body {
            background-color: var(--bg-dark);
            color: #f0f0f5;
            font-family: 'Inter', sans-serif;
        }
        
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
        }
        
        .cover-section {
            position: relative;
            height: 280px;
            background-size: cover;
            background-position: center;
            border-radius: 0 0 2rem 2rem;
        }
        
        .cover-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(11,11,15,1) 0%, transparent 60%);
            border-radius: 0 0 2rem 2rem;
        }
        
        .profile-avatar {
            width: 140px;
            height: 140px;
            border: 4px solid var(--primary);
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 30px rgba(255, 45, 85, 0.4);
        }
        
        .stat-card {
            text-align: center;
            padding: 1rem;
        }
        .stat-card .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        .stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
        }
        
        .btn-follow {
            background: linear-gradient(135deg, var(--primary), #ff4081);
            color: #000;
            font-weight: 700;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            transition: all 0.3s ease;
        }
        .btn-follow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(255, 45, 85, 0.5);
        }
        .btn-follow.following {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .game-card-mini {
            transition: transform 0.3s ease;
        }
        .game-card-mini:hover {
            transform: scale(1.05);
        }
        
        .post-card {
            transition: all 0.3s ease;
        }
        .post-card:hover {
            border-color: rgba(255, 45, 85, 0.3);
        }
        
        .like-btn.liked { color: var(--primary); }
    </style>
</head>
<body class="min-h-screen">
    <!-- Cover Photo -->
    <div class="cover-section" style="background-image: url('<?= $cover_photo ?>');">
        <div class="cover-overlay"></div>
    </div>

    <div class="container mx-auto px-6 -mt-20 relative z-10">
        <!-- Profile Header -->
        <div class="flex flex-col md:flex-row items-center md:items-end gap-6 mb-8">
            <img src="<?= $profile_picture ?>" alt="<?= htmlspecialchars($profile_user['username']) ?>" class="profile-avatar">
            
            <div class="flex-grow text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-black text-white mb-2">
                    <?= htmlspecialchars($profile_user['username']) ?>
                </h1>
                <p class="text-gray-400 text-sm">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <?= __('member_since') ?? 'Member since' ?> <?= date('M Y', strtotime($profile_user['created_at'])) ?>
                </p>
            </div>
            
            <div class="flex gap-3">
                <?php if ($is_owner): ?>
                    <a href="<?= SITE_URL ?>/profile" class="btn-follow">
                        <i class="fas fa-edit mr-2"></i><?= __('edit_profile') ?? 'Edit Profile' ?>
                    </a>
                <?php elseif ($is_logged_in): ?>
                    <button id="follow-btn" data-user-id="<?= $profile_user['id'] ?>" 
                            class="btn-follow <?= $is_following ? 'following' : '' ?>">
                        <i class="fas fa-<?= $is_following ? 'user-check' : 'user-plus' ?> mr-2"></i>
                        <span><?= $is_following ? (__('following') ?? 'Following') : (__('follow') ?? 'Follow') ?></span>
                    </button>
                <?php endif; ?>
                
                <button onclick="copyProfileLink()" class="glass-panel px-4 py-2 text-gray-400 hover:text-white transition-colors" title="<?= __('link_copied') ?? 'Copy Link' ?>">
                    <i class="fas fa-link"></i>
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="glass-panel grid grid-cols-4 gap-4 mb-8">
            <div class="stat-card">
                <div class="number"><?= $profile_user['favorite_count'] ?? 0 ?></div>
                <div class="label"><?= __('favorites') ?? 'Favorites' ?></div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $profile_user['rating_count'] ?? 0 ?></div>
                <div class="label"><?= __('ratings') ?? 'Ratings' ?></div>
            </div>
            <div class="stat-card">
                <div class="number" id="followers-count"><?= $profile_user['followers_count'] ?? 0 ?></div>
                <div class="label"><?= __('followers') ?? 'Followers' ?></div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $profile_user['following_count'] ?? 0 ?></div>
                <div class="label"><?= __('following') ?? 'Following' ?></div>
            </div>
        </div>

        <!-- Bio Section -->
        <?php if (!empty($profile_user['bio'])): ?>
        <div class="glass-panel p-6 mb-8">
            <h3 class="text-sm font-bold text-primary uppercase tracking-widest mb-4">
                <i class="fas fa-user mr-2"></i><?= __('about') ?? 'About' ?>
            </h3>
            <p class="text-gray-300 leading-relaxed"><?= nl2br(htmlspecialchars($profile_user['bio'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Favorites -->
            <div class="lg:col-span-1">
                <div class="glass-panel p-6">
                    <h3 class="text-sm font-bold text-secondary uppercase tracking-widest mb-6 flex items-center gap-2">
                        <i class="fas fa-heart"></i> <?= __('favorite_games') ?? 'Favorite Games' ?>
                    </h3>
                    
                    <?php if (empty($favorite_games)): ?>
                        <p class="text-gray-500 text-center py-8"><?= __('no_favorites') ?? 'No favorites yet' ?></p>
                    <?php else: ?>
                        <div class="grid grid-cols-3 gap-3">
                            <?php foreach ($favorite_games as $game): 
                                $gameCover = !empty($game['cover']) ? (strpos($game['cover'], 'http') === 0 ? $game['cover'] : SITE_URL . '/' . ltrim($game['cover'], '/')) : SITE_URL . '/public/img/default_cover.png';
                            ?>
                                <a href="<?= SITE_URL ?>/game/<?= $game['id'] ?>" 
                                   class="game-card-mini block rounded-lg overflow-hidden" 
                                   title="<?= htmlspecialchars($game['title']) ?>">
                                    <img src="<?= $gameCover ?>" alt="<?= htmlspecialchars($game['title']) ?>" 
                                         class="w-full aspect-square object-cover">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Feed -->
            <div class="lg:col-span-2">
                <!-- Create Post (if owner) -->
                <?php if ($is_owner): ?>
                <div class="glass-panel p-6 mb-6">
                    <form id="create-post-form" class="space-y-4">
                        <textarea id="post-content" rows="3" 
                                  class="w-full bg-white/5 border border-white/10 rounded-xl p-4 text-white placeholder-gray-500 focus:border-primary focus:outline-none resize-none"
                                  placeholder="<?= __('whats_on_your_mind') ?? "What's on your mind?" ?>"></textarea>
                        
                        <!-- Currently Playing Selector -->
                        <div id="playing-selector-container" class="hidden animate__animated animate__fadeIn">
                            <label class="block text-xs text-gray-500 mb-2 uppercase tracking-widest"><?= __('now_playing') ?? 'Now Playing' ?></label>
                            <select id="post-game-id" class="w-full bg-white/5 border border-white/10 rounded-lg p-2 text-sm text-gray-300 focus:border-primary outline-none">
                                <option value=""><?= __('select_game') ?? 'Select a game...' ?></option>
                                <!-- Populated by JS -->
                            </select>
                        </div>

                        <div class="flex justify-between items-center">
                            <div class="flex gap-2">
                                <button type="button" id="toggle-playing" class="text-gray-500 hover:text-primary transition-colors p-2" title="<?= __('now_playing') ?? 'Now Playing' ?>">
                                    <i class="fas fa-gamepad"></i>
                                </button>
                                <button type="button" id="upload-post-image" class="text-gray-500 hover:text-primary transition-colors p-2" title="Upload Image">
                                    <i class="fas fa-image"></i>
                                    <input type="file" id="post-image-input" class="hidden" accept="image/*">
                                </button>
                                <input type="hidden" id="post-image-url">
                            </div>
                            <button type="submit" class="btn-follow text-sm px-6 py-2">
                                <i class="fas fa-paper-plane mr-2"></i><?= __('post') ?? 'Post' ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Posts Feed -->
                <div id="posts-feed" class="space-y-6">
                    <?php if (empty($posts)): ?>
                        <div class="glass-panel p-12 text-center">
                            <i class="fas fa-comment-slash text-4xl text-gray-600 mb-4"></i>
                            <p class="text-gray-500"><?= __('no_posts') ?? 'No posts yet' ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): 
                            $authorAvatar = $post['author_avatar'] 
                                ? (strpos($post['author_avatar'], 'http') === 0 ? $post['author_avatar'] : SITE_URL . '/' . $post['author_avatar'])
                                : SITE_URL . '/public/img/default_avatar.png';
                        ?>
                        <div class="post-card glass-panel p-6" data-post-id="<?= $post['id'] ?>">
                            <div class="flex items-start gap-4">
                                <img src="<?= $authorAvatar ?>" alt="" class="w-12 h-12 rounded-full object-cover">
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-white"><?= htmlspecialchars($post['author_username']) ?></span>
                                            <span class="text-gray-500 text-sm"><?= date('M j, Y', strtotime($post['created_at'])) ?></span>
                                        </div>
                                        <?php if ($is_logged_in && $_SESSION['user_id'] == $post['user_id']): ?>
                                        <div class="flex gap-2">
                                            <button class="edit-post-btn text-gray-500 hover:text-primary transition-colors" data-post-id="<?= $post['id'] ?>">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button class="delete-post-btn text-gray-500 hover:text-red-500 transition-colors" data-post-id="<?= $post['id'] ?>">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-300 mb-4"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                    
                                    <?php if ($post['image_url']): ?>
                                        <img src="<?= htmlspecialchars($post['image_url']) ?>" 
                                             alt="" class="rounded-xl mb-4 max-h-96 object-cover">
                                    <?php endif; ?>
                                    
                                    <?php if ($post['game_title']): ?>
                                        <a href="<?= SITE_URL ?>/game/<?= $post['game_id'] ?>" 
                                           class="inline-flex items-center gap-2 bg-white/5 px-3 py-2 rounded-lg text-sm hover:bg-white/10 transition-colors mb-4">
                                            <i class="fas fa-gamepad text-primary"></i>
                                            <?= htmlspecialchars($post['game_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Post Actions -->
                                    <div class="flex items-center gap-6 text-gray-500 border-t border-white/5 pt-4">
                                        <button class="like-btn flex items-center gap-2 hover:text-primary transition-colors <?= $post['user_liked'] ? 'liked' : '' ?>"
                                                data-post-id="<?= $post['id'] ?>">
                                            <i class="<?= $post['user_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                            <span class="like-count font-bold"><?= $post['like_count'] ?></span>
                                        </button>
                                        <button class="comment-btn flex items-center gap-2 hover:text-primary transition-colors"
                                                data-post-id="<?= $post['id'] ?>">
                                            <i class="far fa-comment"></i>
                                            <span class="font-bold"><?= $post['comment_count'] ?></span>
                                        </button>
                                    </div>

                                    <!-- Comments Section (Hidden) -->
                                    <div id="comments-container-<?= $post['id'] ?>" class="hidden mt-6 space-y-4 pt-4 border-t border-white/5">
                                        <div class="comments-list space-y-3" id="comments-list-<?= $post['id'] ?>">
                                            <!-- Comments loaded via JS -->
                                        </div>
                                        
                                        <?php if ($is_logged_in): ?>
                                        <div class="flex gap-3 mt-4">
                                            <img src="<?= $current_user_avatar ?? SITE_URL . '/public/img/default_avatar.png' ?>" class="w-8 h-8 rounded-full">
                                            <div class="flex-grow flex gap-2">
                                                <input type="text" class="comment-input bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm w-full focus:outline-none focus:border-primary" 
                                                       placeholder="Write a comment..." data-post-id="<?= $post['id'] ?>">
                                                <button class="send-comment-btn text-primary p-2" data-post-id="<?= $post['id'] ?>">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Home -->
    <div class="container mx-auto px-6 py-12">
        <a href="<?= SITE_URL ?>/" class="inline-flex items-center gap-2 text-gray-500 hover:text-primary transition-colors">
            <i class="fas fa-arrow-left"></i>
            <?= __('back_home') ?? 'Back to Home' ?>
        </a>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const SITE_URL = "<?= SITE_URL ?>";
        const PROFILE_USER_ID = <?= $profile_user['id'] ?>;
        const IS_LOGGED_IN = <?= $is_logged_in ? 'true' : 'false' ?>;
        const CURRENT_USER_ID = <?= $_SESSION['user_id'] ?? 0 ?>;
        const USERNAME = "<?= htmlspecialchars($profile_user['username']) ?>";
        
        // Copy profile link
        function copyProfileLink() {
            const publicUrl = `${SITE_URL}/user/${USERNAME}`;
            navigator.clipboard.writeText(publicUrl).then(() => {
                alert('<?= __('link_copied') ?? 'Profile link copied!' ?>');
            });
        }
        
        // Follow/Unfollow
        const followBtn = document.getElementById('follow-btn');
        if (followBtn) {
            followBtn.addEventListener('click', async function() {
                const isFollowing = this.classList.contains('following');
                const action = isFollowing ? 'unfollowUser' : 'followUser';
                
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${PROFILE_USER_ID}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    
                    this.classList.toggle('following');
                    const icon = this.querySelector('i');
                    const text = this.querySelector('span');
                    
                    if (this.classList.contains('following')) {
                        icon.className = 'fas fa-user-check mr-2';
                        text.textContent = '<?= __('following') ?? 'Following' ?>';
                        document.getElementById('followers-count').textContent = 
                            parseInt(document.getElementById('followers-count').textContent) + 1;
                    } else {
                        icon.className = 'fas fa-user-plus mr-2';
                        text.textContent = '<?= __('follow') ?? 'Follow' ?>';
                        document.getElementById('followers-count').textContent = 
                            Math.max(0, parseInt(document.getElementById('followers-count').textContent) - 1);
                    }
                } catch (error) {
                    console.error(error);
                    alert('Error: ' + error.message);
                }
            });
        }
        
        // Post Actions (Edit/Delete)
        document.querySelectorAll('.delete-post-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to delete this post?')) return;
                const postId = this.dataset.postId;
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=deletePost`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `post_id=${postId}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    document.querySelector(`.post-card[data-post-id="${postId}"]`).remove();
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            });
        });

        document.querySelectorAll('.edit-post-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const postContent = document.querySelector(`.post-card[data-post-id="${postId}"] p.text-gray-300`);
                const originalText = postContent.innerText;
                const newText = prompt('Edit your post:', originalText);
                
                if (newText && newText !== originalText) {
                    saveEditPost(postId, newText, postContent);
                }
            });
        });

        async function saveEditPost(postId, content, element) {
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=editPost`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${postId}&content=${encodeURIComponent(content)}`
                });
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                element.innerText = content;
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        // Like/Unlike Posts
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!IS_LOGGED_IN) {
                    window.location.href = `${SITE_URL}/login`;
                    return;
                }
                
                const postId = this.dataset.postId;
                const isLiked = this.classList.contains('liked');
                const action = isLiked ? 'unlikePost' : 'likePost';
                
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `post_id=${postId}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    
                    this.classList.toggle('liked');
                    const icon = this.querySelector('i');
                    icon.className = this.classList.contains('liked') ? 'fas fa-heart' : 'far fa-heart';
                    this.querySelector('.like-count').textContent = data.like_count;
                } catch (error) {
                    console.error(error);
                }
            });
        });

        // Toggle Playing Selector
        const togglePlaying = document.getElementById('toggle-playing');
        if (togglePlaying) {
            togglePlaying.addEventListener('click', async () => {
                const container = document.getElementById('playing-selector-container');
                container.classList.toggle('hidden');
                
                if (!container.classList.contains('hidden')) {
                    const select = document.getElementById('post-game-id');
                    if (select.options.length <= 1) {
                        try {
                            const response = await fetch(`${SITE_URL}/api.php?action=getGames`); // Standardized to api.php
                            const games = await response.json();
                            games.forEach(game => {
                                const opt = document.createElement('option');
                                opt.value = game.id;
                                opt.textContent = game.title + ' (' + game.console_name + ')';
                                select.appendChild(opt);
                            });
                        } catch (e) {
                            console.error('Failed to load games', e);
                        }
                    }
                }
            });
        }

        // Image Upload for Posts
        const imageUploadBtn = document.getElementById('upload-post-image');
        if (imageUploadBtn) {
            imageUploadBtn.addEventListener('click', () => document.getElementById('post-image-input').click());
            document.getElementById('post-image-input').addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const originalIcon = imageUploadBtn.innerHTML;
                imageUploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                try {
                    const formData = new FormData();
                    formData.append('image', file);
                    const response = await fetch('https://api.imgbb.com/1/upload?key=f0111b3eff47b1a5baf6d1aee47504d3', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        document.getElementById('post-image-url').value = data.data.url;
                        imageUploadBtn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                    } else {
                        throw new Error('Upload failed');
                    }
                } catch (err) {
                    alert('Upload failed');
                    imageUploadBtn.innerHTML = originalIcon;
                }
            });
        }
        
        // Create Post update
        const createPostForm = document.getElementById('create-post-form');
        if (createPostForm) {
            createPostForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const content = document.getElementById('post-content').value.trim();
                const gameId = document.getElementById('post-game-id')?.value || '';
                const imageUrl = document.getElementById('post-image-url').value;
                if (!content) return;

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>...';
                
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=createPost`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `content=${encodeURIComponent(content)}&game_id=${gameId}&image_url=${encodeURIComponent(imageUrl)}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    location.reload();
                } catch (error) {
                    console.error(error);
                    alert('Error: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // Comments Logic
        document.querySelectorAll('.comment-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                const container = document.getElementById(`comments-container-${postId}`);
                container.classList.toggle('hidden');
                
                if (!container.classList.contains('hidden')) {
                    loadComments(postId);
                }
            });
        });

        async function loadComments(postId) {
            const list = document.getElementById(`comments-list-${postId}`);
            list.innerHTML = '<p class="text-xs text-gray-500 italic">Loading comments...</p>';
            
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=getComments&post_id=${postId}`);
                const comments = await response.json();
                
                if (comments.length === 0) {
                    list.innerHTML = '<p class="text-xs text-gray-500 italic">No comments yet.</p>';
                    return;
                }
                
                list.innerHTML = comments.map(c => `
                    <div class="comment-item flex gap-3 bg-white/5 p-3 rounded-lg" data-comment-id="${c.id}">
                        <img src="${c.profile_picture ? (c.profile_picture.startsWith('http') ? c.profile_picture : SITE_URL + '/' + c.profile_picture) : SITE_URL + '/public/img/default_avatar.png'}" class="w-8 h-8 rounded-full object-cover">
                        <div class="flex-grow">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-white">${c.username}</span>
                                    <span class="text-[10px] text-gray-500">${new Date(c.created_at).toLocaleDateString()}</span>
                                </div>
                                ${CURRENT_USER_ID == c.user_id ? `
                                <div class="flex gap-2">
                                    <button class="edit-comment-btn text-gray-500 hover:text-primary transition-colors" onclick="editComment(${c.id}, ${postId})">
                                        <i class="fas fa-edit text-[10px]"></i>
                                    </button>
                                    <button class="delete-comment-btn text-gray-500 hover:text-red-500 transition-colors" onclick="deleteComment(${c.id}, ${postId})">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </div>
                                ` : ''}
                            </div>
                            <p class="comment-content text-sm text-gray-400">${c.content}</p>
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                list.innerHTML = '<p class="text-xs text-red-500 italic">Failed to load comments.</p>';
            }
        }

        async function deleteComment(commentId, postId) {
            if (!confirm('Delete this comment?')) return;
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=deleteComment`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `comment_id=${commentId}`
                });
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                
                document.querySelector(`.comment-item[data-comment-id="${commentId}"]`).remove();
                const btnCount = document.querySelector(`.comment-btn[data-post-id="${postId}"] span:last-child`);
                btnCount.textContent = Math.max(0, parseInt(btnCount.textContent) - 1);
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function editComment(commentId, postId) {
            const commentContent = document.querySelector(`.comment-item[data-comment-id="${commentId}"] .comment-content`);
            const originalText = commentContent.innerText;
            const newText = prompt('Edit your comment:', originalText);
            
            if (newText && newText !== originalText) {
                saveEditComment(commentId, newText, commentContent);
            }
        }

        async function saveEditComment(commentId, content, element) {
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=editComment`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `comment_id=${commentId}&content=${encodeURIComponent(content)}`
                });
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                element.innerText = content;
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        document.querySelectorAll('.send-comment-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                const input = document.querySelector(`.comment-input[data-post-id="${postId}"]`);
                const content = input.value.trim();
                if (!content) return;
                
                this.disabled = true;
                try {
                    const response = await fetch(`${SITE_URL}/api.php?action=addComment`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `post_id=${postId}&content=${encodeURIComponent(content)}`
                    });
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);
                    input.value = '';
                    loadComments(postId);
                    // Update count
                    const btnCount = document.querySelector(`.comment-btn[data-post-id="${postId}"] span:last-child`);
                    btnCount.textContent = parseInt(btnCount.textContent) + 1;
                } catch (e) {
                    alert('Failed to add comment');
                } finally {
                    this.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
