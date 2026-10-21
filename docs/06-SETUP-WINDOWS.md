# Developer setup on Windows

This guide was written against: Windows 11, PHP 8.5, Composer 2.10, Flutter 3.41 (Dart 3.11), Node 24, Laravel 13.35, Filament 5.10.

## 1. PHP extensions (one-time)

Open `C:\php-8.5.5\php.ini` (run `php --ini` to find yours) and make sure these lines are **not** commented out (no leading `;`):

```ini
extension=intl       ; required by Filament (admin tables, number formatting)
extension=gd         ; avatar re-encoding (strips photo GPS/EXIF data)
extension=pdo_pgsql  ; PostgreSQL
extension=pgsql
extension=fileinfo
extension=mbstring
extension=openssl
extension=zip
extension=sodium
```

Check with:

```bash
php -m
```

> If `intl` is missing, `php artisan test` still passes, but admin pages with tables fail with *"The intl PHP extension is required"*.

## 2. PostgreSQL (recommended)

1. Install PostgreSQL 16 or newer from postgresql.org (the Windows installer includes pgAdmin).
2. Create a database and user (in pgAdmin or psql):

```sql
CREATE USER rivalo WITH PASSWORD 'choose-a-local-password';
CREATE DATABASE rivalo OWNER rivalo;
```

**Quick start without PostgreSQL:** set `DB_CONNECTION=sqlite` in `.env` and remove the other `DB_*` lines. Everything works for local development, but use PostgreSQL before staging, because the `CHECK` constraints are PostgreSQL-only.

## 3. Backend (Laravel API + admin panel)

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Edit `.env`: database credentials, `APP_URL=http://localhost:8000`, `RIVALO_SUPPORT_EMAIL` (optional locally), and optionally `SAMPLE_DATA_PASSWORD`.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

- `migrate --seed` creates roles and permissions, feature flags (all **off**), the sports catalog and, when `APP_ENV=local`, clearly labeled **[SAMPLE]** data. The sample password is printed in the console (or taken from `SAMPLE_DATA_PASSWORD`).
- API: http://localhost:8000/api/v1/config
- Admin: http://localhost:8000/admin

### Create your own super admin

```bash
php artisan rivalo:make-admin you@example.com your_username
```

You'll be asked for a password, which is never stored in code. At your first admin sign-in you must enroll an authenticator app (Google Authenticator, 1Password, Microsoft Authenticator and so on). Save the recovery codes.

### Background processes (separate terminals)

```bash
php artisan queue:work
```

```bash
php artisan schedule:work
```

The queue sends verification and reset emails, announcements and phone notifications. The scheduler also sends match and check-in reminders every 5 minutes and scheduled announcements every minute. With `MAIL_MAILER=log`, emails are written to `storage/logs/laravel.log`, so you can open verification links from there. The scheduler anonymizes accounts after their deletion grace period, prunes expired tokens, signs out app sessions unused for 180 days, and records a heartbeat that `php artisan rivalo:launch-check` and the admin dashboard use to confirm it is running.

**After updating to a new milestone:** run `php artisan migrate`, then `php artisan db:seed --class=RolesAndPermissionsSeeder` (new permissions such as `legal.record`) and `php artisan db:seed --class=PolicyPagesSeeder` (creates the policy page drafts if missing; never overwrites edits).

**Store (M8) in development:** the sample seeder creates [SAMPLE] products, a pickup point and a delivery area, and switches `store_enabled` on **in the development database only**. On a real server the flag stays off until you switch it on in Admin → Platform → Feature flags.

**Marketplace (M9) in development:** the sample seeder approves two fictional [SAMPLE] sellers through the real services (15 % placeholder commission), adds their products (one on sale and one waiting for review each), completes one order so a statement has a sale and a payout, files one pending application from `sample_player02`, and switches `marketplace_enabled` on **in the development database only**. The Seller Hub is at http://localhost:8000/seller: sign in as `sample_seller_cues@sample.rivalo.test` with the sample password and set up an authenticator app (required, like the admin panel). Set `SAMPLE_DATA_PASSWORD` in `.env` before seeding if you want a password you know.

**Translations:** `py tools/translations/check_server_strings.py` lists server text without Arabic or Kurdish; `py tools/translations/review_sheet.py export` rebuilds the native-speaker review sheet.

### Tests and formatting

```bash
php artisan test
```

```bash
vendor/bin/pint
```

## 4. Mobile app (Flutter)

### Android on Windows
1. Install **Android Studio** and, in its SDK Manager, the *Android SDK Command-line Tools*. Then run `flutter doctor --android-licenses`.
2. Create an emulator (Device Manager → Pixel, a recent API level).
3. Start the backend (`php artisan serve`). The emulator reaches your PC at `10.0.2.2`, which is the default in `lib/core/config/env.dart`.

```bash
cd mobile
flutter pub get
flutter run
```

For a physical Android phone on the same Wi-Fi, run the API on your LAN address and point the app at it:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

### iOS
iOS builds, simulators, signing and App Store submission **require a Mac with Xcode**. Flutter can't build iOS apps on Windows. Options: a Mac (mini) on your network, or a cloud macOS CI service (Codemagic, GitHub Actions macOS runners, Bitrise). The codebase is shared, so no code changes are needed for iOS.

### Quick visual preview in a browser (development only)
A web target exists **only** for fast design review. It is not a supported product surface.

```bash
flutter build web --dart-define=API_BASE_URL=http://localhost:8000/api/v1 --dart-define=SHOW_DEV_TOOLS=true
```

Then serve `mobile/build/web` (for example `py -3 -m http.server 5180 --directory build/web`) and open it at a phone-sized window.

### Tests, analysis and localization

```bash
flutter analyze
```

```bash
flutter test
```

```bash
flutter gen-l10n
```

Edit strings in `lib/core/l10n/app_en.arb`. Arabic is in `app_ar.arb`; missing Arabic keys fall back to English until translated.

## 5. Troubleshooting

| Symptom | Fix |
|---|---|
| Android app shows "You're offline" against a local API | Make sure the API is running and the app uses `10.0.2.2` (emulator) or your PC's LAN IP (phone). Release builds require HTTPS. |
| `The intl PHP extension is required` | Enable `extension=intl` in php.ini (section 1). |
| Emails never arrive | Run `php artisan queue:work`. Locally, emails are in `storage/logs/laravel.log`. |
| Admin redirects to "Set up two-factor authentication" | This is expected: MFA is mandatory for staff. |
| `cmdline-tools component is missing` from `flutter doctor` | Install it in Android Studio → SDK Manager → SDK Tools. |
