<?php
namespace MagicianNews;

class Newsletter {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Subscribe an email to the newsletter
     */
    public function subscribe(string $email, ?string $name = null, string $source = 'homepage'): array {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email address');
        }

        // Sanitize inputs
        $email = strtolower(trim($email));
        $name = $name ? trim($name) : null;
        $source = trim($source);

        try {
            // Check if email already exists
            $existing = $this->db->fetchOne(
                "SELECT id, status FROM newsletter WHERE email = ?",
                [$email]
            );

            if ($existing) {
                // If previously unsubscribed, resubscribe
                if ($existing['status'] === 'unsubscribed') {
                    $this->db->query(
                        "UPDATE newsletter SET status = 'subscribed', subscribed_at = CURRENT_TIMESTAMP WHERE email = ?",
                        [$email]
                    );

                    return [
                        'email' => $email,
                        'status' => 'resubscribed',
                        'message' => 'Welcome back! You\'ve been resubscribed.',
                    ];
                }

                // Already subscribed
                return [
                    'email' => $email,
                    'status' => 'already_subscribed',
                    'message' => 'You\'re already on the list!',
                ];
            }

            // Insert new subscriber
            $this->db->query(
                "INSERT INTO newsletter (email, name, source) VALUES (?, ?, ?)",
                [$email, $name, $source]
            );

            return [
                'email' => $email,
                'status' => 'subscribed',
                'message' => 'Thank you for subscribing!',
            ];

        } catch (\PDOException $e) {
            throw new \Exception('Failed to subscribe: ' . $e->getMessage());
        }
    }

    /**
     * Unsubscribe an email from the newsletter
     */
    public function unsubscribe(string $email): array {
        $email = strtolower(trim($email));

        $existing = $this->db->fetchOne(
            "SELECT id FROM newsletter WHERE email = ?",
            [$email]
        );

        if (!$existing) {
            throw new \Exception('Email not found');
        }

        $this->db->query(
            "UPDATE newsletter SET status = 'unsubscribed' WHERE email = ?",
            [$email]
        );

        return [
            'email' => $email,
            'status' => 'unsubscribed',
            'message' => 'You\'ve been unsubscribed.',
        ];
    }

    /**
     * Get newsletter statistics
     */
    public function getStats(): array {
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM newsletter WHERE status = 'subscribed'"
        )['count'];

        $last30Days = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM newsletter
             WHERE status = 'subscribed'
             AND subscribed_at >= datetime('now', '-30 days')"
        )['count'];

        $last7Days = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM newsletter
             WHERE status = 'subscribed'
             AND subscribed_at >= datetime('now', '-7 days')"
        )['count'];

        $bySource = $this->db->fetchAll(
            "SELECT source, COUNT(*) as count
             FROM newsletter
             WHERE status = 'subscribed'
             GROUP BY source"
        );

        return [
            'total_subscribers' => (int)$total,
            'new_last_30_days' => (int)$last30Days,
            'new_last_7_days' => (int)$last7Days,
            'by_source' => $bySource,
        ];
    }

    /**
     * Get all subscribers (for export)
     */
    public function getSubscribers(int $limit = 100, int $offset = 0): array {
        return $this->db->fetchAll(
            "SELECT email, name, source, subscribed_at
             FROM newsletter
             WHERE status = 'subscribed'
             ORDER BY subscribed_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
}
