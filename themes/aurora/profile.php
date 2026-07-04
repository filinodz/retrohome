<?php
// themes/classic/profile.php - Enhanced with Profile Editing & Social Features
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <?php if ($isRTL): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Cairo', sans-serif !important; }</style>
    <?php endif; ?>
    <meta charset="UTF-8">
    <title><?= __('profile') ?> - RetroHome</title>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/public/img/logo_new.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & CSS -->
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #ff4081);
            color: #fff;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(255, 45, 85, 0.5);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border: 3px solid var(--primary);
            border-radius: 50%;
            object-fit: cover;
        }
        
        .form-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            color: #fff;
            padding: 0.75rem 1rem;
            width: 100%;
            transition: border-color 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .collection-card {
            background: var(--glass-bg);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .collection-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .modal-overlay {
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
        }
        
        .favorite-game-card {
            background: var(--glass-bg);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .favorite-game-card:hover {
            border-color: var(--primary);
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <header class="glass-panel m-6 p-4 flex items-center justify-between">
        <a href="<?= SITE_URL ?>/" class="flex items-center gap-3 text-white hover:text-primary transition-colors">
            <i class="fas fa-arrow-left"></i>
            <span class="font-bold"><?= __('back_home') ?? 'Back Home' ?></span>
        </a>
        <h1 class="text-xl font-bold"><?= __('my_profile') ?? 'My Profile' ?></h1>
        <div class="flex gap-3">
            <a href="<?= SITE_URL ?>/user/<?= htmlspecialchars($username) ?>" class="text-gray-400 hover:text-primary transition-colors" title="<?= __('view_public_profile') ?? 'View Public Profile' ?>">
                <i class="fas fa-external-link-alt"></i>
            </a>
            <button onclick="copyProfileLink()" class="text-gray-400 hover:text-primary transition-colors" title="<?= __('link_copied') ?? 'Copy Link' ?>">
                <i class="fas fa-link"></i>
            </button>
        </div>
    </header>

    <div class="container mx-auto px-6 pb-12">
        <!-- Profile Edit Section -->
        <div class="glass-panel p-8 mb-8">
            <h2 class="text-lg font-bold mb-6 text-primary uppercase tracking-wider">
                <i class="fas fa-user-edit mr-2"></i><?= __('edit_profile') ?? 'Edit Profile' ?>
            </h2>
            
            <form id="profile-edit-form" class="space-y-6">
                <div class="flex flex-col md:flex-row gap-8">
                    <!-- Avatar Section -->
                    <div class="flex flex-col items-center gap-4">
                        <img id="profile-avatar-preview" src="<?= SITE_URL ?>/public/img/default_avatar.png" alt="Avatar" class="profile-avatar">
                        <label class="btn-primary text-sm cursor-pointer">
                            <i class="fas fa-camera mr-2"></i><?= __('change_photo') ?? 'Change Photo' ?>
                            <input type="file" id="profile-avatar-input" accept="image/*" class="hidden">
                        </label>
                        <input type="hidden" id="profile-picture-url" name="profile_picture">
                    </div>
                    
                    <!-- Form Fields -->
                    <div class="flex-grow space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2"><?= __('username') ?? 'Username' ?></label>
                            <input type="text" value="<?= htmlspecialchars($username) ?>" class="form-input" disabled>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2"><?= __('email') ?? 'Email' ?></label>
                            <input type="email" id="profile-email" class="form-input" disabled>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2"><?= __('bio') ?? 'Bio' ?></label>
                            <textarea id="profile-bio" rows="3" class="form-input resize-none" placeholder="<?= __('bio_placeholder') ?? 'Tell us about yourself...' ?>"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Cover Photo -->
                <div>
                    <label class="block text-sm text-gray-400 mb-2"><?= __('cover_photo') ?? 'Cover Photo' ?></label>
                    <div class="relative rounded-xl overflow-hidden h-32 bg-gradient-to-r from-primary/20 to-secondary/20">
                        <img id="cover-preview" src="" alt="" class="w-full h-full object-cover hidden">
                        <label class="absolute inset-0 flex items-center justify-center cursor-pointer hover:bg-black/30 transition-colors">
                            <span class="bg-black/50 px-4 py-2 rounded-lg"><i class="fas fa-image mr-2"></i><?= __('upload_cover') ?? 'Upload Cover' ?></span>
                            <input type="file" id="cover-input" accept="image/*" class="hidden">
                        </label>
                    </div>
                    <input type="hidden" id="cover-photo-url" name="cover_photo">
                </div>
                
                <!-- Privacy -->
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="profile-public" checked class="w-5 h-5 accent-primary">
                    <label for="profile-public" class="text-sm text-gray-300"><?= __('public_profile') ?? 'Make my profile public' ?></label>
                </div>
                
                <button type="submit" class="btn-primary w-full md:w-auto">
                    <i class="fas fa-save mr-2"></i><?= __('save_changes') ?? 'Save Changes' ?>
                </button>
            </form>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="glass-panel p-6 text-center">
                <div id="profile-favorite-count" class="text-3xl font-bold text-primary">0</div>
                <div class="text-xs text-gray-400 uppercase tracking-wider"><?= __('favorites') ?? 'Favorites' ?></div>
            </div>
            <div class="glass-panel p-6 text-center">
                <div id="profile-rating-count" class="text-3xl font-bold text-secondary">0</div>
                <div class="text-xs text-gray-400 uppercase tracking-wider"><?= __('ratings') ?? 'Ratings' ?></div>
            </div>
            <div class="glass-panel p-6 text-center">
                <div id="profile-followers-count" class="text-3xl font-bold text-green-400">0</div>
                <div class="text-xs text-gray-400 uppercase tracking-wider"><?= __('followers') ?? 'Followers' ?></div>
            </div>
            <div class="glass-panel p-6 text-center">
                <div id="profile-following-count" class="text-3xl font-bold text-blue-400">0</div>
                <div class="text-xs text-gray-400 uppercase tracking-wider"><?= __('following') ?? 'Following' ?></div>
            </div>
        </div>

        <!-- Collections Section -->
        <div class="glass-panel p-8 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-secondary uppercase tracking-wider">
                    <i class="fas fa-folder-open mr-2"></i><?= __('my_collections') ?? 'My Collections' ?>
                </h2>
                <button id="create-collection-btn" class="btn-primary text-sm">
                    <i class="fas fa-plus mr-2"></i><?= __('create') ?? 'Create' ?>
                </button>
            </div>
            <div id="collections-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Loaded by JS -->
            </div>
        </div>

        <!-- Favorites Section -->
        <div class="glass-panel p-8">
            <h2 class="text-lg font-bold text-primary uppercase tracking-wider mb-6">
                <i class="fas fa-heart mr-2"></i><?= __('my_favorites') ?? 'My Favorites' ?>
            </h2>
            <div id="profile-favorites" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <!-- Loaded by JS -->
            </div>
        </div>
    </div>

    <!-- Collection Modal -->
    <div id="collection-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center modal-overlay">
        <div class="glass-panel p-8 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold mb-6 text-primary"><?= __('create_collection') ?? 'Create Collection' ?></h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-2"><?= __('collection_name') ?? 'Collection Name' ?></label>
                    <input type="text" id="collection-name" class="form-input" placeholder="<?= __('collection_name_placeholder') ?? 'e.g., My Favorites' ?>">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-2"><?= __('description') ?? 'Description' ?></label>
                    <textarea id="collection-description" rows="3" class="form-input resize-none" placeholder="<?= __('collection_desc_placeholder') ?? 'Optional description...' ?>"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button id="cancel-collection-btn" class="flex-1 py-3 rounded-lg bg-gray-700 hover:bg-gray-600 transition-colors">
                    <?= __('cancel') ?? 'Cancel' ?>
                </button>
                <button id="save-collection-btn" class="flex-1 btn-primary">
                    <?= __('create') ?? 'Create' ?>
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        const SITE_URL = "<?= SITE_URL ?>";
        const USERNAME = "<?= htmlspecialchars($username) ?>";
        
        document.addEventListener('DOMContentLoaded', () => {
            loadProfileData();
            loadFavorites();
            loadCollections();
            
            // Modal handlers
            document.getElementById('create-collection-btn').addEventListener('click', () => {
                document.getElementById('collection-modal').classList.remove('hidden');
            });
            document.getElementById('cancel-collection-btn').addEventListener('click', () => {
                document.getElementById('collection-modal').classList.add('hidden');
            });
            document.getElementById('save-collection-btn').addEventListener('click', createCollection);
            
            // Profile form
            document.getElementById('profile-edit-form').addEventListener('submit', saveProfile);
            
            // Avatar upload
            document.getElementById('profile-avatar-input').addEventListener('change', handleAvatarUpload);
            document.getElementById('cover-input').addEventListener('change', handleCoverUpload);
        });
        
        async function loadProfileData() {
            try {
                const response = await fetch(`${SITE_URL}/api?action=getProfile`);
                const data = await response.json();
                
                document.getElementById('profile-email').value = data.email || '';
                document.getElementById('profile-favorite-count').textContent = data.favorite_count || 0;
                document.getElementById('profile-rating-count').textContent = data.rating_count || 0;
                
                // Load extended profile
                const profileResponse = await fetch(`${SITE_URL}/api?action=getPublicProfile&username=${USERNAME}`);
                const profileData = await profileResponse.json();
                
                if (profileData.bio) {
                    document.getElementById('profile-bio').value = profileData.bio;
                }
                if (profileData.profile_picture) {
                    const avatarUrl = profileData.profile_picture.startsWith('http') 
                        ? profileData.profile_picture 
                        : SITE_URL + '/' + profileData.profile_picture;
                    document.getElementById('profile-avatar-preview').src = avatarUrl;
                    document.getElementById('profile-picture-url').value = profileData.profile_picture;
                }
                if (profileData.cover_photo) {
                    const coverUrl = profileData.cover_photo.startsWith('http') 
                        ? profileData.cover_photo 
                        : SITE_URL + '/' + profileData.cover_photo;
                    document.getElementById('cover-preview').src = coverUrl;
                    document.getElementById('cover-preview').classList.remove('hidden');
                    document.getElementById('cover-photo-url').value = profileData.cover_photo;
                }
                document.getElementById('profile-public').checked = profileData.is_public !== '0';
                document.getElementById('profile-followers-count').textContent = profileData.followers_count || 0;
                document.getElementById('profile-following-count').textContent = profileData.following_count || 0;
                
            } catch (error) {
                console.error('Error loading profile:', error);
            }
        }
        
        async function saveProfile(e) {
            e.preventDefault();
            
            const bio = document.getElementById('profile-bio').value;
            const profilePicture = document.getElementById('profile-picture-url').value;
            const coverPhoto = document.getElementById('cover-photo-url').value;
            const isPublic = document.getElementById('profile-public').checked ? 1 : 0;
            
            try {
                const response = await fetch(`${SITE_URL}/api?action=updateProfile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `bio=${encodeURIComponent(bio)}&profile_picture=${encodeURIComponent(profilePicture)}&cover_photo=${encodeURIComponent(coverPhoto)}&is_public=${isPublic}`
                });
                const data = await response.json();
                
                if (data.error) {
                    alert(data.error);
                } else {
                    alert('<?= __('profile_saved') ?? 'Profile saved!' ?>');
                }
            } catch (error) {
                console.error('Error saving profile:', error);
                alert('Error saving profile');
            }
        }
        
        async function uploadToImgBB(file) {
            const formData = new FormData();
            formData.append('image', file);
            
            try {
                const response = await fetch('https://api.imgbb.com/1/upload?key=f0111b3eff47b1a5baf6d1aee47504d3', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    return data.data.url;
                }
                throw new Error('ImgBB upload failed');
            } catch (error) {
                console.error('Upload error:', error);
                throw error;
            }
        }

        async function handleAvatarUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const label = e.target.parentElement;
            const originalIcon = label.innerHTML;
            label.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
            label.style.pointerEvents = 'none';

            try {
                const url = await uploadToImgBB(file);
                document.getElementById('profile-avatar-preview').src = url;
                document.getElementById('profile-picture-url').value = url;
                label.innerHTML = '<i class="fas fa-check mr-2"></i>Done';
            } catch (error) {
                alert('Failed to upload image');
                label.innerHTML = originalIcon;
            } finally {
                label.style.pointerEvents = 'auto';
                setTimeout(() => { label.innerHTML = originalIcon; }, 2000);
            }
        }
        
        async function handleCoverUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            const label = e.target.parentElement;
            const originalText = label.innerHTML;
            label.innerHTML = '<span class="bg-black/50 px-4 py-2 rounded-lg"><i class="fas fa-spinner fa-spin mr-2"></i>Uploading...</span>';
            label.style.pointerEvents = 'none';
            
            try {
                const url = await uploadToImgBB(file);
                document.getElementById('cover-preview').src = url;
                document.getElementById('cover-preview').classList.remove('hidden');
                document.getElementById('cover-photo-url').value = url;
                label.innerHTML = '<span class="bg-black/50 px-4 py-2 rounded-lg"><i class="fas fa-check mr-2"></i>Done</span>';
            } catch (error) {
                alert('Failed to upload cover');
                label.innerHTML = originalText;
            } finally {
                label.style.pointerEvents = 'auto';
                setTimeout(() => { label.innerHTML = originalText; }, 2000);
            }
        }
        
        function getAssetUrl(path) {
            if (!path) return SITE_URL + '/public/img/default_cover.png';
            if (path.startsWith('http') || path.startsWith('data:')) return path;
            return SITE_URL + '/' + path.replace(/^\//, '');
        }
        
        async function loadFavorites() {
            const container = document.getElementById('profile-favorites');
            try {
                const response = await fetch(`${SITE_URL}/api?action=getFavorites`);
                const games = await response.json();
                
                if (games.length === 0) {
                    container.innerHTML = '<p class="col-span-full text-center text-gray-500 py-8"><?= __('no_favorites') ?? 'No favorites yet' ?></p>';
                    return;
                }
                
                container.innerHTML = games.map(game => `
                    <a href="${SITE_URL}/game/${game.id}" class="favorite-game-card block">
                        <img src="${getAssetUrl(game.cover)}" alt="${game.title}" class="w-full aspect-[3/4] object-cover">
                        <div class="p-3">
                            <h4 class="text-sm font-bold truncate">${game.title}</h4>
                            <p class="text-xs text-gray-500">${game.console_name}</p>
                        </div>
                    </a>
                `).join('');
                
            } catch (error) {
                console.error('Error loading favorites:', error);
            }
        }
        
        async function loadCollections() {
            const container = document.getElementById('collections-container');
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=getCollections`);
                const collections = await response.json();
                
                if (collections.length === 0) {
                    container.innerHTML = '<p class="col-span-full text-center text-gray-500 py-8"><?= __('no_collections') ?? 'No collections yet. Create one!' ?></p>';
                    return;
                }
                
                container.innerHTML = collections.map(col => `
                    <div class="collection-card" onclick="window.location.href='${SITE_URL}/collection?id=${col.id}'">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-bold text-white">${col.name}</h3>
                            <button onclick="event.stopPropagation(); deleteCollection(${col.id})" class="text-gray-500 hover:text-red-500">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <p class="text-sm text-gray-400">${col.description || ''}</p>
                    </div>
                `).join('');
                
            } catch (error) {
                console.error('Error loading collections:', error);
            }
        }
        
        async function createCollection() {
            const name = document.getElementById('collection-name').value.trim();
            const description = document.getElementById('collection-description').value.trim();
            
            if (!name) {
                alert('<?= __('enter_name') ?? 'Please enter a name' ?>');
                return;
            }
            
            try {
                const response = await fetch(`${SITE_URL}/api?action=createCollection`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
                });
                
                if (response.ok) {
                    document.getElementById('collection-modal').classList.add('hidden');
                    document.getElementById('collection-name').value = '';
                    document.getElementById('collection-description').value = '';
                    loadCollections();
                } else {
                    const data = await response.json();
                    alert(data.error || 'Error creating collection');
                }
            } catch (error) {
                console.error('Error creating collection:', error);
            }
        }
        
        async function deleteCollection(id) {
            if (!confirm('<?= __('confirm_delete') ?? 'Are you sure?' ?>')) return;
            
            try {
                const response = await fetch(`${SITE_URL}/api.php?action=deleteCollection`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `collection_id=${id}`
                });
                
                if (response.ok) {
                    loadCollections();
                }
            } catch (error) {
                console.error('Error deleting collection:', error);
            }
        }
        // Copy profile link
        function copyProfileLink() {
            const publicUrl = `${SITE_URL}/user/${USERNAME}`;
            navigator.clipboard.writeText(publicUrl).then(() => {
                alert('<?= __('link_copied') ?? 'Profile link copied!' ?>');
            });
        }
    </script>
</body>
</html>
