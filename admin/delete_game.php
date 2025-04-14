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
    header('Location: index.php');
    exit();
}

// Récupère les infos du jeu (pour supprimer les fichiers)
$stmt = $db->prepare("SELECT rom_path, cover, preview, console_id FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if ($game) {
    // Supprime les fichiers (ROM, cover, preview)
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
      // Récupère le slug de la console pour supprimer le dossier du jeu
     $stmt = $db->prepare("SELECT slug FROM consoles WHERE id = ?");
     $stmt->execute([$game['console_id']]);
     $console = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($console) {
        $gameSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $game['title'])));
        $game_dir = ROMS_PATH . $console['slug'] . '/' . $gameSlug;

          // Fonction récursive pour supprimer un dossier et son contenu
         function deleteDirectory($dir) {
            if (!file_exists($dir)) {  return true; }
            if (!is_dir($dir)) {  return unlink($dir);}
             // ... (suite de delete_game.php) ...
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') { continue;  }
                if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) { return false; }
            }
            return rmdir($dir);
         }

          //Supprimer le dossier du jeu
          if(is_dir($game_dir)){
             deleteDirectory($game_dir);
          }
      }


    // Supprime le jeu de la base de données
    $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
}

header('Location: index.php'); // Redirige vers la liste des jeux
exit();