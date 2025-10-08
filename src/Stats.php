<?php
namespace MagicianNews;

class Stats {
    private Database $db;
    private CMSClient $cms;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->cms = new CMSClient();
    }

    /**
     * Verify the secret token
     */
    public function verifyToken(string $token): bool {
        $secretToken = $_ENV['STATS_SECRET_TOKEN'] ?? '';
        return !empty($secretToken) && hash_equals($secretToken, $token);
    }

    /**
     * Get comprehensive platform statistics
     */
    public function getStats(): array {
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'users' => $this->getUserStats(),
            'subscriptions' => $this->getSubscriptionStats(),
            'content' => $this->getContentStats(),
            'engagement' => $this->getEngagementStats(),
            'revenue' => $this->getRevenueStats(),
        ];
    }

    /**
     * Get user statistics
     */
    private function getUserStats(): array {
        // Total users
        $totalUsers = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users"
        )['count'];

        // Users registered in last 30 days
        $newUsers30d = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE created_at >= datetime('now', '-30 days')"
        )['count'];

        // Users registered in last 7 days
        $newUsers7d = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE created_at >= datetime('now', '-7 days')"
        )['count'];

        // Users by authentication method
        $googleUsers = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE google_id IS NOT NULL"
        )['count'];

        $emailUsers = $totalUsers - $googleUsers;

        return [
            'total' => (int)$totalUsers,
            'new_last_30_days' => (int)$newUsers30d,
            'new_last_7_days' => (int)$newUsers7d,
            'by_auth_method' => [
                'email' => (int)$emailUsers,
                'google_oauth' => (int)$googleUsers,
            ],
        ];
    }

    /**
     * Get subscription statistics
     */
    private function getSubscriptionStats(): array {
        // Active subscriptions
        $activeSubscriptions = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE subscription_status = 'active'"
        )['count'];

        // Inactive/free users
        $freeUsers = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE subscription_status != 'active' OR subscription_status IS NULL"
        )['count'];

        // Early adopters (created before a certain date)
        $earlyAdopters = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE subscription_status = 'active'
             AND created_at < '2026-01-01'"
        )['count'];

        return [
            'active_subscribers' => (int)$activeSubscriptions,
            'free_users' => (int)$freeUsers,
            'early_adopters' => (int)$earlyAdopters,
            'conversion_rate' => $activeSubscriptions > 0
                ? round(($activeSubscriptions / ($activeSubscriptions + $freeUsers)) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get content statistics from PayloadCMS
     */
    private function getContentStats(): array {
        try {
            // Get article count
            $articles = $this->cms->getCollection('articles', ['limit' => 1]);
            $articleCount = $articles['totalDocs'] ?? 0;

            // Get video count
            $videos = $this->cms->getCollection('videos', ['limit' => 1]);
            $videoCount = $videos['totalDocs'] ?? 0;

            // Get daily facts count
            $facts = $this->cms->getCollection('daily-facts', ['limit' => 1]);
            $factsCount = $facts['totalDocs'] ?? 0;

            // Get apps count
            $apps = $this->cms->getCollection('apps', ['limit' => 1]);
            $appsCount = $apps['totalDocs'] ?? 0;

            return [
                'articles' => (int)$articleCount,
                'videos' => (int)$videoCount,
                'daily_facts' => (int)$factsCount,
                'app_reviews' => (int)$appsCount,
                'total_content_pieces' => (int)($articleCount + $videoCount + $factsCount + $appsCount),
            ];
        } catch (\Exception $e) {
            return [
                'articles' => 0,
                'videos' => 0,
                'daily_facts' => 0,
                'app_reviews' => 0,
                'total_content_pieces' => 0,
                'error' => 'Failed to fetch content stats: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get engagement statistics
     */
    private function getEngagementStats(): array {
        // Total comments
        $totalComments = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM comments WHERE is_deleted = 0"
        )['count'];

        // Comments in last 30 days
        $comments30d = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM comments
             WHERE is_deleted = 0
             AND created_at >= datetime('now', '-30 days')"
        )['count'];

        // Comments in last 7 days
        $comments7d = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM comments
             WHERE is_deleted = 0
             AND created_at >= datetime('now', '-7 days')"
        )['count'];

        // Active commenters (users who commented in last 30 days)
        $activeCommenters = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT user_id) as count FROM comments
             WHERE is_deleted = 0
             AND created_at >= datetime('now', '-30 days')"
        )['count'];

        // Top articles by comment count
        $topArticles = $this->db->fetchAll(
            "SELECT article_id, COUNT(*) as comment_count
             FROM comments
             WHERE is_deleted = 0
             GROUP BY article_id
             ORDER BY comment_count DESC
             LIMIT 5"
        );

        return [
            'total_comments' => (int)$totalComments,
            'comments_last_30_days' => (int)$comments30d,
            'comments_last_7_days' => (int)$comments7d,
            'active_commenters_30d' => (int)$activeCommenters,
            'top_articles_by_comments' => $topArticles,
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(): array {
        // Total invoices
        $totalInvoices = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM invoices"
        )['count'];

        // Total revenue (sum of all paid invoices)
        $totalRevenue = $this->db->fetchOne(
            "SELECT SUM(amount) as total FROM invoices
             WHERE status = 'paid'"
        )['total'] ?? 0;

        // Revenue in last 30 days
        $revenue30d = $this->db->fetchOne(
            "SELECT SUM(amount) as total FROM invoices
             WHERE status = 'paid'
             AND created_at >= datetime('now', '-30 days')"
        )['total'] ?? 0;

        // MRR (Monthly Recurring Revenue) - active subscriptions
        $activeSubscribers = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE subscription_status = 'active'"
        )['count'];

        // Assuming €1 for early adopters, €4 for others (simplified - you'll need to track actual tiers)
        $estimatedMRR = $activeSubscribers * 1; // Conservative estimate at €1 average

        return [
            'total_invoices' => (int)$totalInvoices,
            'total_revenue' => round((float)$totalRevenue, 2),
            'revenue_last_30_days' => round((float)$revenue30d, 2),
            'currency' => 'EUR',
            'estimated_mrr' => round($estimatedMRR, 2),
            'active_subscribers' => (int)$activeSubscribers,
        ];
    }

    /**
     * Get growth metrics (comparing periods)
     */
    public function getGrowthMetrics(): array {
        // User growth
        $usersThisMonth = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE created_at >= datetime('now', 'start of month')"
        )['count'];

        $usersLastMonth = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE created_at >= datetime('now', '-1 month', 'start of month')
             AND created_at < datetime('now', 'start of month')"
        )['count'];

        $userGrowth = $usersLastMonth > 0
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 2)
            : 0;

        return [
            'user_growth' => [
                'this_month' => (int)$usersThisMonth,
                'last_month' => (int)$usersLastMonth,
                'growth_percentage' => $userGrowth,
            ],
        ];
    }
}
