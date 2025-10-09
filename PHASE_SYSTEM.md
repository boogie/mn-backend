# Phase System - Magicians News Launch Strategy

## Overview

The PHASE environment variable controls what features are available in the application. This allows for a controlled, gradual rollout.

## Phase Definitions

### PHASE=0 - Pre-Launch Email Collection (Current)
**Status**: Closed beta, email collection only
**Timeline**: Now - Until first 10 invited beta testers

**Available Features**:
- ✅ Email capture on homepage
- ✅ Newsletter subscription
- ✅ Registration via special invite link ONLY
- ❌ Public registration disabled
- ❌ Login page not publicly linked
- ❌ Articles behind invite wall

**Registration**:
- Special invite links: `https://magicians.news/register?invite=SECRET_TOKEN`
- Only you can generate invite tokens
- No public registration form visible

**Goal**: Collect 500+ email addresses for launch announcement

---

### PHASE=1 - Private Beta with Viral Invites
**Timeline**: After 10 beta testers - Until 100 members

**Available Features**:
- ✅ Registration via invite link (with tracking)
- ✅ Each member gets 3 invite credits
- ✅ Invite credits refill when invitees become active (post comment, like article, etc.)
- ✅ Login page accessible
- ✅ Articles readable by members
- ✅ Leaderboard: "Top Inviters" (gamification)
- ❌ Public registration still disabled
- ❌ Payment system not active yet (everyone gets free founding member status)

**Invite System**:
- Each user has `invite_credits` field (default: 3)
- User generates personal invite link: `https://magicians.news/register?invite={USER_TOKEN}`
- When someone registers via their link:
  - New user linked to inviter (`invited_by_user_id`)
  - Inviter's `invite_credits` decreased by 1
  - When invitee performs "active" action (5+ minutes reading, 1 comment, 1 like):
    - Inviter gets +1 invite credit back
    - Inviter gets notification: "Your friend is now active! You earned an invite credit."

**Goal**: Reach 100 active members through viral growth

---

### PHASE=2 - Community & Shop Partnerships
**Timeline**: After 100 members - Until 500 members

**Available Features**:
- ✅ Shop registration form (public)
- ✅ Community registration form (public)
- ✅ Approved shops/communities get QR code PDF
- ✅ QR codes track registrations (`registered_through_shop_id`)
- ✅ Shop directory page (searchable)
- ✅ Shop/community leaderboard (most registrations)
- ✅ Payment system active (€1/month for founding members)
- ✅ Birthday system active
- ❌ Public registration still not available

**Shop System**:
- Shops submit form → pending approval
- You approve → shop gets:
  - Unique referral code
  - QR code PDF poster
  - Shop profile on directory
- Users scan QR → redirected to `https://magicians.news/register?shop=SHOP_CODE`
- Track which shop brought each member

**Goal**: Reach 500 members through partnerships

---

### PHASE=3 - Public Launch
**Timeline**: After 500 members

**Available Features**:
- ✅ Public registration (no invite needed)
- ✅ Homepage shows articles preview
- ✅ SEO optimized for discoverability
- ✅ All features unlocked
- ✅ Founding member pricing ends (new members pay €4/month)
- ✅ Press release sent
- ✅ Social media campaign

**Goal**: Scale to 1,000+ members

---

## Technical Implementation

### Environment Variables

**.env** (both frontend and backend):
```bash
PHASE=0
```

### Backend: Phase Configuration

**File**: `src/PhaseConfig.php`

```php
class PhaseConfig {
    private static int $currentPhase;

    public static function getCurrentPhase(): int {
        if (!isset(self::$currentPhase)) {
            self::$currentPhase = (int)($_ENV['PHASE'] ?? 0);
        }
        return self::$currentPhase;
    }

    public static function isRegistrationOpen(): bool {
        return self::getCurrentPhase() >= 3;
    }

    public static function requiresInviteCode(): bool {
        return self::getCurrentPhase() < 3;
    }

    public static function isPaymentActive(): bool {
        return self::getCurrentPhase() >= 2;
    }

    public static function hasViralInvites(): bool {
        return self::getCurrentPhase() >= 1;
    }

    public static function hasShopRegistration(): bool {
        return self::getCurrentPhase() >= 2;
    }

    public static function getFoundingMemberPrice(): int {
        // Return price in cents
        return self::getCurrentPhase() >= 3 ? 400 : 100; // €4 or €1
    }
}
```

### Frontend: Phase Hook

**File**: `src/hooks/usePhase.ts`

```typescript
export function usePhase() {
  const phase = Number(import.meta.env.VITE_PHASE || 0)

  return {
    phase,
    isRegistrationOpen: phase >= 3,
    requiresInviteCode: phase < 3,
    isPaymentActive: phase >= 2,
    hasViralInvites: phase >= 1,
    hasShopRegistration: phase >= 2,
    foundingMemberPrice: phase >= 3 ? 4 : 1, // €4 or €1
  }
}
```

