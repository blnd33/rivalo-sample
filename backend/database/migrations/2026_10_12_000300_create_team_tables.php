<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teams, memberships (with history), invitations and captain transfers.
 * Partial unique indexes are supported by both PostgreSQL and SQLite (used in tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('name_normalized', 60);
            $table->string('slug', 80)->unique();
            $table->string('logo_path')->nullable();
            $table->foreignId('captain_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active'); // active | disbanded
            $table->timestamp('disbanded_at')->nullable();
            $table->boolean('is_sample_data')->default(false)->index();
            $table->timestamps();

            $table->unique(['sport_id', 'name_normalized']);
        });

        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20); // captain | member | substitute
            $table->string('status', 20)->default('active'); // active | left | removed
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->string('left_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX team_memberships_one_active ON team_memberships (team_id, user_id) WHERE status = 'active'");

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20)->default('member'); // member | substitute
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | revoked | expired
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['invited_user_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX team_invitations_one_pending ON team_invitations (team_id, invited_user_id) WHERE status = 'pending'");

        Schema::create('captain_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('initiated_by_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | cancelled | forced
            $table->text('reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX captain_transfers_one_pending ON captain_transfers (team_id) WHERE status = 'pending'");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE teams ADD CONSTRAINT teams_status_check CHECK (status IN ('active','disbanded'))");
            DB::statement("ALTER TABLE team_memberships ADD CONSTRAINT team_memberships_role_check CHECK (role IN ('captain','member','substitute'))");
            DB::statement("ALTER TABLE team_memberships ADD CONSTRAINT team_memberships_status_check CHECK (status IN ('active','left','removed'))");
            DB::statement("ALTER TABLE team_invitations ADD CONSTRAINT team_invitations_status_check CHECK (status IN ('pending','accepted','declined','revoked','expired'))");
            DB::statement("ALTER TABLE captain_transfers ADD CONSTRAINT captain_transfers_status_check CHECK (status IN ('pending','accepted','declined','cancelled','forced'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('captain_transfers');
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
    }
};
