<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            
            // Lock Details
            $table->decimal('amount', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->string('lock_type')->default('ORDER_PAYMENT');
            
            // Timestamps
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('wallet_id');
            $table->index('locked_at');
            $table->index(['order_id', 'lock_type']);
        });
        
        // Add check constraints using raw SQL
        DB::statement("ALTER TABLE escrow_locks ADD CONSTRAINT chk_escrow_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE escrow_locks ADD CONSTRAINT chk_platform_fee_non_negative CHECK (platform_fee >= 0)");
        DB::statement("
            ALTER TABLE escrow_locks 
            ADD CONSTRAINT escrow_locks_type_check 
            CHECK (lock_type IN ('ORDER_PAYMENT', 'DISPUTE_HOLD'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_locks');
    }
};
