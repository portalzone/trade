<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_limits', function (Blueprint $table) {
            $table->id();
            $table->integer('tier')->unique();
            $table->decimal('per_transaction_limit', 15, 2);
            $table->decimal('daily_limit', 15, 2);
            $table->decimal('monthly_limit', 15, 2);
            $table->timestamps();
        });

        // Insert tier-based limits
        DB::table('transaction_limits')->insert([
            [
                'tier' => 1,
                'per_transaction_limit' => 100000,
                'daily_limit' => 200000,
                'monthly_limit' => 500000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier' => 2,
                'per_transaction_limit' => 500000,
                'daily_limit' => 2000000,
                'monthly_limit' => 10000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tier' => 3,
                'per_transaction_limit' => 5000000,
                'daily_limit' => 20000000,
                'monthly_limit' => 100000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_limits');
    }
};
