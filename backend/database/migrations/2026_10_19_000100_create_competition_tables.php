<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 5: stages, groups, matches, results (versioned), disputes, points rule sets and the
 * append-only points ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // single_elimination | round_robin | groups | knockout
            $table->string('name', 80);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('status', 20)->default('draft'); // draft | published | completed
            // Draw seed (reproducible), group size, places advancing, table points, third-place match…
            $table->json('settings')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['tournament_id', 'sequence']);
        });

        Schema::create('stage_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->unsignedSmallInteger('sequence');
            $table->timestampsTz();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('position');
            $table->string('label', 40)->nullable(); // Final, Semi-final, Third place…
            $table->string('status', 20)->default('pending'); // pending | ready | completed | cancelled | bye
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('location', 120)->nullable();
            // Bracket links: the winner (and, for semi-finals with a third-place match, the loser) moves on.
            $table->foreignId('next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->unsignedTinyInteger('next_slot')->nullable();
            $table->foreignId('loser_next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->unsignedTinyInteger('loser_next_slot')->nullable();
            $table->foreignId('winner_entry_id')->nullable()->constrained('tournament_entries')->nullOnDelete();
            $table->boolean('is_draw')->default(false);
            $table->unsignedBigInteger('approved_result_id')->nullable();
            $table->timestampsTz();
            $table->index(['tournament_id', 'status']);
            $table->index(['stage_id', 'round', 'position']);
        });

        Schema::create('match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot'); // 1 | 2
            $table->foreignId('tournament_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['match_id', 'slot']);
        });

        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('outcome', 24); // normal | walkover | forfeit | no_show | disqualification | cancelled
            // {"slot1": 2, "slot2": 1, "sets": [[6,4],[3,6],[10,8]]}
            $table->json('scores')->nullable();
            $table->unsignedTinyInteger('winner_slot')->nullable(); // null = draw or cancelled
            $table->string('status', 20)->default('submitted'); // submitted | approved | rejected | superseded
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_as', 20); // participant | staff
            $table->string('note')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestampsTz();
            $table->unique(['match_id', 'version']);
            $table->index(['match_id', 'status']);
        });

        Schema::create('result_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('tournament_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('open'); // open | upheld | rejected
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();
            $table->index(['match_id', 'status']);
        });

        Schema::create('points_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('discipline_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20)->default('draft'); // draft | published | retired
            $table->text('notes')->nullable();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->string('publish_note')->nullable();
            $table->timestampsTz();
            $table->index(['discipline_id', 'season_id', 'status']);
        });

        Schema::create('points_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('points_rule_set_id')->constrained()->cascadeOnDelete();
            // participation | match_win | match_draw | match_loss | place_1 | place_2 | place_3
            $table->string('trigger', 24);
            $table->unsignedInteger('points');
            // Match triggers only: which outcomes earn the points (e.g. a walkover may not).
            $table->json('eligible_outcomes')->nullable();
            $table->timestampsTz();
            $table->unique(['points_rule_set_id', 'trigger']);
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->foreignId('tournament_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discipline_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('points_rule_set_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('rule_set_version');
            $table->foreignId('points_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('match_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('award_type', 24); // a trigger, or "reversal"
            $table->integer('points_delta');
            $table->string('reason')->nullable();
            // Same award can never be written twice (retries, double clicks, re-runs).
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('points_ledger')->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->nullable();
            $table->index(['discipline_id', 'season_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->unsignedSmallInteger('final_place')->nullable();
        });
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestampTz('placements_finalized_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE stages ADD CONSTRAINT stages_type_check CHECK (type IN ('single_elimination','round_robin','groups','knockout'))");
            DB::statement("ALTER TABLE stages ADD CONSTRAINT stages_status_check CHECK (status IN ('draft','published','completed'))");
            DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('pending','ready','completed','cancelled','bye'))");
            DB::statement('ALTER TABLE match_participants ADD CONSTRAINT mp_slot_check CHECK (slot IN (1,2))');
            DB::statement("ALTER TABLE match_results ADD CONSTRAINT mr_outcome_check CHECK (outcome IN ('normal','walkover','forfeit','no_show','disqualification','cancelled'))");
            DB::statement("ALTER TABLE match_results ADD CONSTRAINT mr_status_check CHECK (status IN ('submitted','approved','rejected','superseded'))");
            DB::statement('CREATE UNIQUE INDEX match_results_one_approved ON match_results (match_id) WHERE status = \'approved\'');
            DB::statement("ALTER TABLE result_disputes ADD CONSTRAINT rd_status_check CHECK (status IN ('open','upheld','rejected'))");
            DB::statement("ALTER TABLE points_rule_sets ADD CONSTRAINT prs_status_check CHECK (status IN ('draft','published','retired'))");
            DB::statement("ALTER TABLE points_rules ADD CONSTRAINT pr_trigger_check CHECK (trigger IN ('participation','match_win','match_draw','match_loss','place_1','place_2','place_3'))");
            DB::statement('ALTER TABLE points_ledger ADD CONSTRAINT pl_delta_check CHECK (points_delta <> 0)');
        } else {
            DB::statement("CREATE UNIQUE INDEX match_results_one_approved ON match_results (match_id) WHERE status = 'approved'");
        }
    }

    public function down(): void
    {
        Schema::table('tournaments', fn (Blueprint $t) => $t->dropColumn('placements_finalized_at'));
        Schema::table('tournament_entries', fn (Blueprint $t) => $t->dropColumn('final_place'));
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('points_rules');
        Schema::dropIfExists('points_rule_sets');
        Schema::dropIfExists('result_disputes');
        Schema::dropIfExists('match_results');
        Schema::dropIfExists('match_participants');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('stage_groups');
        Schema::dropIfExists('stages');
    }
};
