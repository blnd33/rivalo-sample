<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sports catalog: Sport -> Discipline/Game. Tournaments reference a discipline (Milestone 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->json('name_translations')->nullable();
            $table->string('kind', 20)->default('physical'); // physical | esport
            $table->string('icon', 40)->nullable();
            // Max active members a team in this sport may have. NULL = teams not used for this sport.
            $table->unsignedSmallInteger('team_member_limit')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->json('name_translations')->nullable();
            $table->text('description')->nullable();

            // Allowed registration types: ["individual"], ["pair"], ["team"], or a combination.
            $table->json('registration_types');
            $table->unsignedSmallInteger('team_size_min')->default(1);
            $table->unsignedSmallInteger('team_size_max')->default(1);
            $table->unsignedSmallInteger('substitutes_max')->default(0);

            // E-sports attributes (nullable for physical sports). Never hardcode an ambiguous version.
            $table->string('game_title', 120)->nullable();
            $table->string('game_version', 60)->nullable();
            $table->string('platform', 60)->nullable();
            $table->string('region', 60)->nullable();
            $table->string('match_format', 120)->nullable();
            $table->string('competition_mode', 20)->nullable(); // local | online | lan

            $table->string('ruleset_status', 30)->default('needs_confirmation'); // confirmed | needs_confirmation
            $table->text('ruleset_notes')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['sport_id', 'slug']);
            $table->index(['is_active', 'sport_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sports ADD CONSTRAINT sports_kind_check CHECK (kind IN ('physical','esport'))");
            DB::statement('ALTER TABLE disciplines ADD CONSTRAINT disciplines_team_size_check CHECK (team_size_min >= 1 AND team_size_min <= team_size_max)');
            DB::statement("ALTER TABLE disciplines ADD CONSTRAINT disciplines_ruleset_check CHECK (ruleset_status IN ('confirmed','needs_confirmation'))");
            DB::statement("ALTER TABLE disciplines ADD CONSTRAINT disciplines_mode_check CHECK (competition_mode IS NULL OR competition_mode IN ('local','online','lan'))");
        }

        Schema::create('user_disciplines', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'discipline_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_disciplines');
        Schema::dropIfExists('disciplines');
        Schema::dropIfExists('sports');
    }
};
