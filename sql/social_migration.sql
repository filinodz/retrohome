-- =====================================================
-- RETROHOME: Social Features Migration
-- Run this file in phpMyAdmin to add social features
-- =====================================================

-- 1. User Profiles Extension
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `user_id` INT PRIMARY KEY,
    `bio` TEXT DEFAULT NULL,
    `profile_picture` VARCHAR(500) DEFAULT NULL,
    `cover_photo` VARCHAR(500) DEFAULT NULL,
    `is_public` TINYINT(1) DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Social Posts
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `game_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_post_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_post_game` FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Post Likes
CREATE TABLE IF NOT EXISTS `post_likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_like` (`post_id`, `user_id`),
    CONSTRAINT `fk_like_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_like_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Post Comments
CREATE TABLE IF NOT EXISTS `post_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Follows (Followers System)
CREATE TABLE IF NOT EXISTS `follows` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `follower_id` INT NOT NULL,
    `following_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_follow` (`follower_id`, `following_id`),
    CONSTRAINT `fk_follower` FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_following` FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create default profiles for existing users
INSERT IGNORE INTO `user_profiles` (`user_id`, `bio`, `is_public`)
SELECT `id`, NULL, 1 FROM `users`;
