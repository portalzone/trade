<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            
            // Transaction details
            $table->enum('type', ['DEPOSIT', 'WITHDRAWAL'])->index();
            $table->enum('gateway', ['PAYSTACK', 'STRIPE'])->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            
            // Gateway details
            $table->string('gateway_reference')->nullable()->unique();
            $table->json('gateway_data')->nullable();
            $table->json('bank_details')->nullable();
            
            // Status tracking
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED', 'CANCELLED'])->default('PENDING')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
