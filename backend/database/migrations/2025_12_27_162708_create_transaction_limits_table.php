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
        Schema::create('user_transaction_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('tier', ['tier1', 'tier2', 'tier3'])->default('tier1');
            $table->decimal('per_transaction_limit', 15, 2)->default(100000);
            $table->decimal('daily_limit', 15, 2)->default(200000);
            $table->decimal('monthly_limit', 15, 2)->default(500000);
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_transaction_limits');
    }
};
