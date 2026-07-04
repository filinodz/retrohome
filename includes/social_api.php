<?php
/**
 * Social Features API Functions
 * Include this file in api.php switch statement
 */

// =====================================================
// SOCIAL FEATURES API HANDLERS
// =====================================================

function handleGetPublicProfile() {
    global $db;
    $username = $_GET['username'] ?? null;
    if (!$username) {
        sendResponse(['error' => 'Username is required'], 400);
    }
    try {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.created_at,
                   up.bio, up.profile_picture, up.cover_photo, up.is_public,
                   (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorite_count,
                   (SELECT COUNT(*) FROM ratings WHERE user_id = u.id) as rating_count,
                   (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as followers_count,
                   (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE u.username = :username
        ");
        $stmt->execute([':username' => $username]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            sendResponse(['error' => 'User not found'], 404);
        }
        sendResponse($profile);
    } catch (PDOException $e) {
        error_log("getPublicProfile error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

function handleUpdateProfile($postData) {
    global $db;
    checkLoggedIn();
    $bio = trim($postData['bio'] ?? '');
    $profilePicture = $postData['profile_picture'] ?? null;
    $coverPhoto = $postData['cover_photo'] ?? null;
    $isPublic = isset($postData['is_public']) ? (int)$postData['is_public'] : 1;

    try {
        $stmt = $db->prepare("
            INSERT INTO user_profiles (user_id, bio, profile_picture, cover_photo, is_public)
            VALUES (:user_id, :bio, :profile_picture, :cover_photo, :is_public)
            ON DUPLICATE KEY UPDATE 
                bio = VALUES(bio),
                profile_picture = COALESCE(VALUES(profile_picture), profile_picture),
                cover_photo = COALESCE(VALUES(cover_photo), cover_photo),
                is_public = VALUES(is_public)
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':bio' => $bio,
            ':profile_picture' => $profilePicture,
            ':cover_photo' => $coverPhoto,
            ':is_public' => $isPublic
        ]);
        sendResponse(['message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        error_log("updateProfile error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to update profile'], 500);
    }
}

function handleCreatePost($postData) {
    global $db;
    checkLoggedIn();
    $content = trim($postData['content'] ?? '');
    $imageUrl = $postData['image_url'] ?? null;
    $gameId = isset($postData['game_id']) ? (int)$postData['game_id'] : null;

    if (empty($content)) {
        sendResponse(['error' => 'Content is required'], 400);
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO posts (user_id, content, image_url, game_id)
            VALUES (:user_id, :content, :image_url, :game_id)
        ");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':content' => $content,
            ':image_url' => $imageUrl,
            ':game_id' => $gameId ?: null
        ]);
        sendResponse(['message' => 'Post created', 'post_id' => $db->lastInsertId()], 201);
    } catch (PDOException $e) {
        error_log("createPost error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to create post'], 500);
    }
}

function handleEditPost($postData) {
    global $db;
    checkLoggedIn();
    $postId = (int)($postData['post_id'] ?? 0);
    $content = trim($postData['content'] ?? '');
    
    if (!$postId || empty($content)) {
        sendResponse(['error' => 'post_id and content are required'], 400);
    }

    try {
        $stmt = $db->prepare("UPDATE posts SET content = :content WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':content' => $content,
            ':id' => $postId,
            ':user_id' => $_SESSION['user_id']
        ]);
        if ($stmt->rowCount() > 0) {
            sendResponse(['message' => 'Post updated']);
        } else {
            sendResponse(['error' => 'Post not found, not owned, or no changes made'], 404);
        }
    } catch (PDOException $e) {
        error_log("editPost error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to update post'], 500);
    }
}

function handleDeletePost($postData) {
    global $db;
    checkLoggedIn();
    $postId = (int)($postData['post_id'] ?? 0);
    if (!$postId) {
        sendResponse(['error' => 'Valid post_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM posts WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $postId, ':user_id' => $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            sendResponse(['message' => 'Post deleted']);
        } else {
            sendResponse(['error' => 'Post not found or not owned'], 404);
        }
    } catch (PDOException $e) {
        error_log("deletePost error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to delete post'], 500);
    }
}

function handleGetPosts() {
    global $db;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $currentUserId = $_SESSION['user_id'] ?? 0;

    try {
        $sql = "
            SELECT p.*, 
                   u.username as author_username,
                   up.profile_picture as author_avatar,
                   g.title as game_title, g.cover as game_cover, g.id as game_id,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
                   (SELECT 1 FROM post_likes WHERE post_id = p.id AND user_id = :current_user) as user_liked
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN games g ON p.game_id = g.id
        ";
        if ($userId) {
            $sql .= " WHERE p.user_id = :user_id";
        }
        $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':current_user', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($userId) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("getPosts error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

function handleLikePost($postData) {
    global $db;
    checkLoggedIn();
    $postId = (int)($postData['post_id'] ?? 0);
    if (!$postId) {
        sendResponse(['error' => 'Valid post_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)");
        $stmt->execute([':post_id' => $postId, ':user_id' => $_SESSION['user_id']]);
        
        $countStmt = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = :post_id");
        $countStmt->execute([':post_id' => $postId]);
        sendResponse(['message' => 'Post liked', 'like_count' => $countStmt->fetchColumn()]);
    } catch (PDOException $e) {
        error_log("likePost error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to like post'], 500);
    }
}

function handleUnlikePost($postData) {
    global $db;
    checkLoggedIn();
    $postId = (int)($postData['post_id'] ?? 0);
    if (!$postId) {
        sendResponse(['error' => 'Valid post_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id");
        $stmt->execute([':post_id' => $postId, ':user_id' => $_SESSION['user_id']]);
        
        $countStmt = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = :post_id");
        $countStmt->execute([':post_id' => $postId]);
        sendResponse(['message' => 'Post unliked', 'like_count' => $countStmt->fetchColumn()]);
    } catch (PDOException $e) {
        error_log("unlikePost error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to unlike post'], 500);
    }
}

function handleAddComment($postData) {
    global $db;
    checkLoggedIn();
    $postId = (int)($postData['post_id'] ?? 0);
    $content = trim($postData['content'] ?? '');

    if (!$postId || empty($content)) {
        sendResponse(['error' => 'post_id and content are required'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)");
        $stmt->execute([':post_id' => $postId, ':user_id' => $_SESSION['user_id'], ':content' => $content]);
        sendResponse(['message' => 'Comment added', 'comment_id' => $db->lastInsertId()], 201);
    } catch (PDOException $e) {
        error_log("addComment error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to add comment'], 500);
    }
}

function handleEditComment($postData) {
    global $db;
    checkLoggedIn();
    $commentId = (int)($postData['comment_id'] ?? 0);
    $content = trim($postData['content'] ?? '');

    if (!$commentId || empty($content)) {
        sendResponse(['error' => 'comment_id and content are required'], 400);
    }

    try {
        $stmt = $db->prepare("UPDATE post_comments SET content = :content WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':content' => $content,
            ':id' => $commentId,
            ':user_id' => $_SESSION['user_id']
        ]);
        if ($stmt->rowCount() > 0) {
            sendResponse(['message' => 'Comment updated']);
        } else {
            sendResponse(['error' => 'Comment not found, not owned, or no changes made'], 404);
        }
    } catch (PDOException $e) {
        error_log("editComment error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to update comment'], 500);
    }
}

function handleGetComments() {
    global $db;
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) {
        sendResponse(['error' => 'Valid post_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT c.*, u.username, up.profile_picture
            FROM post_comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE c.post_id = :post_id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':post_id' => $postId]);
        sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("getComments error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

function handleDeleteComment($postData) {
    global $db;
    checkLoggedIn();
    $commentId = (int)($postData['comment_id'] ?? 0);
    if (!$commentId) {
        sendResponse(['error' => 'Valid comment_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM post_comments WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $commentId, ':user_id' => $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            sendResponse(['message' => 'Comment deleted']);
        } else {
            sendResponse(['error' => 'Comment not found or not owned'], 404);
        }
    } catch (PDOException $e) {
        error_log("deleteComment error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to delete comment'], 500);
    }
}

function handleFollowUser($postData) {
    global $db;
    checkLoggedIn();
    $followingId = (int)($postData['user_id'] ?? 0);
    if (!$followingId || $followingId == $_SESSION['user_id']) {
        sendResponse(['error' => 'Valid user_id is required (cannot follow yourself)'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT IGNORE INTO follows (follower_id, following_id) VALUES (:follower, :following)");
        $stmt->execute([':follower' => $_SESSION['user_id'], ':following' => $followingId]);
        sendResponse(['message' => 'Now following user']);
    } catch (PDOException $e) {
        error_log("followUser error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to follow user'], 500);
    }
}

function handleUnfollowUser($postData) {
    global $db;
    checkLoggedIn();
    $followingId = (int)($postData['user_id'] ?? 0);
    if (!$followingId) {
        sendResponse(['error' => 'Valid user_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM follows WHERE follower_id = :follower AND following_id = :following");
        $stmt->execute([':follower' => $_SESSION['user_id'], ':following' => $followingId]);
        sendResponse(['message' => 'Unfollowed user']);
    } catch (PDOException $e) {
        error_log("unfollowUser error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to unfollow user'], 500);
    }
}

function handleIsFollowing() {
    global $db;
    checkLoggedIn();
    $targetId = (int)($_GET['user_id'] ?? 0);
    if (!$targetId) {
        sendResponse(['error' => 'Valid user_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = :follower AND following_id = :following");
        $stmt->execute([':follower' => $_SESSION['user_id'], ':following' => $targetId]);
        sendResponse(['isFollowing' => (bool)$stmt->fetchColumn()]);
    } catch (PDOException $e) {
        error_log("isFollowing error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

// =====================================================
// COLLECTIONS API HANDLERS
// =====================================================

function handleCreateCollection($postData) {
    global $db;
    checkLoggedIn();
    $name = trim($postData['name'] ?? '');
    $description = trim($postData['description'] ?? '');

    if (empty($name)) {
        sendResponse(['error' => 'Collection name is required'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO collections (user_id, name, description) VALUES (:user_id, :name, :description)");
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':name' => $name,
            ':description' => $description
        ]);
        sendResponse(['message' => 'Collection created', 'collection_id' => $db->lastInsertId()], 201);
    } catch (PDOException $e) {
        error_log("createCollection error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to create collection'], 500);
    }
}

function handleGetCollections() {
    global $db;
    checkLoggedIn();

    try {
        $stmt = $db->prepare("SELECT * FROM collections WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("getCollections error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

function handleDeleteCollection($postData) {
    global $db;
    checkLoggedIn();
    $collectionId = (int)($postData['collection_id'] ?? 0);
    if (!$collectionId) {
        sendResponse(['error' => 'Valid collection_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM collections WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $collectionId, ':user_id' => $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            sendResponse(['message' => 'Collection deleted']);
        } else {
            sendResponse(['error' => 'Collection not found or not owned'], 404);
        }
    } catch (PDOException $e) {
        error_log("deleteCollection error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to delete collection'], 500);
    }
}

function handleGetCollectionGames() {
    global $db;
    checkLoggedIn();
    $collectionId = (int)($_GET['collection_id'] ?? 0);
    if (!$collectionId) {
        sendResponse(['error' => 'Valid collection_id is required'], 400);
    }

    try {
        // Verify ownership
        $ownerCheck = $db->prepare("SELECT 1 FROM collections WHERE id = :id AND user_id = :user_id");
        $ownerCheck->execute([':id' => $collectionId, ':user_id' => $_SESSION['user_id']]);
        if (!$ownerCheck->fetchColumn()) {
            sendResponse(['error' => 'Collection not found or access denied'], 404);
        }

        $stmt = $db->prepare("
            SELECT g.*, c.name as console_name, c.slug as console_slug
            FROM collection_games cg
            JOIN games g ON cg.game_id = g.id
            JOIN consoles c ON g.console_id = c.id
            WHERE cg.collection_id = :collection_id
            ORDER BY cg.added_at DESC
        ");
        $stmt->execute([':collection_id' => $collectionId]);
        sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("getCollectionGames error: " . $e->getMessage());
        sendResponse(['error' => 'Database error'], 500);
    }
}

function handleAddGameToCollection($postData) {
    global $db;
    checkLoggedIn();
    $collectionId = (int)($postData['collection_id'] ?? 0);
    $gameId = (int)($postData['game_id'] ?? 0);

    if (!$collectionId || !$gameId) {
        sendResponse(['error' => 'collection_id and game_id are required'], 400);
    }

    try {
        // Verify ownership
        $ownerCheck = $db->prepare("SELECT 1 FROM collections WHERE id = :id AND user_id = :user_id");
        $ownerCheck->execute([':id' => $collectionId, ':user_id' => $_SESSION['user_id']]);
        if (!$ownerCheck->fetchColumn()) {
            sendResponse(['error' => 'Collection not found or access denied'], 404);
        }

        $stmt = $db->prepare("INSERT IGNORE INTO collection_games (collection_id, game_id) VALUES (:collection_id, :game_id)");
        $stmt->execute([':collection_id' => $collectionId, ':game_id' => $gameId]);
        sendResponse(['message' => 'Game added to collection']);
    } catch (PDOException $e) {
        error_log("addGameToCollection error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to add game'], 500);
    }
}

function handleRemoveGameFromCollection($postData) {
    global $db;
    checkLoggedIn();
    $collectionId = (int)($postData['collection_id'] ?? 0);
    $gameId = (int)($postData['game_id'] ?? 0);

    if (!$collectionId || !$gameId) {
        sendResponse(['error' => 'collection_id and game_id are required'], 400);
    }

    try {
        // Verify ownership
        $ownerCheck = $db->prepare("SELECT 1 FROM collections WHERE id = :id AND user_id = :user_id");
        $ownerCheck->execute([':id' => $collectionId, ':user_id' => $_SESSION['user_id']]);
        if (!$ownerCheck->fetchColumn()) {
            sendResponse(['error' => 'Collection not found or access denied'], 404);
        }

        $stmt = $db->prepare("DELETE FROM collection_games WHERE collection_id = :collection_id AND game_id = :game_id");
        $stmt->execute([':collection_id' => $collectionId, ':game_id' => $gameId]);
        sendResponse(['message' => 'Game removed from collection']);
    } catch (PDOException $e) {
        error_log("removeGameFromCollection error: " . $e->getMessage());
        sendResponse(['error' => 'Failed to remove game'], 500);
    }
}
