<?php
require_once '../config.php';

// Vérification des droits d'accès
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'admin') {
    header('HTTP/1.0 403 Forbidden');
    echo "Accès interdit.";
    exit();
}

$game_id = $_GET['id'] ?? null;
if (!$game_id || !is_numeric($game_id)) {
    header('Location: ./');
    exit();
}

// Récupère les infos du jeu (pour supprimer les fichiers)
$stmt = $db->prepare("SELECT title, rom_path, cover, preview, console_id FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if ($game) {
    try {
        $db->beginTransaction();

        // 1. Supprime les contraintes d'intégrité (clés étrangères)
        // Ratings
        $stmtDelRatings = $db->prepare("DELETE FROM ratings WHERE game_id = ?");
        $stmtDelRatings->execute([$game_id]);

        // Favorites
        $stmtDelFavs = $db->prepare("DELETE FROM favorites WHERE game_id = ?");
        $stmtDelFavs->execute([$game_id]);

        // Collection Games
        $stmtDelColl = $db->prepare("DELETE FROM collection_games WHERE game_id = ?");
        $stmtDelColl->execute([$game_id]);

        // User Game Stats (Normalement CASCADE est activé mais on assure le coup si besoin, 
        // ou on laisse faire si on est sûr du schéma. Le schéma SQL montre CASCADE pour stats.)

        // 2. Supprime le jeu de la base de données
        $stmtDelGame = $db->prepare("DELETE FROM games WHERE id = ?");
        $stmtDelGame->execute([$game_id]);

        $db->commit();

        // 3. Suppression des fichiers physiques (seulement si la suppression DB a réussi)
        if ($game['rom_path']) {
            $rom_path = ROMS_PATH . $game['rom_path'];
            if (file_exists($rom_path)) {
                unlink($rom_path);
            }
        }
        if ($game['cover']) {
            $cover_path = ROMS_PATH . $game['cover'];
            if (file_exists($cover_path)) {
                unlink($cover_path);
            }
        }
        if ($game['preview']) {
            $preview_path = ROMS_PATH . $game['preview'];
            if (file_exists($preview_path)) {
                unlink($preview_path);
            }
        }

        // 4. Suppression du dossier du jeu si vide ou spécifique
        $stmtConsole = $db->prepare("SELECT slug FROM consoles WHERE id = ?");
        $stmtConsole->execute([$game['console_id']]);
        $console = $stmtConsole->fetch(PDO::FETCH_ASSOC);

        if ($console && !empty($game['title'])) {
            $gameSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $game['title'])));
            $game_dir = ROMS_PATH . $console['slug'] . '/' . $gameSlug;

            if (is_dir($game_dir)) {
                // Fonction récursive pour supprimer un dossier
                function deleteDirectory($dir) {
                    if (!file_exists($dir)) return true;
                    if (!is_dir($dir)) return unlink($dir);
                    foreach (scandir($dir) as $item) {
                        if ($item == '.' || $item == '..') continue;
                        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
                    }
                    return rmdir($dir);
                }
                deleteDirectory($game_dir);
            }
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error deleting game ID $game_id: " . $e->getMessage());
        // Optionnel: stocker l'erreur en session pour l'afficher
    }
}


// Récupération des paramètres de redirection
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$console_id_filter = isset($_GET['console_id']) ? $_GET['console_id'] : '';

$redirectUrl = "./?page=$page";
if ($search) {
    $redirectUrl .= "&search=" . urlencode($search);
}
if ($console_id_filter) {
    $redirectUrl .= "&console_id=" . urlencode($console_id_filter);
}

header("Location: $redirectUrl"); // Redirige vers la liste des jeux avec les filtres
exit();
