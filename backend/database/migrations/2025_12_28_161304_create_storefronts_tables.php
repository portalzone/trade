<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Storefronts - seller's branded stores
        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('primary_color')->default('#6366f1');
            $table->string('secondary_color')->default('#8b5cf6');
            $table->string('accent_color')->default('#ec4899');
            $table->string('theme')->default('light');
            $table->string('currency')->default('NGN');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->json('social_links')->nullable();
            $table->json('business_hours')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_verified')->default(false);
            $table->integer('total_products')->default(0);
            $table->integer('total_sales')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('slug');
            $table->index('subdomain');
        });

        // Product Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['storefront_id', 'is_active']);
            $table->index('parent_id');
        });

        // Storefront Products (catalog)
        Schema::create('storefront_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('set null');
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 15, 2);
            $table->decimal('compare_at_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('track_inventory')->default(true);
            $table->enum('stock_status', ['in_stock', 'low_stock', 'out_of_stock'])->default('in_stock');
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->string('weight')->nullable();
            $table->json('dimensions')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('views_count')->default(0);
            $table->integer('sales_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['storefront_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('sku');
            $table->index('stock_status');
        });

        // Storefront Analytics
        Schema::create('storefront_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->integer('product_views')->default(0);
            $table->integer('add_to_cart')->default(0);
            $table->integer('orders')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->json('top_products')->nullable();
            $table->json('traffic_sources')->nullable();
            $table->timestamps();

            $table->unique(['storefront_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_analytics');
        Schema::dropIfExists('storefront_products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('storefronts');
    }
};
