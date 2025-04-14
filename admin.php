<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'addGame':
                $stmt = $db->prepare("
                    INSERT INTO games (console_id, title, description, year, publisher, cover, preview, rom_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['console_id'],
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['year'],
                    $_POST['publisher'],
                    $_POST['cover'],
                    $_POST['preview'],
                    $_POST['rom_path']
                ]);
                header('Location: admin.php?success=1');
                exit;
                break;
            
            case 'deleteGame':
                $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
                $stmt->execute([$_POST['game_id']]);
                header('Location: admin.php?success=1');
                exit;
                break;
        }
    }
}

$consoles = $db->query("SELECT * FROM consoles ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$games = $db->query("
    SELECT g.*, c.name as console_name 
    FROM games g 
    JOIN consoles c ON g.console_id = c.id 
    ORDER BY c.sort_order, g.sort_order
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - RetroGaming Paradise</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Administration des jeux</h1>

        <!-- Formulaire d'ajout -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h2 class="text-xl font-bold mb-4">Ajouter un jeu</h2>
            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="addGame">
                
                <div>
                    <label class="block mb-2">Console</label>
                    <select name="console_id" class="w-full p-2 border rounded">
                        <?php foreach($consoles as $console): ?>
                            <option value="<?= $console['id'] ?>"><?= htmlspecialchars($console['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block mb-2">Titre</label>
                    <input type="text" name="title" required class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block mb-2">Description</label>
                    <textarea name="description" class="w-full p-2 border rounded" rows="3"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2">Année</label>
                        <input type="number" name="year" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block mb-2">Éditeur</label>
                        <input type="text" name="publisher" class="w-full p-2 border rounded">
                    </div>
                </div>

                <div>
                    <label class="block mb-2">Chemin de la couverture</label>
                    <input type="text" name="cover" class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block mb-2">Chemin de la preview</label>
                    <input type="text" name="preview" class="w-full p-2 border rounded">
                </div>

                <div>
                    <label class="block mb-2">Chemin de la ROM</label>
                    <input type="text" name="rom_path" required class="w-full p-2 border rounded">
                </div>

                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Ajouter le jeu
                </button>
            </form>
        </div>

        <!-- Liste des jeux -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold mb-4">Jeux existants</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-2 text-left">Console</th>
                            <th class="p-2 text-left">Titre</th>
                            <th class="p-2 text-left">Année</th>
                            <th class="p-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($games as $game): ?>
                            <tr class="border-t">
                                <td class="p-2"><?= htmlspecialchars($game['console_name']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($game['title']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($game['year']) ?></td>
                                <td class="p-2">
                                    <form action="admin.php" method="POST" class="inline">
                                        <input type="hidden" name="action" value="deleteGame">
                                        <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce jeu ?')">
                                            Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
