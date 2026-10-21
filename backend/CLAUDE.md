# RIVALO backend — notes for contributors and coding agents

Laravel 13 API (`/api/v1`) + Filament 5 admin panel (`/admin`). Read `../docs/01-ARCHITECTURE.md` and `../docs/05-DECISIONS.md` first.

## Rules
- Business rules live in `app/Services/*`. Controllers and Filament pages call services; they never change capacity, status, money or points directly.
- Capacity/money/points writes run in `DB::transaction()` with `lockForUpdate()` and write `App\Support\Audit::record(...)`.
- Authorization = permission + scope, in `app/Policies/*`. Never rely on hiding UI.
- Public API shapes are whitelists (`PublicProfileResource`, `TournamentResource`). Never return a model directly.
- Money is integer minor units + ISO currency (`App\Support\Money`). No floats.
- Open decisions (D1–D16) are configurable drafts. Do not activate scoring or financial behavior based on guesses.
- `paid_entry_participation` feature flag stays off until compliance approval (D7).

## Commands
- Tests: `php artisan test` (in-memory SQLite, reference seeders run automatically).
- Format: `vendor/bin/pint`.
- Fresh dev DB with sample data: `php artisan migrate:fresh --seed`.
