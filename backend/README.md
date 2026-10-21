# RIVALO backend

Laravel 13 · Filament 5 · Sanctum 4 · spatie/laravel-permission 8 · PostgreSQL 16+

- Mobile API: `/api/v1`, using bearer tokens (one Sanctum token per device).
- Staff admin panel: `/admin`, with session login and **mandatory TOTP MFA**.
- Health check: `/up`.

Setup: [../docs/06-SETUP-WINDOWS.md](../docs/06-SETUP-WINDOWS.md) · Deployment: [../docs/07-DEPLOYMENT-AND-PILOT.md](../docs/07-DEPLOYMENT-AND-PILOT.md)

## Layout

```
app/Enums/          Statuses, roles, permissions (single source for allowed values)
app/Models/         Eloquent models
app/Services/       Business rules, transactional and audited:
                    AccountService, TeamService, TournamentService, RegistrationService
app/Policies/       Authorization = permission + scope (shared by API and admin)
app/Http/Controllers/Api/V1/   Thin controllers
app/Http/Resources/ Whitelisted API shapes (public vs private)
app/Filament/       Admin resources, pages, widgets
app/Support/        Audit, FeatureFlags, Money
database/migrations Schema with partial unique indexes and PostgreSQL CHECK constraints
database/seeders    Reference data (production-safe) + SampleDataSeeder (local only, labeled [SAMPLE])
tests/Feature       Auth, privacy, permissions, teams, registration, tournaments, admin
```

## API overview (v1)

| Area | Endpoints |
|---|---|
| Public | `GET config`, `GET catalog/sports`, `GET users/{username}`, `GET tournaments`, `GET tournaments/{slug}`, `GET tournaments/{slug}/entries`, `GET teams/{slug}` |
| Auth | `POST auth/register`, `auth/login`, `auth/logout`, `auth/logout-all`, `auth/email/resend`, `auth/forgot-password`, `auth/reset-password` |
| Me | `GET/PATCH me`, `POST/DELETE me/avatar`, `PUT me/disciplines`, `PATCH me/privacy`, `PATCH me/notification-preferences`, `PUT me/password`, `GET me/sessions`, `DELETE me/sessions/{id}`, `POST/DELETE me/deletion-request`, `GET me/teams`, `GET me/invitations`, `GET me/registrations` |
| Teams (verified) | `POST teams`, `PATCH teams/{slug}`, `POST teams/{slug}/logo`, `POST teams/{slug}/invitations`, `DELETE teams/{slug}/invitations/{id}`, `POST teams/{slug}/leave`, `PATCH/DELETE teams/{slug}/members/{userId}`, `POST teams/{slug}/captain-transfer`, `POST teams/{slug}/disband`, `POST invitations/{id}/accept\|decline`, `POST captain-transfers/{id}/respond`, `DELETE captain-transfers/{id}` |
| Registration (verified) | `GET tournaments/{slug}/my-entry`, `POST tournaments/{slug}/registrations`, `PUT registrations/{id}/roster`, `POST registrations/{id}/cancel` |

Errors: business-rule refusals return `{"message": "...", "code": "registration_closed"}` with status 403, 404, 409 or 422. Validation errors use Laravel's `errors` object.

## Commands

```bash
php artisan test
```

```bash
vendor/bin/pint
```

```bash
php artisan rivalo:make-admin you@example.com your_username
```

```bash
php artisan rivalo:process-account-deletions
```

`dev-server.php` is a local-only router so the PHP built-in server can run with `-d extension=intl` (used by the preview launcher). Don't deploy it.
