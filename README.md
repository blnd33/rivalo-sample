# RIVALO

**Play. Win. Rise.** A multi-sport tournament platform: Android and iPhone apps for players, plus a web admin panel for staff.

| Folder | What it is | Start here |
|---|---|---|
| [`docs/`](docs/) | Architecture, database, screen map, milestones, open decisions, setup, deployment, security, pilot runbook, translation review | [01-ARCHITECTURE.md](docs/01-ARCHITECTURE.md) |
| [`tools/translations/`](tools/translations/) | Translation coverage check and the native-speaker review sheet (export/import) | [docs/translation-review](docs/translation-review/README.md) |
| [`design-system/`](design-system/) | Brand tokens (source of truth) shared by app and admin | [tokens.json](design-system/tokens.json) |
| [`backend/`](backend/) | Laravel 13 API (`/api/v1`), Filament 5 admin panel (`/admin`) and Seller Hub (`/seller`) | [backend/README.md](backend/README.md) |
| [`mobile/`](mobile/) | Flutter app for Android and iOS (Apple-style UX) | [mobile/README.md](mobile/README.md) |
| [`website/`](website/) | Static coming-soon page (unchanged) | [website/README.md](website/README.md) |

## Status

| Milestone | State |
|---|---|
| M1 Architecture, design system, scaffolding | ✅ Done |
| M2 Auth, roles, catalog, profiles | ✅ Done |
| M3 Teams, tournaments, registration | ✅ Done |
| M4A QR check-in, cash payments, refunds, eligibility | ✅ Done |
| News with photo slider + visual redesign (pulled forward from M6) | ✅ Done |
| M4B Sponsors, prizes, tournament money records (draft finance rules) | ✅ Done |
| M5 Fixtures, results, disputes, points, leaderboards (points tables are drafts until published) | ✅ Done |
| M6 Badges, announcements, in-app inbox, phone notifications (phone delivery needs a Firebase project) | ✅ Done |
| M7 Reports and moderation, blocking, policy pages, full Arabic/Kurdish drafts, security review, launch check, pilot runbook (the pilot event itself is yours to run) | ✅ Done |
| M8 Official store: products, sizes, stock, bag, orders, pickup or delivery, cash at handover (switched on with `store_enabled`) | ✅ Done |
| M9 Approved sellers: applications in the app, staff approval with the signed agreement's commission, reviewed listings, "Sold by" and shop pages, Seller Hub (`/seller`), seller statements and recorded payouts (switched on with `marketplace_enabled`) | ✅ Done |

Tests: **206 backend** and **53 mobile** (`flutter test`), all passing. `flutter analyze` reports no issues.
Run the backend suite with `php -d extension=intl -d extension=gd vendor/bin/phpunit` until intl is enabled in php.ini (`php artisan test` drops those flags).

## Quick start (Windows)

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

```bash
cd mobile
flutter pub get
flutter run
```

Full instructions, including the required PHP extensions, PostgreSQL, the Android emulator and iOS: [docs/06-SETUP-WINDOWS.md](docs/06-SETUP-WINDOWS.md).

## Ground rules
- Players never publish tournaments. Only staff do, through the admin panel.
- Points come only from approved RIVALO results. There are no wallets, no betting and no purchasable points.
- The official store takes **cash on pickup or delivery only**; card payments wait for a verified payment provider.
- Approved sellers bring their stock to RIVALO, which sells it the same way. Their commission comes from each signed agreement (never a default), and payouts are recorded by staff after they really pay. Nothing moves money automatically.
- Paid entry is collected in person and gated by a server flag that stays **off** until legal and app-store approval (decision D7).
- Open rules (padel "series", points tables, prize formulas and so on) are configurable drafts and are never activated on guesses. See [05-DECISIONS.md](docs/05-DECISIONS.md).
