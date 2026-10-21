<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Usernames are normalized to lowercase [a-z0-9_.] before saving, so a plain unique index is case-insensitive in practice.
            $table->string('username', 30)->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Public-facing profile (exposure controlled by `privacy`).
            $table->string('display_name', 60)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('locale', 10)->default('en');

            // Private fields: never returned by public API resources.
            $table->string('phone', 32)->nullable();

            $table->json('privacy')->nullable();
            $table->json('notification_preferences')->nullable();

            $table->string('status', 20)->default('active');
            $table->text('suspension_reason')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Filament multi-factor authentication (TOTP) for staff. Encrypted by model casts.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();

            $table->boolean('is_sample_data')->default(false)->index();
            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','suspended','pending_deletion','deleted'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format CHECK (username ~ '^[a-z0-9_.]{3,30}$')");
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
