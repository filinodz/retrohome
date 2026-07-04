<?php
require_once 'config.php';

// Redirect to login.php if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit();
}

$collectionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Valider l'ID
$userId = $_SESSION['user_id'];

if (!$collectionId) {
    header('Location: profile'); // Rediriger si pas d'ID valide
    exit();
}

// --- Récupérer les infos de la collection pour vérifier l'appartenance et afficher le titre ---
$collectionInfo = null;
$pageTitle = "Collection"; // Titre par défaut
$collectionDescription = ""; // Description par défaut
try {
    $stmt = $db->prepare("SELECT name, description, user_id FROM collections WHERE id = ?");
    $stmt->execute([$collectionId]);
    $collectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérifier si la collection existe et appartient à l'utilisateur connecté
    if (!$collectionInfo || $collectionInfo['user_id'] != $userId) {
         // Rediriger vers le profil si la collection n'est pas trouvée ou n'appartient pas à l'utilisateur
         $_SESSION['profile_flash_message'] = ['type' => 'error', 'message' => 'Collection non trouvée ou accès non autorisé.'];
         header('Location: profile');
         exit();
    }
    $pageTitle = "Collection : " . htmlspecialchars($collectionInfo['name']);
    $collectionDescription = htmlspecialchars($collectionInfo['description'] ?? '');

} catch (PDOException $e) {
    error_log("Error fetching collection info for ID {$collectionId}: " . $e->getMessage());
    // Afficher une erreur ou rediriger ? Pour l'instant, on continue, le titre sera générique.
     $pageTitle = "Erreur Collection";
}

// Include the template
$template = $themeManager->getTemplate('collection');
if ($template) {
    include $template;
} else {
    die("Collection template not found.");
}
?>