<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seasons, tournaments, staff assignments, registrations (entries) and roster snapshots.
 * Money is stored as integer minor units with an explicit ISO-4217 currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft'); // draft | active | closed
            $table->timestamps();
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->string('cover_path')->nullable();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->foreignId('discipline_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();

            $table->string('registration_type', 20); // individual | pair | team
            $table->text('eligibility_notes')->nullable();
            $table->unsignedInteger('min_entrants')->default(2);
            $table->unsignedInteger('max_entrants');
            $table->unsignedSmallInteger('roster_min')->default(1);
            $table->unsignedSmallInteger('roster_max')->default(1);
            $table->unsignedSmallInteger('substitutes_max')->default(0);

            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('check_in_closes_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Baghdad');

            $table->string('venue_name', 120)->nullable();
            $table->string('venue_address', 255)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('online_region', 60)->nullable();
            // Shown only to staff and checked-in entrants (enforced by the API resource).
            $table->text('lobby_details')->nullable();

            $table->longText('rules')->nullable();
            $table->string('format', 30); // single_elimination | round_robin | groups_knockout
            $table->string('third_place_method', 30)->default('playoff_match');
            $table->text('third_place_notes')->nullable();

            // Financial terms. Detailed prize tiers and ledger arrive in Milestone 4.
            $table->string('funding_model', 30)->default('free'); // free | player_funded | sponsor_supported
            $table->unsignedBigInteger('entry_fee_minor')->default(0);
            $table->char('currency', 3)->default('IQD');
            $table->text('prize_rules')->nullable();
            $table->timestamp('financial_rules_locked_at')->nullable();

            // Points (Milestone 5). Locked once registration opens.
            $table->boolean('ranking_eligible')->default(false);
            $table->unsignedBigInteger('points_rule_set_id')->nullable();
            $table->timestamp('points_rules_locked_at')->nullable();

            $table->string('status', 30)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('status_reason')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample_data')->default(false)->index();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('discipline_id');
        });

        Schema::create('tournament_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // organizer | referee
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id', 'role']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('tournament_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 20); // individual | pair | team
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('display_name', 80);
            $table->foreignId('registered_by_id')->constrained('users')->restrictOnDelete();

            // Four independent status tracks.
            $table->string('registration_status', 20); // reserved | waitlisted | cancelled | withdrawn | rejected | no_show
            $table->string('payment_status', 30);      // not_required | unpaid | paid | refunded | partially_refunded
            $table->string('check_in_status', 20)->default('not_checked_in');
            $table->string('eligibility_status', 20)->default('pending'); // pending | eligible | ineligible

            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('roster_locked_at')->nullable();
            $table->unsignedInteger('seed')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'registration_status']);
        });
        DB::statement("CREATE UNIQUE INDEX tournament_entries_one_active_team ON tournament_entries (tournament_id, team_id) WHERE team_id IS NOT NULL AND registration_status IN ('reserved','waitlisted')");

        // Roster snapshot. Copied at registration so later team changes never rewrite history.
        Schema::create('tournament_entry_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20); // captain | player | substitute
            $table->string('username_snapshot', 30);
            $table->string('display_name_snapshot', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
        // A person can be in at most one active entry per tournament: blocks conflicting registrations at DB level.
        DB::statement('CREATE UNIQUE INDEX tournament_entry_members_one_active ON tournament_entry_members (tournament_id, user_id) WHERE is_active = true');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE seasons ADD CONSTRAINT seasons_dates_check CHECK (starts_on <= ends_on)');
            DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_status_check CHECK (status IN ('draft','published','registration_open','registration_closed','in_progress','completed','postponed','cancelled'))");
            DB::statement('ALTER TABLE tournaments ADD CONSTRAINT tournaments_entrants_check CHECK (min_entrants >= 2 AND min_entrants <= max_entrants)');
            DB::statement('ALTER TABLE tournaments ADD CONSTRAINT tournaments_roster_check CHECK (roster_min >= 1 AND roster_min <= roster_max)');
            DB::statement('ALTER TABLE tournaments ADD CONSTRAINT tournaments_fee_check CHECK (entry_fee_minor >= 0)');
            DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
            DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_format_check CHECK (format IN ('single_elimination','round_robin','groups_knockout'))");
            DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_regtype_check CHECK (registration_type IN ('individual','pair','team'))");
            DB::statement('ALTER TABLE tournaments ADD CONSTRAINT tournaments_reg_window_check CHECK (registration_opens_at IS NULL OR registration_closes_at IS NULL OR registration_opens_at < registration_closes_at)');
            DB::statement('ALTER TABLE tournaments ADD CONSTRAINT tournaments_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at <= ends_at)');
            DB::statement("ALTER TABLE tournament_staff ADD CONSTRAINT tournament_staff_role_check CHECK (role IN ('organizer','referee'))");
            DB::statement("ALTER TABLE tournament_entries ADD CONSTRAINT entries_registration_status_check CHECK (registration_status IN ('reserved','waitlisted','cancelled','withdrawn','rejected','no_show'))");
            DB::statement("ALTER TABLE tournament_entries ADD CONSTRAINT entries_payment_status_check CHECK (payment_status IN ('not_required','unpaid','paid','refunded','partially_refunded'))");
            DB::statement("ALTER TABLE tournament_entries ADD CONSTRAINT entries_check_in_status_check CHECK (check_in_status IN ('not_checked_in','checked_in'))");
            DB::statement("ALTER TABLE tournament_entries ADD CONSTRAINT entries_eligibility_status_check CHECK (eligibility_status IN ('pending','eligible','ineligible'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_entry_members');
        Schema::dropIfExists('tournament_entries');
        Schema::dropIfExists('tournament_staff');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('seasons');
    }
};
