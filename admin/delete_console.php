<?php
require_once '../config.php';

// Vérification des droits d'accès
// ... (copier/coller) ...
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
$console_id = $_GET['id'] ?? null;
if (!$console_id || !is_numeric($console_id)) {
    header('Location: index.php');
    exit();
}

// Récupère les infos de la console (pour supprimer le logo)
$stmt = $db->prepare("SELECT logo, slug FROM consoles WHERE id = ?");
$stmt->execute([$console_id]);
$console = $stmt->fetch(PDO::FETCH_ASSOC);

if ($console) {
    // Supprime le logo
    if ($console['logo']) {
       $logo_path = ROMS_PATH . $console['logo'];
        if (file_exists($logo_path)) {
            unlink($logo_path);
        }
    }

    // Supprime le dossier de la console et tout son contenu
    $console_dir = ROMS_PATH . $console['slug'];

    function deleteDirectory($dir) {
       if (!file_exists($dir)) {  return true; }
       if (!is_dir($dir)) {  return unlink($dir);}
        foreach (scandir($dir) as $item) {
           if ($item == '.' || $item == '..') { continue;  }
           if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) { return false; }
        }
       return rmdir($dir);
    }

    if(is_dir($console_dir)){
       deleteDirectory($console_dir);
    }

    // Supprime la console de la base de données
    $stmt = $db->prepare("DELETE FROM consoles WHERE id = ?");
    $stmt->execute([$console_id]);
}

header('Location: index.php');
exit();