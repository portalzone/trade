<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('raised_by_user_id')->constrained('users')->onDelete('cascade');
            
            // Dispute Details
            $table->text('dispute_reason');
            $table->string('dispute_status')->default('OPEN');
            
            // Resolution
            $table->text('admin_notes')->nullable();
            $table->text('resolution_details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('order_id');
            $table->index('raised_by_user_id');
            $table->index('dispute_status');
            $table->index('created_at');
        });
        
        // Add check constraint using raw SQL
        DB::statement("
            ALTER TABLE disputes 
            ADD CONSTRAINT disputes_status_check 
            CHECK (dispute_status IN ('OPEN', 'UNDER_REVIEW', 'RESOLVED_BUYER', 'RESOLVED_SELLER', 'RESOLVED_REFUND'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
