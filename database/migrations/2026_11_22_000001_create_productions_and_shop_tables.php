<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('topic')->nullable();
            $table->text('summary')->nullable();
            $table->string('cover_image')->nullable();
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();
            $table->json('performances')->nullable();
            $table->string('date_label', 60)->nullable();
            $table->string('venue')->nullable();
            $table->string('ticket_url')->nullable();
            $table->string('charity')->nullable();
            $table->json('gallery')->nullable();
            $table->json('blocks')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('sold_out')->default(false);
            $table->boolean('track_stock')->default(false);
            $table->integer('stock')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_tables', function (Blueprint $table) {
            $table->id();
            $table->string('row', 5);
            $table->unsignedSmallInteger('number');
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['row', 'number']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('token', 40)->unique();
            $table->foreignId('shop_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_label', 30);
            $table->string('customer_name', 100);
            $table->string('note', 500)->nullable();
            $table->string('payment_method', 20)->default('bonnen');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 20)->default('open')->index();
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('total_eur', 10, 2)->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_transaction')->nullable();
            $table->string('checkout_url', 1000)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('price', 8, 2);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shop_tables');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shop_categories');
        Schema::dropIfExists('productions');
    }
};
