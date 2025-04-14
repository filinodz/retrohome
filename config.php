<?php
session_start();

define('DB_HOST', 'HOST');
define('DB_USER', 'USER');
define('DB_PASS', 'PASSWORD');  // CHANGE EN PRODUCTION
define('DB_NAME', 'DB_PASSWORD');

define('SCREENSCRAPER_USER', 'USERNAME_SCREENSCRAPER'); // Remplace par ton user
define('SCREENSCRAPER_PASSWORD', 'PASSWORD_SCREENSCRAPER'); // Remplace par ton pass
// Utilisation des identifiants dev fournis (à tes risques)
define('SCREENSCRAPER_DEV_ID', base64_decode("enVyZGkxNQ==")); // zurdi15
define('SCREENSCRAPER_DEV_PASSWORD', base64_decode("eFRKd29PRmpPUUc=")); // xTJwoOFjOQG

// Chemin ABSOLU vers le dossier roms (EN DEHORS de public_html)
define('ROMS_PATH', __DIR__ . '/roms/'); // __DIR__ est le répertoire du fichier config.php

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}