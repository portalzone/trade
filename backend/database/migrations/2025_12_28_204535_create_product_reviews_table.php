<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('storefront_products')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('rating')->unsigned()->comment('1-5 stars');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('images')->nullable()->comment('Review images');
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->text('seller_response')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id', 'product_id']);
            $table->index('rating');
            $table->unique(['user_id', 'product_id', 'order_id']);
        });

        // Review helpfulness votes
        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('product_reviews')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_helpful')->comment('true = helpful, false = not helpful');
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
            $table->index(['review_id', 'is_helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('product_reviews');
    }
};
