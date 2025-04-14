<?php
require_once '../config.php'; // Chemin relatif vers config.php

// Vérifie si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); // Redirige vers la page de connexion principale
    exit();
}

// Récupère l'utilisateur (pour vérifier le rôle et afficher le nom)
$stmtUser = $db->prepare("SELECT username, role FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    // Interdit l'accès si pas admin (on pourrait rediriger ou afficher un message plus sympa)
    header('Location: ../index.php?error=unauthorized'); // Redirige vers l'accueil avec un message (optionnel)
    // Ou afficher une page d'erreur dédiée
    // header('HTTP/1.0 403 Forbidden');
    // echo "Accès interdit.";
    exit();
}

// Récupère la liste des jeux
try {
    $stmtGames = $db->query("SELECT g.id, g.title, c.name as console_name FROM games g JOIN consoles c ON g.console_id = c.id ORDER BY g.title");
    $games = $stmtGames->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Gérer l'erreur de base de données (log, message, etc.)
    error_log("Admin Error fetching games: " . $e->getMessage());
    $games = []; // Tableau vide pour éviter les erreurs PHP plus bas
    $dbError = "Erreur lors de la récupération des jeux.";
}


// Récupère la liste des consoles
try {
    $stmtConsoles = $db->query("SELECT * FROM consoles ORDER BY name");
    $consoles = $stmtConsoles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Error fetching consoles: " . $e->getMessage());
    $consoles = [];
    $dbError = isset($dbError) ? $dbError . " Erreur consoles." : "Erreur lors de la récupération des consoles.";
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - RetroHome</title>
    <!-- Favicon (Utiliser le même que le site principal) -->
    <link rel="icon" type="image/png" href="../assets/img/playstation.png"> <!-- Chemin relatif vers l'asset -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome via CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Google Fonts (si utilisées dans style.css principal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;700&family=Press+Start+2P&display=swap" rel="stylesheet"> <!-- Ajout Press Start 2P -->

    <!-- CSS Principal (pour les variables et styles de base) -->
    <link rel="stylesheet" href="../public/style.css"> <!-- Chemin relatif vers le CSS public -->
    <!-- CSS Spécifique Admin -->
    <link rel="stylesheet" href="admin_style.css"> <!-- Nouveau fichier CSS pour l'admin -->
    <style>
        /* Délais animations */
        .animate__delay-100ms { animation-delay: 0.1s; }
        .animate__delay-200ms { animation-delay: 0.2s; }
        .animate__delay-300ms { animation-delay: 0.3s; }
    </style>
</head>
<body class="bg-background text-text-primary font-body">

    <div class="admin-container mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <header class="admin-header flex flex-col sm:flex-row justify-between items-center mb-10 pb-4 border-b border-border-color">
            <div class="flex items-center mb-4 sm:mb-0">
                <img src="../public/img/logo.png" alt="RetroHome Logo" class="h-12 md:h-16 mr-4"> <!-- Chemin relatif et taille réduite -->
                <h1 class="text-2xl md:text-3xl font-bold text-primary pixel-font">Administration</h1>
            </div>
            <nav class="admin-nav flex flex-col sm:flex-row items-center gap-4">
                <span class="text-text-secondary text-sm hidden md:inline">
                    Connecté en tant que: <strong class="text-text-primary"><?= htmlspecialchars($user['username']) ?></strong>
                </span>
                <a href="../index.php" class="admin-button back-button">
                    <i class="fas fa-arrow-left mr-2"></i>Retour au site
                </a>
                <a href="../logout.php" class="admin-button logout-button"> <!-- Pointer vers un script logout à la racine ? -->
                    <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                </a>
            </nav>
        </header>

         <?php if (isset($dbError)): ?>
            <div class="bg-red-800 border border-red-600 text-red-100 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Erreur!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($dbError) ?></span>
            </div>
        <?php endif; ?>

        <main class="admin-main-content grid grid-cols-1 lg:grid-cols-2 gap-10">

            <!-- Section Jeux -->
            <section class="admin-section animate__animated animate__fadeInUp animate__delay-100ms">
                <div class="section-header">
                    <h2 class="section-title">Gestion des Jeux</h2>
                    <a href="add_game.php" class="admin-button add-button" title="Ajouter un jeu">
                        <i class="fas fa-plus"></i><span class="hidden sm:inline ml-2">Ajouter un jeu (manuel)</span>
                    </a>
                    <a href="add_game_auto.php" class="admin-button add-button" title="Ajouter un jeu">
                        <i class="fas fa-plus"></i><span class="hidden sm:inline ml-2">Ajouter un jeu (auto)</span>
                    </a>
                    <a href="add_game_auto_bulk.php" class="admin-button add-button" title="Ajouter un jeu">
                        <i class="fas fa-plus"></i><span class="hidden sm:inline ml-2">Ajouter des jeux en masse (auto)</span>
                    </a>
                </div>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titre</th>
                                <th>Console</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($games)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-text-secondary py-4">Aucun jeu trouvé.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($games as $game): ?>
                                <tr>
                                    <td><?= $game['id'] ?></td>
                                    <td><?= htmlspecialchars($game['title']) ?></td>
                                    <td><?= htmlspecialchars($game['console_name']) ?></td>
                                    <td class="actions">
                                        <a href="edit_game.php?id=<?= $game['id'] ?>" class="action-icon edit-icon" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="delete_game.php?id=<?= $game['id'] ?>" class="action-icon delete-icon" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce jeu : <?= htmlspecialchars(addslashes($game['title']), ENT_QUOTES) ?> ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Section Consoles -->
            <section class="admin-section animate__animated animate__fadeInUp animate__delay-200ms">
                 <div class="section-header">
                    <h2 class="section-title">Gestion des Consoles</h2>
                    <a href="add_console.php" class="admin-button add-button" title="Ajouter une console">
                         <i class="fas fa-plus"></i><span class="hidden sm:inline ml-2">Ajouter</span>
                    </a>
                </div>
                 <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Slug</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (empty($consoles)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-text-secondary py-4">Aucune console trouvée.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($consoles as $console): ?>
                                <tr>
                                    <td><?= $console['id'] ?></td>
                                    <td><?= htmlspecialchars($console['name']) ?></td>
                                    <td><?= htmlspecialchars($console['slug']) ?></td>
                                    <td class="actions">
                                        <a href="edit_console.php?id=<?= $console['id'] ?>" class="action-icon edit-icon" title="Modifier"><i class="fas fa-edit"></i></a>
                                         <a href="delete_console.php?id=<?= $console['id'] ?>" class="action-icon delete-icon" title="Supprimer" onclick="return confirm('Attention ! Supprimer cette console supprimera aussi TOUS les jeux associés.\nÊtes-vous sûr de vouloir supprimer : <?= htmlspecialchars(addslashes($console['name']), ENT_QUOTES) ?> ?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                             <?php endif; ?>
                        </tbody>
                    </table>
                 </div>
            </section>

            <!-- Ajouter d'autres sections si nécessaire (Utilisateurs, etc.) -->
             <!-- Exemple Section Utilisateurs -->
            <!--
            <section class="admin-section animate__animated animate__fadeInUp animate__delay-300ms lg:col-span-2">
                 <div class="section-header">
                    <h2 class="section-title">Gestion des Utilisateurs</h2>
                     <a href="add_user.php" class="admin-button add-button" title="Ajouter un utilisateur">
                         <i class="fas fa-plus"></i><span class="hidden sm:inline ml-2">Ajouter</span>
                    </a>
                 </div>
                 <div class="table-container">
                    // Table des utilisateurs ici
                 </div>
            </section>
            -->

        </main>

    </div> <!-- Fin .admin-container -->

    <!-- Optionnel : Ajouter un script JS spécifique à l'admin si besoin -->
    <!-- <script src="admin_script.js"></script> -->
     <script>
        // Script minimal pour confirmations (déjà géré par onclick, mais pourrait être centralisé ici)
        // Exemple:
        // document.querySelectorAll('.delete-icon').forEach(button => {
        //     button.addEventListener('click', function(event) {
        //         const itemName = this.closest('tr').querySelector('td:nth-child(2)').textContent; // Récupère le nom
        //         const message = this.href.includes('delete_game')
        //             ? `Êtes-vous sûr de vouloir supprimer le jeu : ${itemName} ?`
        //             : `Attention ! Supprimer la console ${itemName} supprimera aussi TOUS les jeux associés.\nÊtes-vous sûr ?`;
        //         if (!confirm(message)) {
        //             event.preventDefault(); // Annule la navigation si on clique sur "Annuler"
        //         }
        //     });
        // });
     </script>

</body>
</html>