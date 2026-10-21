<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 4A: venue check-in and staff-recorded in-person (cash) payments.
 *
 * Payments are append-only: a mistake is corrected by voiding (with a reason) and recording
 * again; money returned is a separate "refund" row. Nothing is ever edited or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('check_in_status');
            $table->foreignId('checked_in_by_id')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            // Which roster member's QR code was scanned (identity check trail).
            $table->foreignId('checked_in_member_id')->nullable()->after('checked_in_by_id')->constrained('tournament_entry_members')->nullOnDelete();
            $table->text('eligibility_reason')->nullable()->after('eligibility_status');
        });

        Schema::create('entry_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);                 // payment | refund
            $table->string('method', 20)->default('cash');
            $table->unsignedBigInteger('amount_minor'); // always positive; kind gives direction
            $table->char('currency', 3);
            $table->string('reference', 40)->unique(); // receipt number given to the player
            $table->text('note')->nullable();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            // Corrections: a voided row stays for history; voided_* explain who and why.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'kind']);
        });

        // At most one valid (non-voided) payment per entry: prevents double-charging.
        DB::statement("CREATE UNIQUE INDEX entry_payments_one_valid_payment ON entry_payments (tournament_entry_id) WHERE kind = 'payment' AND voided_at IS NULL");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE entry_payments ADD CONSTRAINT entry_payments_kind_check CHECK (kind IN ('payment','refund'))");
            DB::statement("ALTER TABLE entry_payments ADD CONSTRAINT entry_payments_method_check CHECK (method IN ('cash'))");
            DB::statement('ALTER TABLE entry_payments ADD CONSTRAINT entry_payments_amount_check CHECK (amount_minor > 0)');
            DB::statement("ALTER TABLE entry_payments ADD CONSTRAINT entry_payments_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_payments');
        Schema::table('tournament_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_member_id');
            $table->dropConstrainedForeignId('checked_in_by_id');
            $table->dropColumn(['checked_in_at', 'eligibility_reason']);
        });
    }
};
