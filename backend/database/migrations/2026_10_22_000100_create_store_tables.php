<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone 8: official RIVALO store. Products with size/option variants, stock kept as an
 * append-only movement ledger plus a locked running balance, a server-side bag, and orders paid
 * in cash on pickup or on delivery (no card payments until a provider is verified).
 * Money is integer minor units in the store currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->json('name_translations'); // {"en": "...", "ar": "...", "kmr": "..."}
            $table->json('description_translations')->nullable();
            $table->string('category', 20); // jerseys | apparel | accessories | equipment
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('draft'); // draft | active | archived
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('max_per_order')->default(5);
            $table->boolean('is_sample_data')->default(false)->index();
            $table->timestampsTz();
            $table->index(['status', 'category']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt', 160)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40)->unique();
            $table->string('option_label', 40); // "M", "XL", "One size", "Black / L"
            $table->unsignedBigInteger('price_minor')->nullable(); // NULL = product price
            // Running balance of inventory_movements, updated in the same transaction (row-locked).
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address', 255);
            $table->string('opening_hours', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
        });

        // Delivery is offered only to areas staff configure, each with its own fee. No areas = no delivery.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120); // city or district
            $table->unsignedBigInteger('fee_minor');
            $table->string('estimate', 80)->nullable(); // "1–2 days"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sample_data')->default(false);
            $table->timestampsTz();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestampsTz();
            $table->unique(['user_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique(); // ORD-000042
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('placed'); // placed | confirmed | ready | out_for_delivery | completed | cancelled
            $table->string('fulfillment', 10); // pickup | delivery
            $table->foreignId('pickup_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->restrictOnDelete();
            // Private delivery details: only the customer and store staff ever see them.
            $table->string('recipient_name', 120)->nullable();
            $table->string('recipient_phone', 32)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('payment_method', 20); // cash_on_pickup | cash_on_delivery
            $table->string('payment_status', 20)->default('unpaid'); // unpaid | paid | refunded | partially_refunded
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('amount_refunded_minor')->default(0);
            $table->string('payment_reference', 30)->nullable()->unique(); // SR-000042 receipt
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('paid_recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('placed_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('cancelled_by', 10)->nullable(); // customer | staff
            $table->boolean('is_sample_data')->default(false)->index();
            $table->timestampsTz();
            $table->index(['status', 'placed_at']);
            $table->index(['user_id', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot at purchase time: later catalog edits never change a placed order.
            $table->string('product_name', 160);
            $table->string('option_label', 40);
            $table->string('sku', 40);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedSmallInteger('returned_quantity')->default(0);
        });

        // Append-only. stock = SUM(quantity_delta) per variant.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason', 20); // restock | adjustment | order | order_cancelled | return
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['product_variant_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_non_negative CHECK (stock >= 0)');
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_nonzero CHECK (quantity_delta <> 0)');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('placed','confirmed','ready','out_for_delivery','completed','cancelled'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_check CHECK (total_minor = subtotal_minor + delivery_fee_minor)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('pickup_locations');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
