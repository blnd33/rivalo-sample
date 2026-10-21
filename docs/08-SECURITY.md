# Security review (Milestone 7)

This is an internal review done while building RIVALO, plus automated checks that keep running in the test suite. **It is not an independent penetration test.** Before public launch, have an outside tester look at the live staging server (see "Still to do").

## How to check a server

```bash
php artisan rivalo:launch-check
```

Read-only. It prints PASS / WARN / FAIL for configuration, operations, data and policies, and exits with an error while any FAIL remains. Run it on staging before the pilot and on production before launch. Owner decisions that software can't detect are listed as TODO reminders.

## What protects what

| Area | Protection | Where |
|---|---|---|
| Player sign-in | Bearer token per device (Sanctum), stored in the phone's secure storage. "Sign out of all devices", password change and suspension revoke tokens. Tokens unused for 180 days are signed out daily (`rivalo:prune-idle-sessions`, `RIVALO_IDLE_SESSION_DAYS`). | `AuthController`, `routes/console.php` |
| Account enumeration | Same message **and same work** for unknown account and wrong password (fixed in M7: unknown accounts used to answer faster). Password reset always gives the same answer. Registration necessarily reports a taken email/username; it is limited to 10 per hour per IP. | `AuthController` |
| Brute force | Login 5/min per account+IP and 20/min per IP; registration 10/h; email 5/h; uploads 10/min; reports 3/min and 30/day; everything else 120/min. Admin sign-in uses Filament's limiter. | `AppServiceProvider` |
| Staff | Admin panel needs a verified, active staff account **and** an authenticator app (TOTP). Every admin screen has a policy (permission + scope); a test fails if a new screen is added without one. | `AdminPanelProvider`, `SecuritySweepTest` |
| Authorization | Checked on the server for every write. A test fails if any non-GET API route lacks sign-in, the suspended-account check or a rate limit. | `SecuritySweepTest` |
| Private data | Public responses are whitelists (`PublicProfileResource`, `TournamentResource`, `NewsArticleResource`). Reports never reveal the reporter; blocks are never revealed to the blocked player. Signed-in API responses carry `Cache-Control: no-store`. | Resources, `SecurityHeaders` |
| Uploads | Avatars **and team logos** are re-encoded on upload, which strips hidden data such as GPS location (team logos were stored as uploaded until M7). Size, type and dimension limits. Requires the GD extension in production (`launch-check` verifies). | `ImageSanitizer` |
| Browser headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS over HTTPS. | `SecurityHeaders` |
| Errors | With `APP_DEBUG=false`, API errors contain no stack traces or file paths (tested). Passwords and tokens are never written to logs or audit records. | `bootstrap/app.php`, `Audit` |
| Money and points | Integer amounts, database transactions with row locks, append-only ledgers with reversals, unique idempotency keys. | Finance/Points services |
| Store | No card data anywhere (cash at handover). Stock reserved under row locks in a fixed order (no overselling, no deadlocks); append-only stock history. Totals confirmed by the customer are re-checked at checkout. Orders and delivery details visible only to their customer and store staff; order endpoints rate limited; at most 3 open orders per customer. | `StoreService`, `OrderService`, `InventoryService` |
| Sellers (M9) | Seller Hub (`/seller`) requires a verified, active account with an approved shop **and** an authenticator app; sellers can't open the admin panel. Every Seller Hub screen filters by the signed-in seller's shop, and policies apply seller rules only while the Seller Hub is the current panel, so they can never widen admin or API access (tested with two shops). Sellers never see customers' details. Public shop responses are a whitelist (no contacts, commission or agreement). Staff can't review or pay their own shop. Payouts need finance permission and can't exceed what is owed; statements are append-only. | `SellerContext`, policies, `SellerService`, `SellerLedgerService` |
| Secrets | None in the repository or the app. `.env.example` only. Firebase service-account JSON stays on the server, outside the web root (`launch-check` verifies). | `config/rivalo.php` |
| Deletion | Anonymization also removes the inbox, blocks and what reports recorded about the player's profile (added in M7). | `AccountService::anonymize` |
| Dependencies | `composer audit`: no known advisories (2026-10-18). Flutter has no equivalent audit command; packages are pinned in `pubspec.lock`. | |

## Findings fixed in Milestone 7

1. **Team logos kept photo metadata.** Logos were saved exactly as uploaded, so a phone photo could publish its GPS location. They are now re-encoded like avatars.
2. **Sign-in timing revealed accounts.** An unknown account returned before the password hash check. Both paths now do one hash check.
3. **Deleted accounts left traces.** Inbox messages (which quote names), blocks and report snapshots stayed after anonymization. They are now removed or scrubbed.
4. **No browser security headers.** Added (see table).
5. **App sessions never expired.** Tokens unused for 180 days are now signed out.

## Still to do (not done, by design or because it needs you)

- **Independent penetration test** of staging before public launch.
- **Content-Security-Policy.** Not set: the admin panel (Filament/Livewire) relies on inline scripts and styles. It needs nonces and careful testing.
- **Same headers for `/storage`.** Photos are served by the web server, not Laravel; add `X-Content-Type-Options: nosniff` there (docs/07).
- **PostgreSQL-only constraints** (status checks, block-self check) are not exercised by the SQLite test suite. Run the suite once against PostgreSQL on staging.
- **Backups and a restore drill** (docs/07). A security incident without a tested restore is a data-loss incident.
- **Admin news covers** uploaded by staff are stored as uploaded (staff content, not player content). Ask staff not to upload photos with location data, or extend `ImageSanitizer` to them.
