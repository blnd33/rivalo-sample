# RIVALO — Architecture (Milestone 1)

> **Play. Win. Rise.**

## 1. What Rivalo is

Rivalo is a multi-sport tournament platform run by one organization. It covers football, padel, cue sports and e-sports. Players create accounts, pick the disciplines they play, form teams and register for **our** tournaments. Staff check entrants in at the venue, record any in-person payment, run fixtures and approve results. Only approved results from our tournaments earn **tournament points**, which feed discipline-specific leaderboards, public profiles and achievements. News, announcements and notifications keep the community informed. An official store (M8) and approved outside sellers (M9) sell merchandise for cash at pickup or delivery; sellers bring their stock to RIVALO and are paid from a recorded statement.

Rivalo does **not** let users publish tournaments. It is **not** a skill rating, and it has **no** betting, wallets, purchasable points or online entry-fee checkout.

## 2. System overview

```
 ┌───────────────────────┐        HTTPS/JSON (Bearer token)        ┌──────────────────────────────┐
 │ Flutter app            │ ─────────────────────────────────────▶ │ Laravel 13 API  (/api/v1)     │
 │ Android + iOS          │                                        │  ├─ Sanctum personal tokens   │
 │ Riverpod · go_router   │ ◀───────── push (FCM/APNs, later) ──── │  ├─ Policies + permissions    │
 └───────────────────────┘                                         │  ├─ Domain services (tx-safe) │
                                                                   │  └─ Jobs / scheduler          │
 ┌───────────────────────┐      HTTPS (session + CSRF + MFA)       │                              │
 │ Admin panel (browser)  │ ─────────────────────────────────────▶ │ Filament 5 panel (/admin)    │
 └───────────────────────┘                                         └──────────────┬───────────────┘
                                                                                  │
                     ┌──────────────────────┬─────────────────────┬───────────────┴──────┐
                     │ PostgreSQL 16+       │ Queue worker        │ Object storage        │
                     │ (source of truth)    │ (database → Redis)  │ (local → S3-compatible)│
                     └──────────────────────┴─────────────────────┴──────────────────────┘
```

One Laravel application serves both the mobile API and the admin panel. They share the domain layer (models, services, policies), so every business rule is written **once** and enforced on the server.

## 3. Technology decisions

| Area | Choice | Why |
|---|---|---|
| Mobile | **Flutter 3.41 (Dart 3.11)**, one codebase for Android and iOS | One team and one codebase. Flutter supports native-feeling iOS behavior (Cupertino transitions, swipe-back, haptics, SF Pro) as well as Android. |
| Mobile state | **Riverpod 3** (`flutter_riverpod`, no code generation) | Compile-safe dependency injection. `AsyncValue` gives loading, data and error states without boilerplate, and providers are easy to override in tests. We skip codegen so Windows developers don't need to run `build_runner`. |
| Mobile routing | **go_router** with `StatefulShellRoute` | Declarative deep links (`/tournaments/42`). Each tab keeps its own navigation stack, as in native iOS tab bars, and auth redirects live in one place. |
| HTTP | **dio** | Interceptors for the bearer token, locale header, timeouts and typed error mapping. |
| Secure storage | **flutter_secure_storage** | Tokens go in the iOS Keychain or Android Keystore, never in SharedPreferences. |
| Models | Hand-written immutable Dart classes with `fromJson` | Readable, with no generator step. Server Resources define the contract. |
| Backend | **Laravel 13** on **PHP 8.3+** (tested on 8.5) | Mature, with first-party queues, scheduler, mail, notifications, rate limiting and storage abstraction. |
| Mobile auth | **Laravel Sanctum 4** personal access tokens, one per device | Tokens can be revoked individually ("sign out this device") or all at once. |
| Permissions | **spatie/laravel-permission 8** | Explicit named permissions, with roles as bundles of permissions. Policies check *permission + scope* (for example, organizer **and** assigned to this tournament). |
| Admin panel | **Filament 5** | Maintained, Laravel-native admin framework with tables, forms, filters, relation managers, keyboard support, a dark theme, and built-in multi-factor authentication (TOTP), which we **require** for staff. It runs on the same policies as the API. |
| Database | **PostgreSQL 16+** | Transactional DDL, partial unique indexes (used to prevent conflicting registrations), `CHECK` constraints, reliable `SELECT … FOR UPDATE`, and JSONB. There is no reason to choose MySQL here. |
| Queue | `database` driver to start, Redis later | No extra infrastructure on day one, and it switches with an env var. |
| Media | Laravel filesystem with a `public` disk (avatars, logos, covers) and a `private` disk (evidence, ID data) | `local` in development, any S3-compatible bucket in production (AWS S3, Cloudflare R2, DigitalOcean Spaces, MinIO). |
| Push | `PushProvider` interface, `log` driver by default | FCM/APNs is added later when credentials exist. The in-app inbox always works without push. |
| Money | Integer minor units (`bigint`) plus an explicit ISO-4217 `currency` | No floating-point money. IQD has 0 minor digits by ISO definition, so `25000` means 25,000 IQD. |

