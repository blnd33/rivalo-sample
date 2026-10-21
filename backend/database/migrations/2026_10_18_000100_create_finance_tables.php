<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 4B: sponsors, contributions with restrictions, draft finance rules, prize tiers and
 * the append-only tournament ledger. All money is integer minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();
            // Private: never returned by the public API.
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            // The sponsor agreed to be shown in the app. Off by default.
            $table->boolean('is_public')->default(false);
            $table->string('status', 20)->default('active');
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
        });

        Schema::create('sponsor_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->restrictOnDelete();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // cash | in_kind | venue
            // Cash only: how the money may be used. Prize-only money can never fund the organizer.
            $table->string('restriction', 20)->nullable(); // unrestricted | prize_only | expenses_only
            $table->bigInteger('committed_minor')->default(0);
            $table->char('currency', 3);
            $table->string('in_kind_description')->nullable();
            $table->string('status', 24)->default('pledged'); // pledged | partially_received | received | cancelled
            $table->text('note')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
            $table->index(['tournament_id', 'status']);
        });

        Schema::create('tournament_finance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->unique()->constrained()->cascadeOnDelete();
            // Basis points (1/100 of a percent) of NET entry revenue. NULL = not decided (D4).
            $table->unsignedInteger('prize_pool_bp')->nullable();
            $table->unsignedInteger('organizer_share_bp')->nullable();
            $table->string('status', 20)->default('draft'); // draft | approved
            $table->text('notes')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('approval_note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tournament_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('place');
            $table->string('title', 80)->nullable();
            $table->string('basis', 20); // fixed | pool_share
            $table->bigInteger('amount_minor')->nullable();      // fixed
            $table->unsignedInteger('pool_share_bp')->nullable(); // pool_share
            $table->string('in_kind_description')->nullable();    // trophy, medals, equipment
            $table->string('label', 20)->default('estimated');    // estimated | finalized | guaranteed
            $table->foreignId('backing_contribution_id')->nullable()->constrained('sponsor_contributions')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['tournament_id', 'place']);
        });

        Schema::create('tournament_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('fund', 20); // unrestricted | prize | expense | organizer
            // Signed: money in is positive, money out is negative. Never zero.
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reference', 40)->unique();
            // Each entry payment/refund maps to exactly one ledger row (idempotent sync).
            $table->foreignId('entry_payment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sponsor_contribution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prize_id')->nullable()->constrained('tournament_prizes')->restrictOnDelete();
            // Each row can be reversed at most once.
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('tournament_ledger_entries')->restrictOnDelete();
            $table->uuid('transfer_group')->nullable()->index();
            $table->string('payee', 160)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at')->nullable();
            $table->index(['tournament_id', 'fund']);
            $table->index(['tournament_id', 'type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sponsors ADD CONSTRAINT sponsors_status_check CHECK (status IN ('active','inactive'))");
            DB::statement("ALTER TABLE sponsor_contributions ADD CONSTRAINT sc_type_check CHECK (type IN ('cash','in_kind','venue'))");
            DB::statement("ALTER TABLE sponsor_contributions ADD CONSTRAINT sc_restriction_check CHECK (restriction IS NULL OR restriction IN ('unrestricted','prize_only','expenses_only'))");
            DB::statement("ALTER TABLE sponsor_contributions ADD CONSTRAINT sc_status_check CHECK (status IN ('pledged','partially_received','received','cancelled'))");
            DB::statement('ALTER TABLE sponsor_contributions ADD CONSTRAINT sc_committed_check CHECK (committed_minor >= 0)');
            DB::statement("ALTER TABLE tournament_finance_rules ADD CONSTRAINT tfr_status_check CHECK (status IN ('draft','approved'))");
            DB::statement('ALTER TABLE tournament_finance_rules ADD CONSTRAINT tfr_bp_check CHECK (COALESCE(prize_pool_bp,0) + COALESCE(organizer_share_bp,0) <= 10000)');
            DB::statement("ALTER TABLE tournament_prizes ADD CONSTRAINT tp_basis_check CHECK (basis IN ('fixed','pool_share'))");
            DB::statement("ALTER TABLE tournament_prizes ADD CONSTRAINT tp_label_check CHECK (label IN ('estimated','finalized','guaranteed'))");
            DB::statement('ALTER TABLE tournament_prizes ADD CONSTRAINT tp_amount_check CHECK (amount_minor IS NULL OR amount_minor > 0)');
            DB::statement("ALTER TABLE tournament_ledger_entries ADD CONSTRAINT tle_fund_check CHECK (fund IN ('unrestricted','prize','expense','organizer'))");
            DB::statement('ALTER TABLE tournament_ledger_entries ADD CONSTRAINT tle_amount_check CHECK (amount_minor <> 0)');
        }

        // Backfill: every valid cash payment/refund recorded before M4B gets its ledger row.
        $payments = DB::table('entry_payments')->whereNull('voided_at')->orderBy('id')->get();
        $seq = [];
        foreach ($payments as $p) {
            $seq[$p->tournament_id] = ($seq[$p->tournament_id] ?? 0) + 1;
            DB::table('tournament_ledger_entries')->insert([
                'tournament_id' => $p->tournament_id,
                'type' => $p->kind === 'refund' ? 'refund' : 'entry_revenue',
                'fund' => 'unrestricted',
                'amount_minor' => $p->kind === 'refund' ? -$p->amount_minor : $p->amount_minor,
                'currency' => $p->currency,
                'reference' => sprintf('LG-%d-%04d', $p->tournament_id, $seq[$p->tournament_id]),
                'entry_payment_id' => $p->id,
                'note' => 'Backfilled from receipt '.$p->reference,
                'recorded_by_id' => $p->recorded_by_id,
                'recorded_at' => $p->recorded_at,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_ledger_entries');
        Schema::dropIfExists('tournament_prizes');
        Schema::dropIfExists('tournament_finance_rules');
        Schema::dropIfExists('sponsor_contributions');
        Schema::dropIfExists('sponsors');
    }
};
