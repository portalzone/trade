<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wishlist/Favorites
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained('storefront_products')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index('user_id');
        });

        // Recently Viewed Products
        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable();
            $table->foreignId('product_id')->constrained('storefront_products')->onDelete('cascade');
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index(['user_id', 'viewed_at']);
            $table->index(['session_id', 'viewed_at']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
        Schema::dropIfExists('wishlists');
    }
};
