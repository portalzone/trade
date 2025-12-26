<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Who performed the action
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_email')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            // What action was performed
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            
            // Action details
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            
            // Status tracking
            $table->string('status')->default('SUCCESS');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Indexes for fast querying
            $table->index('user_id');
            $table->index('action');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
            $table->index('status');
            $table->index(['user_id', 'action', 'created_at']);
        });
        
        // Add check constraint using raw SQL
        DB::statement("
            ALTER TABLE audit_logs 
            ADD CONSTRAINT audit_logs_status_check 
            CHECK (status IN ('SUCCESS', 'FAILED', 'PENDING'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
