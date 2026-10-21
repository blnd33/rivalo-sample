# Staging, production, backups and pilot checklist

## Recommended hosting (small budget, scales later)

| Component | Option | Notes |
|---|---|---|
| App server | 1 Linux VPS (2 vCPU / 4 GB), Ubuntu LTS, Nginx + PHP-FPM 8.4/8.5 | Or a Laravel-focused PaaS such as Laravel Cloud or Forge-managed servers. |
| Database | Managed PostgreSQL 16+ with daily backups and point-in-time recovery | Keep it in the same region as the app server. |
| Storage | S3-compatible bucket (Cloudflare R2, DigitalOcean Spaces, AWS S3) | Public bucket or CDN for avatars and covers; a **private** bucket for evidence and identity data (M5+). |
| Mail | Any SMTP provider with SPF, DKIM and DMARC on your domain | |
| TLS | Let's Encrypt (Certbot) or the platform's managed TLS | HTTPS is required: release mobile builds refuse plain HTTP. |
| Monitoring | Health URL `/up` with an uptime monitor, plus an error tracker (Sentry, Flare or similar) | Never send passwords, tokens or private profile fields to trackers. |

## Environment differences

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database   # or redis
MAIL_MAILER=smtp
TRUSTED_PROXIES=<load balancer IPs, if any>
RIVALO_SUPPORT_EMAIL=support@example.com
```

`SampleDataSeeder` refuses to run in production. Seed reference data only:

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=FeatureFlagSeeder --force
php artisan db:seed --class=SportsCatalogSeeder --force
php artisan optimize
php artisan filament:optimize
```

## Processes (systemd or Supervisor)
- `php artisan queue:work --tries=3 --max-time=3600`, restarted on deploy with `php artisan queue:restart`
- Cron: `* * * * * cd /var/www/rivalo/backend && php artisan schedule:run >> /dev/null 2>&1`

## Backups and restoration
1. **Database:** managed daily snapshots plus point-in-time recovery, with at least 14 days of retention. Also take a weekly logical dump (`pg_dump -Fc`) to a separate storage account.
2. **Media:** enable bucket versioning, and lifecycle rules for old versions.
3. **Secrets:** keep `.env` values in a password manager or secret store, not in the repository.
4. **Restore drill (do this before the pilot and then quarterly):**
   - Restore the latest snapshot to a *new* database instance.
   - Point a staging copy of the app at it and run `php artisan migrate:status`.
   - Check a sample of users, tournaments, entries and audit logs.
   - Record how long the restore took and what data was lost (this is your RTO and RPO).

## Phone notifications (Firebase)

The inbox works without any of this. Phone notifications need a Firebase project owned by RIVALO:

1. Create a Firebase project. Add an Android app (package name from `mobile/android/app/build.gradle`) and an iOS app (bundle ID). Download `google-services.json` (Android) and `GoogleService-Info.plist` (iOS). These identify the app; they are not server secrets.
2. iOS: create an APNs authentication key in the Apple Developer account and upload it in Firebase → Project settings → Cloud Messaging.
3. App: add `firebase_core` and `firebase_messaging`, implement a `FirebasePushTokenSource` (see `mobile/lib/core/push/push_service.dart`) that requests permission and returns the FCM token, and override `pushTokenSourceProvider` in `main.dart`. The app already registers the token with `POST /api/v1/me/devices`.
4. Server: create a service account with the "Firebase Cloud Messaging API Admin" role, store its JSON **on the server only** (outside the web root, readable by the PHP user), and set `PUSH_DRIVER=fcm`, `FCM_PROJECT_ID`, `FCM_CREDENTIALS_PATH`. Never commit the JSON or put it in the app.
5. Keep `php artisan queue:work` and the scheduler running: push, announcements and reminders are queued.

## Mobile release
- Android: set a real `applicationId` (currently `app.rivalo.rivalo` as a placeholder), create an upload keystore stored outside the repo, then `flutter build appbundle --dart-define=API_BASE_URL=https://api.example.com/api/v1`.
- iOS: on macOS with Xcode, set the bundle identifier and team, then `flutter build ipa --dart-define=API_BASE_URL=...`.
- Store listings must describe the app accurately. Paid-entry tournaments stay disabled server-side until eligibility is confirmed (decision D7). The flag is never used to hide features from review.

## Before real players: launch check

```bash
php artisan rivalo:launch-check
```

