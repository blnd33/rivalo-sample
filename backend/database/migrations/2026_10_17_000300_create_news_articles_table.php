<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('summary', 300)->nullable();
            $table->text('body');
            $table->string('cover_path')->nullable();
            $table->string('category', 20)->default('announcement');
            // NULL = shown in every app language; otherwise only to readers using that language.
            $table->string('locale', 5)->nullable();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            // Featured articles appear in the photo slider on Home and News.
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
            $table->index(['is_featured', 'published_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE news_articles ADD CONSTRAINT news_articles_status_check CHECK (status IN ('draft','published','archived'))");
            DB::statement("ALTER TABLE news_articles ADD CONSTRAINT news_articles_category_check CHECK (category IN ('announcement','tournament','results','community','guide'))");
            DB::statement("ALTER TABLE news_articles ADD CONSTRAINT news_articles_locale_check CHECK (locale IS NULL OR locale IN ('en','ar','kmr'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
