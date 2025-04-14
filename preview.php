<?php
   // Assurez-vous d'avoir la configuration de la base de données ici (comme config.php)
   require_once 'config.php';

   $gameId = $_GET['game_id'] ?? null;

   if (!$gameId) {
       http_response_code(400);
       echo "ID du jeu manquant.";
       exit;
   }

   try {
       $stmt = $db->prepare("SELECT preview FROM games WHERE id = ?");
       $stmt->execute([$gameId]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);

       if ($result && $result['preview']) {
           // Vous pouvez utiliser readfile() si ce sont des fichiers locaux
           // et que vous voulez streamer directement.  Sinon, juste l'URL.

           // Si ce sont des URLs :
           // header('Location: ' . $result['preview']);
           // exit;


          //Si ce sont des fichiers locaux (plus complexe, pour le streaming) :
           $filePath = $result['preview']; //  Assurez-vous que c'est un chemin *absolu* ou relatif correct
           if (file_exists($filePath) && is_readable($filePath)) {

                 header('Content-Type: video/mp4'); // Ou le type MIME correct
               header('Content-Length: ' . filesize($filePath));
               header('Accept-Ranges: bytes'); // Pour le support de la lecture partielle

               readfile($filePath);
               exit;
           } else {
               http_response_code(404);
               echo "Fichier vidéo non trouvé.";
                exit;
           }


       } else {
           http_response_code(404);
           echo "Aucune preview trouvée pour ce jeu.";
            exit;
       }
   } catch (PDOException $e) {
       http_response_code(500);
       echo "Erreur de base de données : " . $e->getMessage();
        exit;
   }