Fix every FAIL. It checks APP_DEBUG/HTTPS/secure cookies, PostgreSQL, queue and mail drivers, support address, GD and intl, Firebase credentials location, the scheduler heartbeat, stuck or failed jobs, the storage link, sample data, staff MFA, points tables, the store and marketplace switches, and the policy pages (published, legal review recorded, no draft placeholders left; "Selling on RIVALO" too while the marketplace is on). See docs/08-SECURITY.md.

Photos under `/storage` are served by the web server, so add the same basic header there, for example in nginx: `location /storage/ { add_header X-Content-Type-Options nosniff always; }`.

## Manual pilot checklist (Milestone 7 gate)

The step-by-step plan for the event itself is in [09-PILOT-RUNBOOK.md](09-PILOT-RUNBOOK.md).

**Accounts**
- [ ] Register on Android and on iPhone, receive the verification email, and verify from a phone browser.
- [ ] Password reset email arrives, the link works, and other devices are signed out.
- [ ] "Sign out of all devices" works. A suspended account is blocked immediately.
- [ ] Account deletion schedules, can be cancelled, and the account is anonymized after 14 days (test with a staging clock).

**Teams and registration**
- [ ] Create a padel pair, invite a partner, the partner accepts, then register for a free tournament.
- [ ] Registering past capacity puts the entry on the waitlist, and cancelling promotes the next entry.
- [ ] A player on two teams can't be on both rosters in the same tournament.
- [ ] Captain transfer requires the nominee to accept.

**Admin**
- [ ] Staff must enroll MFA. An organizer sees only assigned tournaments.
- [ ] Publishing a paid tournament is refused while the paid-entry flag is off.
- [ ] Every status change and locked-rule correction appears in Audit Logs with a reason.
- [ ] Dashboard counts match the database, and sample data is labeled.

**Check-in, money and competition**
- [ ] Staff scan a player's QR code with a phone camera and check them in; a duplicate scan is refused.
- [ ] A cash payment gets a receipt number; voiding needs a reason; the finance page matches the cash in the box.
- [ ] The draw is made after registration closes, published, and players see their matches.
- [ ] A player enters a score, the referee approves it, the bracket advances; a dispute is raised and resolved with a note.
- [ ] Final placements confirmed; points (only with a real published table) and badges appear on profiles.

**Store (only if `store_enabled` is on for the pilot)**
- [ ] Real products with photos, sizes and stock; pickup point with opening hours; delivery areas and fees only if you deliver.
- [ ] Order from a phone, confirm and mark ready in the admin panel, collect it, record the cash with "Handed over and paid"; the sales report matches the cash.
- [ ] Cancel an order and see the stock come back; record a refund and a return.

**Marketplace (only if `marketplace_enabled` is on for the pilot)**
- [ ] "Selling on RIVALO" terms completed and reviewed (D24); commission and payout terms agreed (D23).
- [ ] A seller applies in the app; staff approve with the signed agreement's commission; the seller signs in to the Seller Hub with an authenticator app.
- [ ] The seller adds a product with photos and sizes and submits it; staff approve it; staff record the stock the seller brought.
- [ ] A customer orders it; after "Handed over and paid" the sale (minus commission) appears on the seller's statement; a payout is recorded with its reference and the balance drops.

**Safety and messages**
- [ ] A player reports another player; it appears in Community → Reports; staff act with a reason; both players get the right inbox message.
- [ ] Blocking hides players from each other's search and stops invitations.
- [ ] Policy pages open from the sign-up form and Settings; Terms and Privacy show "Draft: pending legal review" until the review is recorded.
- [ ] Inbox receives draw, reminders and results; phone notifications arrive if Firebase is configured.

**Quality**
- [ ] Largest text size and screen reader (VoiceOver and TalkBack) pass through sign-in, registration and team invite.
- [ ] Arabic switches to right-to-left with no clipped text, and Kurdish shows no English text (after native review: docs/translation-review).
- [ ] Airplane mode shows offline states, not crashes.
- [ ] Restore drill completed and documented.

**Owner and legal sign-off before public launch (see 05-DECISIONS.md)**
- [ ] D6 jurisdiction and age · D7 paid entry · Terms and Privacy reviewed, placeholders completed, review recorded in the admin panel · support inbox monitored · `rivalo:launch-check` without FAIL · independent penetration test of staging.
