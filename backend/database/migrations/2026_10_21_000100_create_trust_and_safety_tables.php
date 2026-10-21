<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 7: player reports and moderation, blocking, and versioned policy pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 10); // user | team | app (a problem with the app, or feedback)
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_team_id')->nullable()->constrained('teams')->nullOnDelete();
            // "user:12" / "team:5"; NULL for app reports. One OPEN report per reporter and subject.
            $table->string('dedupe_key', 40)->nullable();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            // What the reported profile or team looked like when reported (it may change later).
            $table->json('snapshot')->nullable();
            // App reports: app version, platform, language. Never secrets.
            $table->json('context')->nullable();
            $table->string('status', 20)->default('open'); // open | actioned | dismissed
            $table->string('action', 30)->nullable(); // see App\Enums\ModerationAction
            // Internal: never shown to the reporter or the reported player.
            $table->text('resolution_note')->nullable();
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('subject_user_id');
            $table->index('subject_team_id');
        });
        DB::statement("CREATE UNIQUE INDEX reports_one_open_per_subject ON reports (reporter_id, dedupe_key) WHERE status = 'open'");

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        // Draft text lives on `pages`; each publish copies it into an append-only `page_versions` row.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique(); // terms | privacy | community-guidelines
            $table->json('title_translations'); // {"en": "...", "ar": "...", "kmr": "..."}
            $table->json('body_translations');
            $table->boolean('requires_legal_review')->default(true);
            $table->unsignedInteger('current_version')->default(0); // 0 = never published
            $table->timestampTz('published_at')->nullable();
            // A legal review is recorded by staff for one exact version; publishing again resets it.
            $table->unsignedInteger('legal_reviewed_version')->nullable();
            $table->timestampTz('legal_reviewed_at')->nullable();
            $table->string('legal_reviewer', 160)->nullable();
            $table->text('legal_review_note')->nullable();
            $table->foreignId('legal_recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('title_translations');
            $table->json('body_translations');
            $table->string('change_note', 500);
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at');
            $table->unique(['page_id', 'version']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_subject_check CHECK (subject_type IN ('user','team','app'))");
            DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('open','actioned','dismissed'))");
            DB::statement('ALTER TABLE user_blocks ADD CONSTRAINT user_blocks_not_self CHECK (blocker_id <> blocked_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('reports');
    }
};
