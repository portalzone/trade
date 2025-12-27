<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('tier', ['tier2', 'tier3'])->default('tier2')->index();
            
            // Business Information
            $table->string('business_name');
            $table->string('registration_number')->unique();
            $table->string('cac_number')->nullable();
            $table->date('registration_date')->nullable();
            $table->text('business_address');
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->enum('business_type', ['sole_proprietorship', 'limited_liability', 'partnership', 'enterprise'])->default('limited_liability');
            
            // Documents
            $table->string('cac_certificate_path')->nullable();
            $table->string('cac_certificate_url')->nullable();
            $table->string('tin_certificate_path')->nullable();
            $table->string('tin_certificate_url')->nullable();
            $table->json('additional_documents')->nullable();
            
            // Verification Status
            $table->enum('verification_status', [
                'pending',
                'under_review',
                'verified',
                'rejected',
                'requires_additional_info'
            ])->default('pending')->index();
            
            $table->text('rejection_reason')->nullable();
            $table->json('verification_notes')->nullable();
            
            // Admin Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
            
        });

        // Directors/Beneficial Owners
        Schema::create('business_directors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_verification_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('nin')->nullable();
            $table->string('bvn')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('residential_address')->nullable();
            $table->integer('ownership_percentage')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->enum('role', ['director', 'shareholder', 'beneficial_owner', 'secretary'])->default('director');
            $table->string('id_document_path')->nullable();
            $table->string('id_document_url')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('business_verification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_directors');
        Schema::dropIfExists('business_verifications');
    }
};
