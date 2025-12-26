<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Order Details
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('category')->nullable();
            $table->json('images')->nullable();
            
            // Order Status
            $table->string('order_status')->default('ACTIVE');
            
            // Timestamps for different stages
            $table->timestamp('escrow_locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('seller_id');
            $table->index('buyer_id');
            $table->index('order_status');
            $table->index('created_at');
            $table->index(['order_status', 'created_at']);
        });
        
        // Add check constraints using raw SQL
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_price_positive CHECK (price > 0)");
        DB::statement("
            ALTER TABLE orders 
            ADD CONSTRAINT orders_status_check 
            CHECK (order_status IN ('ACTIVE', 'PENDING_PAYMENT', 'IN_ESCROW', 'COMPLETED', 'CANCELLED', 'DISPUTED'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
