# Phase System Implementation - Summary

## ✅ Completed

The phase-based rollout system has been implemented across both frontend and backend repositories.

### What Was Implemented

#### 1. Documentation
- ✅ **PHASE_SYSTEM.md** - Complete documentation of all phases (0-3)
  - Phase definitions and timelines
  - Feature availability by phase
  - Database schema for invite system
  - Migration checklist between phases

#### 2. Backend (mn-backend)

**Environment Configuration**:
- ✅ Added `PHASE=0` to `.env` and `.env.example`
- ✅ Set GitHub secret: `PHASE=0`

**Core Classes**:
- ✅ **PhaseConfig.php** - Central configuration class
  - `getCurrentPhase()` - Get current phase
  - `isRegistrationOpen()` - PHASE 3+ check
  - `requiresInviteCode()` - PHASE 0-2 check
  - `isPaymentActive()` - PHASE 2+ check
  - `hasViralInvites()` - PHASE 1+ check
  - `hasShopRegistration()` - PHASE 2+ check
  - `getFoundingMemberPrice()` - €1 or €4 based on phase
  - `getPhaseName()` - Human-readable phase name
  - `getConfig()` - Full config array for API responses

**Database Migrations**:
- ✅ Created `admin_invite_tokens` table for Phase 0
- ✅ Created `invite_usage` table for tracking
- ✅ Added to users table:
  - `invite_credits` (default: 3) - For Phase 1 viral invites
  - `invited_by_user_id` - Track who invited them
  - `invite_token` - Personal invite token (unique)
  - `is_active_member` - Track engagement for invite credit refill
  - `registered_through_shop_id` - For Phase 2 shop tracking

#### 3. Frontend (mn-frontend)

**Environment Configuration**:
- ✅ Added `VITE_PHASE=0` to `.env` and `.env.example`
- ✅ Set GitHub secret: `VITE_PHASE=0`

**Core Hooks**:
- ✅ **usePhase.ts** - React hook for phase detection
  - Returns phase configuration object
  - Provides feature flags for UI rendering

**Component Updates**:
- ✅ **Header.tsx** - Hide "Get Started" button in Phase 0-2
- ✅ **Register.tsx** - Check for invite code, redirect if missing in Phase 0-2

---

## Current State: PHASE 0

**What's Active**:
- ✅ Email capture on homepage (Subscribe.tsx)
- ✅ Newsletter database storage
- ✅ Registration is hidden (no "Get Started" button)
- ✅ Login is available for existing users
- ❌ Public registration is blocked

**What's Coming in Phase 1**:
- User-to-user invite system (3 credits per user)
- Invite credit refill when invitees become active
- Leaderboard for top inviters

**What's Coming in Phase 2**:
- Shop/community registration
- QR code generation for shops
- Shop directory
- Payment system activation (€1/month)
- Birthday system

**What's Coming in Phase 3**:
- Public registration (no invite needed)
- Price increase to €4/month (existing members keep €1)
- Full SEO and marketing launch

---

## How to Use the Phase System

### Check Current Phase (Backend)
```php
use MagicianNews\PhaseConfig;

$phase = PhaseConfig::getCurrentPhase(); // 0
$requiresInvite = PhaseConfig::requiresInviteCode(); // true
$config = PhaseConfig::getConfig(); // Full config array
```

### Check Current Phase (Frontend)
```typescript
import { usePhase } from '../hooks/usePhase'

function MyComponent() {
  const { phase, requiresInviteCode, hasViralInvites } = usePhase()

  return (
    <div>
      {requiresInviteCode ? (
        <p>Registration requires an invite</p>
      ) : (
        <button>Sign Up</button>
      )}
    </div>
  )
}
```

### Moving to Next Phase

1. **Backend**: Update GitHub secret
   ```bash
   gh secret set PHASE --body "1"
   ```

2. **Frontend**: Update GitHub secret
   ```bash
   cd /Users/boogie/Workspace/mn-frontend
   gh secret set VITE_PHASE --body "1"
   ```

