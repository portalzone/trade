<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('verification_type', ['NIN', 'BVN'])->index();
            $table->string('identifier')->comment('Encrypted NIN/BVN number');
            $table->enum('status', [
                'pending',
                'verified',
                'failed',
                'manual_review',
                'rejected'
            ])->default('pending')->index();
            $table->string('provider')->nullable()->comment('API provider used');
            $table->json('provider_response')->nullable();
            $table->json('verified_data')->nullable()->comment('Name, DOB, etc from verification');
            $table->integer('verification_attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('admin_reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index(['user_id', 'verification_type']);
        });

        // Add KYC columns to users table if they don't exist
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'nin_verified')) {
                $table->boolean('nin_verified')->default(false)->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'bvn_verified')) {
                $table->boolean('bvn_verified')->default(false)->after('nin_verified');
            }
            // kyc_tier already exists, skip it
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nin_verified')) {
                $table->dropColumn('nin_verified');
            }
            if (Schema::hasColumn('users', 'bvn_verified')) {
                $table->dropColumn('bvn_verified');
            }
        });
        
        Schema::dropIfExists('kyc_verifications');
    }
};