### Apple-quality user experience (requested)
The app follows Apple's Human Interface Guidelines on iOS and still feels at home on Android:
- Large collapsing titles (`CupertinoSliverNavigationBar` style) on top-level screens.
- A frosted-glass tab bar and navigation bar (blur over 72% opaque navy).
- Cupertino page transitions with edge swipe-back on every route.
- The HIG Dynamic Type scale (34/28/22/20/17/…), rendered in the system font (SF Pro on iPhone) and scaling with the user's text-size setting.
- Grouped inset lists for settings, privacy and account screens.
- Light haptics on tab changes and primary actions. Bottom sheets with grabbers for secondary flows.
- Adaptive controls (`Switch.adaptive`, `showAdaptiveDialog`, adaptive progress indicators).
- 44pt minimum touch targets, semantic labels on every icon button, status shown as text plus icon (never color alone), and reduced motion respected.

## 4. Backend layering

```
app/
  Enums/                 PHP backed enums (statuses, roles, types). Single source for allowed values.
  Models/                Eloquent models with relationships, casts and scopes. No business workflows.
  Services/              Transactional domain operations (RegistrationService, TeamService, …).
                         Every service that changes capacity, money or points runs in DB::transaction()
                         with row locks (lockForUpdate) and writes an AuditLog entry.
  Policies/              Authorization = permission + scope. Used by the API and Filament.
  Http/Controllers/Api/V1  Thin controllers: validate → authorize → call service → return Resource.
  Http/Requests/         FormRequest validation.
  Http/Resources/        Explicit public vs private API shapes (PublicProfileResource ≠ MeResource).
  Filament/              Admin resources, pages and widgets.
  Support/               Money, FeatureFlags, Audit helpers.
```

**Rules**
1. Controllers never touch business state directly. They call a service.
2. Every write that affects capacity, eligibility, money or points goes through one service method inside one transaction.
3. Every privileged write records `audit_logs` (actor, action, subject, before/after, reason, IP).
4. Public API resources whitelist fields, so private fields can't leak by accident.
5. Feature flags (`feature_flags` table) are read on the server. The client only *displays* what the server allows. Flags are for compliance gating and staged rollout, never for hiding features from app review.

## 5. Security baseline
- Passwords are hashed with bcrypt or argon2 (Laravel default), with a minimum of 10 characters and a compromised-password check in production.
- Rate limits: login 5/min per email+IP, registration 10/hour per IP, password reset 5/hour, general API 120/min per user.
- Admin panel: separate session guard, CSRF protection, **TOTP MFA required for every staff role**, and access only for users with the `admin.access` permission.
- Mobile: no secrets in the app. The only configuration is `API_BASE_URL` via `--dart-define`.
- Uploads: MIME and extension allow-list, size limits, re-encoded avatars, randomized file names, and evidence on the private disk served only through signed temporary URLs.
- Logs never contain passwords, tokens, payment references or private profile fields.

## 6. Repository layout

```
rivalo/
├── README.md                 Start here
├── docs/                     Architecture, database, screens, milestones, decisions, setup
├── design-system/            tokens.json (source of truth) + preview
├── website/                  Existing static coming-soon page (unchanged)
├── backend/                  Laravel 13 API + Filament admin
│   ├── app/{Enums,Models,Services,Policies,Http,Filament,Support}
│   ├── database/{migrations,seeders,factories}
│   ├── routes/{api.php,web.php,console.php}
│   └── tests/{Feature,Unit}
└── mobile/                   Flutter app (Android + iOS)
    └── lib/
        ├── main.dart
        ├── app/              App widget, router, shell (tab bar)
        ├── core/             config, network, storage, design system, l10n, utilities
        └── features/         auth, onboarding, home, tournaments, teams, profile, rankings, news, …
```

## 7. Environments
| Env | API | DB | Mail | Storage | Push |
|---|---|---|---|---|---|
| local (Windows) | `php artisan serve` | PostgreSQL (or SQLite for a quick start) | `log` | `local` | `log` |
| staging | VPS or managed PaaS | Managed PostgreSQL | SMTP provider (sandbox) | S3-compatible bucket | FCM test project |
| production | Same, behind TLS | Managed PostgreSQL with PITR backups | SMTP provider | S3-compatible bucket | FCM/APNs |

iOS builds, signing and App Store submission **require macOS with Xcode**. You can develop and test Android on Windows. For iOS you need a Mac, or a cloud macOS CI service such as Codemagic or GitHub Actions macOS runners.