3. **Local Development**: Update .env files
   ```bash
   # backend/.env
   PHASE=1

   # frontend/.env
   VITE_PHASE=1
   ```

---

## Next Steps to Complete Phase 0

Before moving to Phase 1, you need to implement:

### 1. Admin Invite Token Generator
- [ ] Create API endpoint: `POST /api/admin/invite-token`
- [ ] Admin-only authentication check
- [ ] Generate secure token
- [ ] Store in `admin_invite_tokens` table
- [ ] Return invite URL: `https://magicians.news/register?invite=TOKEN`

### 2. Invite Token Validation (Backend)
- [ ] Update `Auth::register()` to accept invite token parameter
- [ ] Validate token exists and is not expired
- [ ] Check `max_uses` vs `current_uses`
- [ ] Increment `current_uses`
- [ ] Link new user to invite token

### 3. Frontend Invite Flow
- [ ] Update `api.ts` to send invite token in registration
- [ ] Update `Register.tsx` to pass invite code to API
- [ ] Show error if invalid/expired invite code

### 4. Testing Phase 0
- [ ] Generate admin invite token
- [ ] Test registration with valid invite
- [ ] Test registration without invite (should be blocked)
- [ ] Verify user is linked to invite token in database

---

## Files Modified

### Backend
1. `/Users/boogie/Workspace/mn-backend/.env` - Added PHASE=0
2. `/Users/boogie/Workspace/mn-backend/.env.example` - Added PHASE=0
3. `/Users/boogie/Workspace/mn-backend/src/PhaseConfig.php` - **NEW**
4. `/Users/boogie/Workspace/mn-backend/src/Database.php` - Added migrations
5. `/Users/boogie/Workspace/mn-backend/PHASE_SYSTEM.md` - **NEW**
6. `/Users/boogie/Workspace/mn-backend/PHASE_IMPLEMENTATION_SUMMARY.md` - **NEW** (this file)

### Frontend
7. `/Users/boogie/Workspace/mn-frontend/.env` - Added VITE_PHASE=0
8. `/Users/boogie/Workspace/mn-frontend/.env.example` - Added VITE_PHASE=0
9. `/Users/boogie/Workspace/mn-frontend/src/hooks/usePhase.ts` - **NEW**
10. `/Users/boogie/Workspace/mn-frontend/src/components/Header.tsx` - Hide register button
11. `/Users/boogie/Workspace/mn-frontend/src/pages/Register.tsx` - Check for invite code

### GitHub Secrets
12. `PHASE=0` set in mn-backend repository
13. `VITE_PHASE=0` set in mn-frontend repository

---

## Testing Instructions

### Verify Phase 0 is Active

1. **Visit homepage**: Should show email capture form
2. **Check header**: Should NOT show "Get Started" button (when not logged in)
3. **Try to visit /register directly**: Should redirect to homepage
4. **Visit /register?invite=test**: Should show registration form

### Test Email Collection
1. Submit email on homepage
2. Check database: `SELECT * FROM newsletter`
3. Should see email stored

### Test Phase Detection (Backend)
```bash
curl http://localhost:8000/api/health
# Should return: {"phase": 0, "requires_invite": true}
```

---

## FAQ

**Q: How do I create an invite link?**
A: You need to implement the admin invite token generator first (see "Next Steps" above).

**Q: Can I skip phases?**
A: Yes, just set PHASE to any number (e.g., PHASE=3 for public launch).

**Q: Will existing users be affected when moving phases?**
A: No, migrations are additive. Existing users are grandfathered.

**Q: How do I test different phases locally?**
A: Change PHASE in your local `.env` file and restart the server.

**Q: What happens if frontend and backend phases don't match?**
A: The backend phase takes precedence for security. Frontend phase only affects UI rendering.

---

## Summary

✅ Phase system infrastructure complete
✅ PHASE=0 active on both repos
⏳ Next: Implement admin invite token generator
⏳ Next: Implement invite validation in Auth.php
⏳ Next: Test Phase 0 invite flow

The foundation is ready. Now you can build the invite token system and start collecting beta users!
