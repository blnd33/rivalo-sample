<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 6: achievements (badges), in-app inbox, announcements and push device tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 80);
            $table->json('name_translations')->nullable();
            $table->string('description', 200);
            $table->json('description_translations')->nullable();
            $table->string('icon', 30)->default('rosette');
            // Awarded automatically when a user's real record reaches the threshold.
            $table->string('criteria_type', 30); // matches_played | matches_won | tournaments_completed | podium_finishes | tournament_wins | check_ins | manual
            $table->unsignedInteger('threshold')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active'); // draft | active | retired
            $table->timestampsTz();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // auto | manual
            $table->foreignId('awarded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('awarded_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            // The notification-preference key that controls phone notifications for this message.
            $table->string('category', 40);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('link_kind', 20)->nullable(); // tournament | match | team | invitations | news | achievements | announcement
            $table->string('link_ref', 120)->nullable();
            $table->json('data')->nullable();
            // Same event never notifies a user twice (retries, reminders).
            $table->string('dedupe_key', 120)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->string('push_status', 20)->nullable(); // queued | sent | skipped_preference | no_device | failed
            $table->timestampTz('pushed_at')->nullable();
            $table->foreignId('announcement_id')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('category', 40)->default('news');
            $table->string('audience', 30)->default('everyone'); // everyone | discipline | tournament_entrants
            $table->unsignedBigInteger('audience_ref')->nullable();
            $table->string('locale', 5)->nullable(); // null = all app languages
            $table->string('link_kind', 20)->nullable();
            $table->string('link_ref', 120)->nullable();
            $table->boolean('send_push')->default(true);
            $table->string('status', 20)->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->timestampTz('send_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
            $table->index(['status', 'send_at']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Signing out of a device (deleting its Sanctum token) removes its push token too.
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 10); // ios | android | web
            $table->string('app_version', 20)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE achievements ADD CONSTRAINT ach_status_check CHECK (status IN ('draft','active','retired'))");
            DB::statement("ALTER TABLE achievements ADD CONSTRAINT ach_criteria_check CHECK (criteria_type IN ('matches_played','matches_won','tournaments_completed','podium_finishes','tournament_wins','check_ins','manual'))");
            DB::statement("ALTER TABLE announcements ADD CONSTRAINT ann_status_check CHECK (status IN ('draft','scheduled','sending','sent','cancelled'))");
            DB::statement("ALTER TABLE announcements ADD CONSTRAINT ann_audience_check CHECK (audience IN ('everyone','discipline','tournament_entrants'))");
            DB::statement("ALTER TABLE device_tokens ADD CONSTRAINT dt_platform_check CHECK (platform IN ('ios','android','web'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
