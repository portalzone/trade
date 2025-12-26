<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the users table - core entity for all personas (Buyer, Seller, Rider, Admin)
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // Authentication
            $table->string('email', 255)->unique();
            $table->string('phone_number', 20)->unique();
            $table->string('password_hash', 255);
            
            // Profile
            $table->string('full_name', 255);
            $table->string('username', 100)->unique();
            $table->string('profile_photo_url', 1024)->nullable();
            
            // User Type & KYC
            $table->enum('user_type', ['BUYER', 'SELLER', 'ADMIN', 'SUPPORT', 'MEDIATOR', 'RIDER']);
            $table->enum('kyc_status', [
                'BASIC', 
                'BASIC_VERIFIED', 
                'BUSINESS_VERIFIED', 
                'ENTERPRISE_VERIFIED', 
                'EXPRESS_VENDOR_VERIFIED'
            ])->default('BASIC');
            $table->integer('kyc_tier')->default(1);
            
            // Flags
            $table->boolean('is_express_vendor')->default(false);
            $table->boolean('is_rider')->default(false);
            $table->boolean('mfa_enabled')->default(false);
            $table->enum('mfa_method', ['SMS', 'EMAIL', 'TOTP'])->nullable();
            
            // Status
            $table->enum('account_status', [
                'ACTIVE', 
                'SUSPENDED', 
                'CLOSED', 
                'RETRAINING_REQUIRED'
            ])->default('ACTIVE');
            
            // KYC Timestamps
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_approved_at')->nullable();
            $table->timestamp('kyc_rejected_at')->nullable();
            $table->text('kyc_rejection_reason')->nullable();
            
            // Express Vendor Specific (NULL if not express vendor)
            $table->date('food_safety_cert_expiry')->nullable();
            
            // Rider Specific (NULL if not rider)
            $table->date('driver_license_expiry')->nullable();
            $table->date('background_check_expiry')->nullable();
            
            // Audit
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_location_update_at')->nullable();
            
            // Standard Laravel timestamps
            $table->timestamps();
            
            // Soft deletes (10-year retention for compliance)
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['user_type', 'account_status']);
            $table->index(['kyc_status', 'kyc_tier']);
            $table->index('created_at');
        });

        // Create partial unique indexes for non-deleted users
        DB::statement('CREATE UNIQUE INDEX users_email_unique_active ON users(email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_phone_unique_active ON users(phone_number) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
