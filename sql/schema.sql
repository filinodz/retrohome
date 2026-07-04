-- RetroHome — Schéma de base de données (structure + seed consoles)
-- Généré pour une installation propre. Aucune donnée personnelle/secret.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `collection_games` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `game_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `collection_game` (`collection_id`,`game_id`),
  KEY `collection_id` (`collection_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `collection_games_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`),
  CONSTRAINT `collection_games_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE `collections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_name` (`user_id`,`name`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE `consoles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `sort_order` int DEFAULT '0',
  `ss_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_ss_id` (`ss_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;
CREATE TABLE `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `game_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`game_id`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;
CREATE TABLE `games` (
  `id` int NOT NULL AUTO_INCREMENT,
  `console_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `year` int DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `preview` varchar(255) DEFAULT NULL,
  `rom_path` varchar(255) NOT NULL,
  `multiplayer` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `console_id` (`console_id`)
) ENGINE=InnoDB AUTO_INCREMENT=306 DEFAULT CHARSET=latin1;
CREATE TABLE `ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `game_id` int NOT NULL,
  `rating` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`game_id`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=latin1;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE `user_game_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `game_id` int NOT NULL,
  `time_played_seconds` bigint unsigned NOT NULL DEFAULT '0',
  `last_played` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_game` (`user_id`,`game_id`),
  KEY `fk_stats_user` (`user_id`),
  KEY `fk_stats_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;



-- Seed : consoles (utile pour démarrer, sans secret)
INSERT INTO `consoles` VALUES (1,'Nintendo Entertainment System','nes','./assets/logos/nes-logo.png',1,3);
INSERT INTO `consoles` VALUES (2,'Super Nintendo','snes','./assets/logos/snes-logo.png',2,4);
INSERT INTO `consoles` VALUES (3,'PlayStation','psx','./assets/logos/psx-logo.png',3,57);
INSERT INTO `consoles` VALUES (4,'Nintendo 64','n64','./assets/logos/n64-logo.png',4,14);
INSERT INTO `consoles` VALUES (5,'3DO Interactive Multiplayer','3do','./assets/logos/3do-logo.png',5,29);
INSERT INTO `consoles` VALUES (6,'Atari 2600','atari2600','./assets/logos/atari2600-logo.png',6,26);
INSERT INTO `consoles` VALUES (7,'Atari 5200','atari5200','./assets/logos/atari5200-logo.png',7,40);
INSERT INTO `consoles` VALUES (8,'Atari 7800','atari7800','./assets/logos/atari7800-logo.png',8,41);
INSERT INTO `consoles` VALUES (9,'Atari Jaguar','jaguar','./assets/logos/jaguar-logo.png',9,27);
INSERT INTO `consoles` VALUES (10,'Atari Lynx','lynx','./assets/logos/lynx-logo.png',10,28);
INSERT INTO `consoles` VALUES (11,'ColecoVision','colecovision','./assets/logos/colecovision-logo.png',11,48);
INSERT INTO `consoles` VALUES (12,'Game Boy','gb','./assets/logos/gb-logo.png',12,9);
INSERT INTO `consoles` VALUES (13,'Game Boy Advance','gba','./assets/logos/gba-logo.png',13,12);
INSERT INTO `consoles` VALUES (14,'Game Boy Color','gbc','./assets/logos/gbc-logo.png',14,10);
INSERT INTO `consoles` VALUES (15,'Super Nintendo MSU-1','msu1','./assets/logos/msu1-logo.png',15,NULL);
INSERT INTO `consoles` VALUES (16,'MSX','msx','./assets/logos/msx-logo.png',16,113);
INSERT INTO `consoles` VALUES (17,'Nintendo DS','nds','./assets/logos/nds-logo.png',17,15);
INSERT INTO `consoles` VALUES (18,'Neo Geo Pocket','ngp','./assets/logos/ngp-logo.png',18,25);
INSERT INTO `consoles` VALUES (19,'Magnavox Odyssey 2','odyssey2','./assets/logos/odyssey2-logo.png',19,104);
INSERT INTO `consoles` VALUES (20,'PC Engine / TurboGrafx-16','pce','./assets/logos/pce-logo.png',20,31);
INSERT INTO `consoles` VALUES (21,'PC Engine CD / TurboGrafx-CD','pcecd','./assets/logos/pcecd-logo.png',21,114);
INSERT INTO `consoles` VALUES (22,'Sega 32X','sega32x','./assets/logos/sega32x-logo.png',22,19);
INSERT INTO `consoles` VALUES (23,'Sega CD','segaCD','./assets/logos/segaCD-logo.png',23,20);
INSERT INTO `consoles` VALUES (24,'Sega Game Gear','segaGG','./assets/logos/segaGG-logo.png',24,21);
INSERT INTO `consoles` VALUES (25,'Sega Genesis / Mega Drive','segaMD','./assets/logos/segaMD-logo.png',25,1);
INSERT INTO `consoles` VALUES (26,'Sega Master System','segaMS','./assets/logos/segaMS-logo.png',26,2);
INSERT INTO `consoles` VALUES (27,'Sega Saturn','segasaturn','./assets/logos/segasaturn-logo.png',27,22);
INSERT INTO `consoles` VALUES (28,'Vectrex','vectrex','./assets/logos/vectrex-logo.png',28,102);
INSERT INTO `consoles` VALUES (29,'Virtual Boy','vb','./assets/logos/vb-logo.png',29,11);
INSERT INTO `consoles` VALUES (30,'WonderSwan','ws','./assets/logos/ws-logo.png',30,45);
INSERT INTO `consoles` VALUES (31,'MSU-MD','msumd','./assets/logos/msumd-logo.png',31,NULL);
INSERT INTO `consoles` VALUES (34,'Arcade','mame2003','./assets/logos/arcade-logo.png',1,75);

-- Réglages par défaut (identifiants ScreenScraper à renseigner dans /admin)
INSERT INTO `settings` (`setting_key`,`setting_value`,`description`) VALUES
('site_name','RetroHome','Nom du site'),
('site_theme','aurora','Thème actif'),
('screenscraper_user','','Nom d''utilisateur ScreenScraper.fr'),
('screenscraper_pass','','Mot de passe ScreenScraper.fr'),
('screenscraper_devid','enVyZGkxNQ==','Dev ID ScreenScraper.fr (base64)'),
('screenscraper_devpass','eFRKd29PRmpPUUc=','Dev Password ScreenScraper.fr (base64)'),
('netplay_url','','URL du serveur NetPlay (vide = http://<host>:3000)')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS=1;
