<?php
require_once 'config.php';

// Redirect to login.php if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

$game_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Fallback for cases where ID might be in the path but not in GET (e.g. MultiViews or specific routing issues)
if (!$game_id) {
    $path_info = $_SERVER['PATH_INFO'] ?? '';
    if (preg_match('/^\/([0-9]+)/', $path_info, $matches)) {
        $game_id = (int)$matches[1];
    }
}

if (!$game_id) {
    header('Location: ' . SITE_URL . '/?error=invalid_game_id');
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$game = null;

try {
     $sql = "SELECT g.*, c.name as console_name, c.logo as console_logo, c.slug as console_slug,
                    AVG(r.rating) as average_rating, COUNT(DISTINCT r.id) as rating_count
             FROM games g
             JOIN consoles c ON g.console_id = c.id
             LEFT JOIN ratings r ON g.id = r.game_id
             WHERE g.id = :game_id
             GROUP BY g.id";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) { header('Location: ' . SITE_URL . '/?error=game_not_found'); exit(); }

     $game['is_favorite'] = false; $game['user_rating'] = null;
     if ($is_logged_in) {
         $stmtFav = $db->prepare("SELECT 1 FROM favorites WHERE user_id = :user_id AND game_id = :game_id LIMIT 1");
         $stmtFav->execute([':user_id' => $user_id, ':game_id' => $game_id]);
         if ($stmtFav->fetchColumn()) $game['is_favorite'] = true;

         $stmtRate = $db->prepare("SELECT rating FROM ratings WHERE user_id = :user_id AND game_id = :game_id LIMIT 1");
         $stmtRate->execute([':user_id' => $user_id, ':game_id' => $game_id]);
         $userRatingResult = $stmtRate->fetch(PDO::FETCH_ASSOC);
         if ($userRatingResult) $game['user_rating'] = $userRatingResult['rating'];
     }
} catch (PDOException $e) { error_log("Game Page Error ID {$game_id}: " . $e->getMessage()); die("Erreur infos jeu."); }

$game_title = htmlspecialchars($game['title'] ?? 'Jeu inconnu');
$game_cover = !empty($game['cover']) ? (strpos($game['cover'], 'http') === 0 ? $game['cover'] : (strpos($game['cover'], '/') === 0 ? SITE_URL . $game['cover'] : SITE_URL . '/' . $game['cover'])) : SITE_URL . '/public/img/default_cover.png';
$game_preview = !empty($game['preview']) ? (strpos($game['preview'], 'http') === 0 ? $game['preview'] : (strpos($game['preview'], '/') === 0 ? SITE_URL . $game['preview'] : SITE_URL . '/' . $game['preview'])) : null;
$game_description = !empty($game['description']) ? nl2br(htmlspecialchars($game['description'])) : '<p class="text-text-secondary italic">Aucune description disponible.</p>';
$game_year = htmlspecialchars($game['year'] ?? 'N/A');
$game_publisher = htmlspecialchars($game['publisher'] ?? 'Inconnu');
$console_name = htmlspecialchars($game['console_name'] ?? 'N/A');
$console_logo = !empty($game['console_logo']) ? (strpos($game['console_logo'], '/') === 0 ? SITE_URL . $game['console_logo'] : (strpos($game['console_logo'], 'http') === 0 ? $game['console_logo'] : SITE_URL . '/' . $game['console_logo'])) : null;
$console_slug = htmlspecialchars($game['console_slug'] ?? '');
$rom_path = !empty($game['rom_path']) ? (strpos($game['rom_path'], 'http') === 0 ? $game['rom_path'] : (strpos($game['rom_path'], '/') === 0 ? SITE_URL . $game['rom_path'] : SITE_URL . '/' . $game['rom_path'])) : '';
$average_rating = $game['average_rating'] ? number_format($game['average_rating'], 1) : 0;
$rating_count = (int)($game['rating_count'] ?? 0);
$is_favorite = $game['is_favorite'] ?? false;
$user_rating = $game['user_rating'] ?? null;
$pageTitle = $game_title . " (" . $console_name . ") - RetroHome";

// Include the template
$template = $themeManager->getTemplate('game');
if ($template) {
    include $template;
} else {
    die("Game template not found.");
}
?>