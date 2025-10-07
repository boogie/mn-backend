<?php
require_once __DIR__ . '/../../src/config.php';

use MagicianNews\Auth;
use MagicianNews\Comment;
use MagicianNews\Response;

$auth = new Auth();
$comment = new Comment();

$method = $_SERVER['REQUEST_METHOD'];

// Get article comments (public - no auth required)
if ($method === 'GET') {
    try {
        $articleId = $_GET['article_id'] ?? null;

        if (!$articleId) {
            Response::error('Article ID is required', 400);
        }

        $comments = $comment->getArticleComments($articleId);
        Response::success($comments);
    } catch (\Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

// Create comment (requires auth)
if ($method === 'POST') {
    try {
        // Verify authentication
        $token = $auth->getAuthHeader();
        if (!$token) {
            Response::unauthorized();
        }

        $decoded = $auth->verifyToken($token);
        $userId = $decoded['user_id'];

        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['article_id']) || !isset($input['content'])) {
            Response::error('Article ID and content are required', 400);
        }

        if (empty(trim($input['content']))) {
            Response::error('Comment content cannot be empty', 400);
        }

        if (strlen($input['content']) > 2000) {
            Response::error('Comment too long (max 2000 characters)', 400);
        }

        $parentId = $input['parent_id'] ?? null;

        $newComment = $comment->createComment(
            $userId,
            $input['article_id'],
            $input['content'],
            $parentId
        );

        Response::success($newComment, 'Comment created');
    } catch (\Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// Update comment (requires auth)
if ($method === 'PUT') {
    try {
        // Verify authentication
        $token = $auth->getAuthHeader();
        if (!$token) {
            Response::unauthorized();
        }

        $decoded = $auth->verifyToken($token);
        $userId = $decoded['user_id'];

        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['comment_id']) || !isset($input['content'])) {
            Response::error('Comment ID and content are required', 400);
        }

        if (empty(trim($input['content']))) {
            Response::error('Comment content cannot be empty', 400);
        }

        if (strlen($input['content']) > 2000) {
            Response::error('Comment too long (max 2000 characters)', 400);
        }

        $updatedComment = $comment->updateComment(
            (int)$input['comment_id'],
            $userId,
            $input['content']
        );

        Response::success($updatedComment, 'Comment updated');
    } catch (\Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// Delete comment (requires auth)
if ($method === 'DELETE') {
    try {
        // Verify authentication
        $token = $auth->getAuthHeader();
        if (!$token) {
            Response::unauthorized();
        }

        $decoded = $auth->verifyToken($token);
        $userId = $decoded['user_id'];

        // Get comment ID from query string
        $commentId = $_GET['comment_id'] ?? null;

        if (!$commentId) {
            Response::error('Comment ID is required', 400);
        }

        $comment->deleteComment((int)$commentId, $userId);

        Response::success(null, 'Comment deleted');
    } catch (\Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}
