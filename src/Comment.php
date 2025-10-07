<?php
namespace MagicianNews;

class Comment {
    private Database $db;
    private const EDIT_WINDOW_MINUTES = 30;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all comments for an article (nested structure)
     */
    public function getArticleComments(string $articleId): array {
        $comments = $this->db->fetchAll(
            "SELECT c.*, u.email as user_email
             FROM comments c
             JOIN users u ON c.user_id = u.id
             WHERE c.article_id = ?
             ORDER BY c.created_at ASC",
            [$articleId]
        );

        // Build nested structure
        return $this->buildCommentTree($comments);
    }

    /**
     * Create a new comment
     */
    public function createComment(int $userId, string $articleId, string $content, ?int $parentId = null): array {
        // Validate parent exists if provided
        if ($parentId !== null) {
            $parent = $this->db->fetchOne(
                "SELECT id FROM comments WHERE id = ? AND article_id = ?",
                [$parentId, $articleId]
            );

            if (!$parent) {
                throw new \Exception("Parent comment not found");
            }
        }

        // Insert comment
        $this->db->query(
            "INSERT INTO comments (article_id, user_id, parent_id, content, created_at, updated_at)
             VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))",
            [$articleId, $userId, $parentId, trim($content)]
        );

        $commentId = $this->db->getConnection()->lastInsertId();

        // Return the created comment
        return $this->getComment($commentId);
    }

    /**
     * Update a comment (only within 30-minute window)
     */
    public function updateComment(int $commentId, int $userId, string $content): array {
        $comment = $this->db->fetchOne(
            "SELECT * FROM comments WHERE id = ? AND user_id = ?",
            [$commentId, $userId]
        );

        if (!$comment) {
            throw new \Exception("Comment not found or you don't have permission");
        }

        if ($comment['is_deleted']) {
            throw new \Exception("Cannot edit deleted comment");
        }

        // Check if within edit window
        if (!$this->canEdit($comment['created_at'])) {
            throw new \Exception("Edit window expired (30 minutes)");
        }

        $this->db->query(
            "UPDATE comments SET content = ?, updated_at = datetime('now') WHERE id = ?",
            [trim($content), $commentId]
        );

        return $this->getComment($commentId);
    }

    /**
     * Soft delete a comment
     */
    public function deleteComment(int $commentId, int $userId): void {
        $comment = $this->db->fetchOne(
            "SELECT * FROM comments WHERE id = ? AND user_id = ?",
            [$commentId, $userId]
        );

        if (!$comment) {
            throw new \Exception("Comment not found or you don't have permission");
        }

        if ($comment['is_deleted']) {
            throw new \Exception("Comment already deleted");
        }

        // Soft delete - keep the record but mark as deleted
        $this->db->query(
            "UPDATE comments SET is_deleted = 1, updated_at = datetime('now') WHERE id = ?",
            [$commentId]
        );
    }

    /**
     * Get a single comment by ID
     */
    private function getComment(int $commentId): array {
        $comment = $this->db->fetchOne(
            "SELECT c.*, u.email as user_email
             FROM comments c
             JOIN users u ON c.user_id = u.id
             WHERE c.id = ?",
            [$commentId]
        );

        if (!$comment) {
            throw new \Exception("Comment not found");
        }

        return $this->formatComment($comment);
    }

    /**
     * Check if comment can still be edited
     */
    private function canEdit(string $createdAt): bool {
        $created = new \DateTime($createdAt);
        $now = new \DateTime();
        $diff = $now->diff($created);

        $minutesElapsed = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        return $minutesElapsed < self::EDIT_WINDOW_MINUTES;
    }

    /**
     * Build nested comment tree from flat array
     */
    private function buildCommentTree(array $comments): array {
        $map = [];
        $tree = [];

        // First pass: format all comments and index by ID
        foreach ($comments as $comment) {
            $formatted = $this->formatComment($comment);
            $formatted['replies'] = [];
            $map[$comment['id']] = $formatted;
        }

        // Second pass: build tree structure
        foreach ($map as $id => $comment) {
            if ($comment['parent_id'] === null) {
                $tree[] = &$map[$id];
            } else {
                if (isset($map[$comment['parent_id']])) {
                    $map[$comment['parent_id']]['replies'][] = &$map[$id];
                }
            }
        }

        return $tree;
    }

    /**
     * Format comment for API response
     */
    private function formatComment(array $comment): array {
        $canEdit = !$comment['is_deleted'] && $this->canEdit($comment['created_at']);

        return [
            'id' => (int)$comment['id'],
            'article_id' => $comment['article_id'],
            'user_id' => (int)$comment['user_id'],
            'user_name' => $comment['user_email'], // Use email as display name
            'parent_id' => $comment['parent_id'] ? (int)$comment['parent_id'] : null,
            'content' => $comment['is_deleted'] ? '[Comment deleted by user]' : $comment['content'],
            'is_deleted' => (bool)$comment['is_deleted'],
            'can_edit' => $canEdit,
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at']
        ];
    }
}
