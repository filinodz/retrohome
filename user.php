<?php
/**
 * Public User Profile Page
 * URL: /user/{username}
 */
require_once 'config.php';

// Get username from URL
$username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_SPECIAL_CHARS);

// Fallback for path-based routing
if (!$username) {
    $path_info = $_SERVER['PATH_INFO'] ?? '';
    if (preg_match('/^\/([a-zA-Z0-9_-]+)/', $path_info, $matches)) {
        $username = $matches[1];
    }
}

if (!$username) {
    header('Location: ' . SITE_URL . '/?error=invalid_user');
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;

// Fetch user data
try {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.created_at,
               up.bio, up.profile_picture, up.cover_photo, up.is_public,
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorite_count,
               (SELECT COUNT(*) FROM ratings WHERE user_id = u.id) as rating_count,
               (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as followers_count,
               (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.username = :username
    ");
    $stmt->execute([':username' => $username]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile_user) {
        header('Location: ' . SITE_URL . '/error.php?error=user_not_found');
        exit();
    }

    // Check if profile is public or if it's the owner viewing
    $is_owner = ($current_user_id == $profile_user['id']);
    if (!$profile_user['is_public'] && !$is_owner) {
        header('Location: ' . SITE_URL . '/error.php?error=profile_private');
        exit();
    }

    // Check if current user follows this profile
    $is_following = false;
    if ($is_logged_in && !$is_owner) {
        $followStmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = :follower AND following_id = :following");
        $followStmt->execute([':follower' => $current_user_id, ':following' => $profile_user['id']]);
        $is_following = (bool)$followStmt->fetchColumn();
    }

    // Get user's favorite games
    $favStmt = $db->prepare("
        SELECT g.id, g.title, g.cover, c.name as console_name, c.slug as console_slug
        FROM games g
        JOIN consoles c ON g.console_id = c.id
        JOIN favorites f ON g.id = f.game_id
        WHERE f.user_id = :user_id
        ORDER BY f.created_at DESC
        LIMIT 12
    ");
    $favStmt->execute([':user_id' => $profile_user['id']]);
    $favorite_games = $favStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's posts
    $postsStmt = $db->prepare("
        SELECT p.*, 
               u.username as author_username,
               up.profile_picture as author_avatar,
               g.title as game_title, g.cover as game_cover,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
               (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = :current_user) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN games g ON p.game_id = g.id
        WHERE p.user_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $postsStmt->execute([':user_id' => $profile_user['id'], ':current_user' => $current_user_id ?? 0]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("User Profile Error: " . $e->getMessage());
    die("Erreur lors du chargement du profil.");
}

// Prepare template variables
$profile_picture = $profile_user['profile_picture'] 
    ? (strpos($profile_user['profile_picture'], 'http') === 0 ? $profile_user['profile_picture'] : SITE_URL . '/' . $profile_user['profile_picture'])
    : SITE_URL . '/public/img/default_avatar.png';

$cover_photo = $profile_user['cover_photo']
    ? (strpos($profile_user['cover_photo'], 'http') === 0 ? $profile_user['cover_photo'] : SITE_URL . '/' . $profile_user['cover_photo'])
    : SITE_URL . '/public/img/default_cover.png';

$pageTitle = $profile_user['username'] . " - RetroHome";

// Include theme template
$template = $themeManager->getTemplate('user');
if ($template) {
    include $template;
} else {
    die("User template not found for current theme.");
}
?>
