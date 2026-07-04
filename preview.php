<?php

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
           $filePath = $result['preview']; 
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