<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 9: approved outside sellers.
 *
 * Model (decision D22): sellers apply, staff approve them with the commission from their signed
 * agreement, and sellers list products that staff review before they go on sale. Sellers bring
 * the stock to RIVALO; RIVALO sells it through the existing store (same bag, checkout, pickup or
 * delivery, cash at handover). A seller earns from a sale once the order is completed and paid;
 * returns reverse it. Payouts are recorded by staff when they actually pay; nothing is automatic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete(); // the account that runs the shop
            $table->string('slug', 80)->unique();
            $table->string('name', 120); // public shop name
            $table->json('bio_translations')->nullable();
            $table->string('logo_path')->nullable();
            // Private: staff and the seller only.
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email', 160)->nullable();
            $table->string('business_address', 255)->nullable();
            $table->string('status', 20)->default('active'); // active | suspended | closed
            $table->string('status_reason', 500)->nullable();
            // From the signed agreement; snapshotted on every order line (later changes never touch past sales).
            $table->unsignedSmallInteger('commission_bps'); // 1250 = 12.5 %
            $table->string('agreement_reference', 120);
            $table->string('verification_note', 500); // what staff checked before approving
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at');
            $table->boolean('is_sample_data')->default(false)->index();
            $table->timestampsTz();
        });

        Schema::create('seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name', 120);
            $table->string('contact_phone', 32);
            $table->string('city', 80);
            $table->string('categories', 120); // comma-separated ProductCategory values
            $table->string('description', 1000); // what they sell
            $table->string('link', 255)->nullable(); // shop page or social profile
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | withdrawn
            $table->string('decision_note', 500)->nullable(); // shown to the applicant when rejected
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
        // One application under review per person.
        DB::statement("CREATE UNIQUE INDEX seller_applications_one_pending ON seller_applications (user_id) WHERE status = 'pending'");

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('id')->constrained()->restrictOnDelete(); // NULL = official RIVALO product
            $table->string('review_note', 500)->nullable(); // staff note to the seller ("changes requested")
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['seller_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Snapshot at checkout: who sold the line and the commission agreed at that time.
            $table->foreignId('seller_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('commission_bps')->nullable();
            $table->index('seller_id');
        });

        // Append-only. A seller's balance (owed by RIVALO) = SUM(amount_minor) per currency.
        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // sale | return | adjustment | payout
            $table->char('currency', 3);
            $table->bigInteger('gross_minor')->default(0); // item value (negative for returns)
            $table->bigInteger('commission_minor')->default(0); // RIVALO's commission (negative for returns)
            $table->bigInteger('amount_minor'); // effect on what RIVALO owes the seller
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->string('payout_method', 20)->nullable(); // cash | bank_transfer | other
            $table->string('reference', 120)->nullable(); // bank transfer or receipt reference
            $table->string('note', 500)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['seller_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sellers ADD CONSTRAINT sellers_status_check CHECK (status IN ('active','suspended','closed'))");
            DB::statement('ALTER TABLE sellers ADD CONSTRAINT sellers_commission_check CHECK (commission_bps <= 10000)');
            DB::statement("ALTER TABLE seller_applications ADD CONSTRAINT seller_applications_status_check CHECK (status IN ('pending','approved','rejected','withdrawn'))");
            DB::statement("ALTER TABLE seller_ledger_entries ADD CONSTRAINT seller_ledger_type_check CHECK (type IN ('sale','return','adjustment','payout'))");
            DB::statement('ALTER TABLE seller_ledger_entries ADD CONSTRAINT seller_ledger_amount_check CHECK (amount_minor <> 0 OR gross_minor <> 0)');
            DB::statement('ALTER TABLE seller_ledger_entries ADD CONSTRAINT seller_ledger_payout_negative CHECK (type <> \'payout\' OR amount_minor < 0)');
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft','pending_review','changes_requested','active','archived'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn('commission_bps');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['review_note', 'submitted_at', 'reviewed_at']);
        });
        Schema::dropIfExists('seller_applications');
        Schema::dropIfExists('sellers');
    }
};