### Database Schema Updates

```sql
-- Phase 0 & 1: Invite system
ALTER TABLE users ADD COLUMN invite_credits INT DEFAULT 3;
ALTER TABLE users ADD COLUMN invited_by_user_id INT NULL;
ALTER TABLE users ADD COLUMN invite_token VARCHAR(64) UNIQUE;
ALTER TABLE users ADD COLUMN is_active_member BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD FOREIGN KEY (invited_by_user_id) REFERENCES users(id);

-- Admin invite tokens (for phase 0)
CREATE TABLE admin_invite_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(64) UNIQUE NOT NULL,
  created_by_admin INT NOT NULL,
  max_uses INT DEFAULT 1,
  current_uses INT DEFAULT 0,
  expires_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by_admin) REFERENCES users(id)
);

-- Track invite usage
CREATE TABLE invite_usage (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inviter_user_id INT NULL,
  admin_token_id INT NULL,
  new_user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inviter_user_id) REFERENCES users(id),
  FOREIGN KEY (admin_token_id) REFERENCES admin_invite_tokens(id),
  FOREIGN KEY (new_user_id) REFERENCES users(id)
);

-- Phase 2: Shops already defined in TODO.md
-- See section 3.7 for shop schema
```

---

## Registration Flow by Phase

### Phase 0: Admin Invite Only

1. **Admin creates invite token**:
   - API: `POST /api/admin/invite-token` (requires admin auth)
   - Returns: `https://magicians.news/register?invite=TOKEN`

2. **User clicks invite link**:
   - Frontend checks if `?invite=TOKEN` exists
   - If not → show "Registration not yet open, join waitlist" + email capture
   - If yes → validate token with backend
   - If valid → show registration form

3. **User registers**:
   - Backend validates invite token
   - Creates user account
   - Marks token as used
   - Links user to invite token

### Phase 1: Viral Invites

1. **Member generates invite link**:
   - API: `POST /api/invite/generate` (requires auth)
   - Check if user has `invite_credits > 0`
   - Return: `https://magicians.news/register?invite={USER_TOKEN}`

2. **New user clicks invite link**:
   - Same flow as Phase 0
   - Links `invited_by_user_id` to inviter
   - Decreases inviter's `invite_credits` by 1

3. **New user becomes active**:
   - Track engagement: reading time, comments, likes
   - When threshold met → set `is_active_member = true`
   - Increase inviter's `invite_credits` by 1
   - Send notification to inviter

### Phase 2: Shop/Community Invites

1. **Shop registers**:
   - Public form: `POST /api/shop/register`
   - Fields: name, description, website, country, city, type
   - Status: pending approval

2. **Admin approves shop**:
   - Dashboard shows pending shops
   - Approve → generates `referral_code`
   - Generates QR code PDF
   - Sends email with PDF attachment

3. **User scans QR code**:
   - QR redirects to: `https://magicians.news/register?shop=SHOP_CODE`
   - Registration form pre-fills shop info
   - User registered with `registered_through_shop_id`

### Phase 3: Public Launch

1. **Anyone can register**:
   - No invite code needed
   - Registration link in header
   - Payment required immediately (€4/month)
   - Founding members (Phase 0-2) keep €1/month forever

---

## Checklist: Moving Between Phases

### Moving to Phase 1:
- [ ] Have 10+ beta testers actively using the platform
- [ ] Invite system fully implemented and tested
- [ ] Leaderboard page built
- [ ] Email notifications for invite credits working
- [ ] Set `PHASE=1` in production
- [ ] Send email to beta testers: "You now have 3 invite credits!"

### Moving to Phase 2:
- [ ] Have 100+ active members
- [ ] Shop registration form built
- [ ] Shop approval admin interface built
- [ ] QR code generator working
- [ ] Shop directory page built
- [ ] Payment system tested and working
- [ ] Set `PHASE=2` in production
- [ ] Reach out to first 10 shops/communities

### Moving to Phase 3:
- [ ] Have 500+ members
- [ ] All features tested at scale
- [ ] Content library has 50+ articles
- [ ] Performance optimized
- [ ] SEO ready
- [ ] Press release prepared
- [ ] Set `PHASE=3` in production
- [ ] Send launch announcement to all email subscribers

---

## Current Implementation Status

**Current Phase**: 0
**Next Steps**:
1. Add PHASE env variable to both repos
2. Implement PhaseConfig.php (backend)
3. Implement usePhase hook (frontend)
4. Add invite token database tables
5. Create admin invite token generator
6. Update registration to check for invite code
7. Hide registration link in header when PHASE=0

---

## Commands

```bash
# Check current phase
echo $PHASE

# Move to next phase (in production)
gh secret set PHASE --body "1"

# Create admin invite token (via API)
curl -X POST https://api.magicians.news/api/admin/invite-token \
  -H "Authorization: Bearer YOUR_JWT" \
  -d '{"max_uses": 1, "expires_at": "2025-12-31"}'
```
