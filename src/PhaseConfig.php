<?php
namespace MagicianNews;

/**
 * Phase Configuration
 *
 * Controls what features are available based on the current PHASE environment variable.
 * See PHASE_SYSTEM.md for detailed documentation.
 */
class PhaseConfig {
    private static ?int $currentPhase = null;

    /**
     * Get the current phase from environment variable
     */
    public static function getCurrentPhase(): int {
        if (self::$currentPhase === null) {
            self::$currentPhase = (int)($_ENV['PHASE'] ?? 0);
        }
        return self::$currentPhase;
    }

    /**
     * PHASE 3+: Public registration is open
     */
    public static function isRegistrationOpen(): bool {
        return self::getCurrentPhase() >= 3;
    }

    /**
     * PHASE 0-2: Registration requires an invite code
     */
    public static function requiresInviteCode(): bool {
        return self::getCurrentPhase() < 3;
    }

    /**
     * PHASE 2+: Payment system is active
     */
    public static function isPaymentActive(): bool {
        return self::getCurrentPhase() >= 2;
    }

    /**
     * PHASE 1+: Viral invite system is enabled
     */
    public static function hasViralInvites(): bool {
        return self::getCurrentPhase() >= 1;
    }

    /**
     * PHASE 2+: Shop registration and QR codes are enabled
     */
    public static function hasShopRegistration(): bool {
        return self::getCurrentPhase() >= 2;
    }

    /**
     * Get founding member price in cents (€1 = 100, €4 = 400)
     * PHASE 0-2: €1/month
     * PHASE 3+: €4/month (founding members grandfathered at €1)
     */
    public static function getFoundingMemberPrice(): int {
        return self::getCurrentPhase() >= 3 ? 400 : 100;
    }

    /**
     * PHASE 0: Only admin invite tokens work
     */
    public static function isAdminInviteOnly(): bool {
        return self::getCurrentPhase() === 0;
    }

    /**
     * Get human-readable phase name
     */
    public static function getPhaseName(): string {
        return match(self::getCurrentPhase()) {
            0 => 'Pre-Launch Email Collection',
            1 => 'Private Beta with Viral Invites',
            2 => 'Community & Shop Partnerships',
            3 => 'Public Launch',
            default => 'Unknown Phase'
        };
    }

    /**
     * Get phase configuration as array (useful for API responses)
     */
    public static function getConfig(): array {
        $phase = self::getCurrentPhase();
        return [
            'phase' => $phase,
            'phase_name' => self::getPhaseName(),
            'registration_open' => self::isRegistrationOpen(),
            'requires_invite' => self::requiresInviteCode(),
            'payment_active' => self::isPaymentActive(),
            'viral_invites' => self::hasViralInvites(),
            'shop_registration' => self::hasShopRegistration(),
            'founding_member_price' => self::getFoundingMemberPrice() / 100, // Convert to euros
        ];
    }
}
