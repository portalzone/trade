<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment links
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('slug')->unique();
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->integer('max_uses')->nullable(); // null = unlimited
            $table->integer('current_uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('slug');
            $table->index('status');
        });

        // Payment link transactions
        Schema::create('payment_link_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_link_id')->constrained()->onDelete('cascade');
            $table->foreignId('payer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_phone')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index('payment_link_id');
            $table->index('reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_link_payments');
        Schema::dropIfExists('payment_links');
    }
};
