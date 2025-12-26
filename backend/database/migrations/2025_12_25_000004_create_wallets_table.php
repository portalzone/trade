<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates wallets table - financial account for each user (1:1 relationship)
     * Balances are DERIVED from ledger_entries, not directly updated
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            
            // User relationship (1:1)
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            
            // Currency
            $table->string('currency', 3)->default('NGN');
            
            // Balances (derived from ledger, but cached here for performance)
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('locked_escrow_funds', 15, 2)->default(0);
            
            // Note: total_balance is a computed column in PostgreSQL
            // We'll add it via raw SQL after table creation
            
            // Status
            $table->enum('wallet_status', ['ACTIVE', 'FROZEN', 'CLOSED'])->default('ACTIVE');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('wallet_status');
            
        });

        // Add check constraints (PostgreSQL specific)
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_available_balance_nonnegative CHECK (available_balance >= 0)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_locked_funds_nonnegative CHECK (locked_escrow_funds >= 0)');
        
        // Add computed column for total_balance (PostgreSQL specific)
        DB::statement('
            ALTER TABLE wallets 
            ADD COLUMN total_balance DECIMAL(15, 2) 
            GENERATED ALWAYS AS (available_balance + locked_escrow_funds) STORED
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};