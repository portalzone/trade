<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Beneficial Owners (UBOs) - separate from directors
        Schema::create('beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_verification_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('nin', 11)->nullable();
            $table->string('bvn', 11)->nullable();
            $table->string('passport_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('residential_address')->nullable();
            $table->integer('ownership_percentage'); // Must be > 25%
            $table->enum('ownership_type', ['direct', 'indirect', 'voting_rights'])->default('direct');
            $table->boolean('is_pep')->default(false); // Politically Exposed Person
            $table->text('pep_details')->nullable();
            $table->string('id_document_type')->nullable(); // passport, national_id, drivers_license
            $table->string('id_document_path')->nullable();
            $table->string('id_document_url')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->string('proof_of_address_url')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('sanctions_cleared')->default(false);
            $table->timestamps();
        });

        // Sanctions Screening Results
        Schema::create('sanctions_screening_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_verification_id')->constrained()->onDelete('cascade');
            $table->foreignId('beneficial_owner_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('screened_name');
            $table->enum('screening_type', ['individual', 'entity'])->default('individual');
            $table->enum('status', ['pending', 'clear', 'potential_match', 'confirmed_match'])->default('pending');
            $table->json('screening_lists')->nullable(); // OFAC, UN, EU, etc.
            $table->json('matches')->nullable(); // Array of potential matches
            $table->integer('match_score')->nullable(); // 0-100
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('screened_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // Enhanced Due Diligence (EDD) Reviews
        Schema::create('edd_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_verification_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'requires_clarification'])->default('not_started');
            $table->json('required_documents')->nullable(); // List of required docs
            $table->json('submitted_documents')->nullable(); // Uploaded docs
            $table->text('source_of_funds')->nullable();
            $table->text('source_of_wealth')->nullable();
            $table->text('business_model_description')->nullable();
            $table->text('expected_transaction_volume')->nullable();
            $table->text('geographic_exposure')->nullable();
            $table->boolean('premises_inspection_required')->default(false);
            $table->timestamp('premises_inspection_date')->nullable();
            $table->text('premises_inspection_notes')->nullable();
            $table->boolean('reference_checks_completed')->default(false);
            $table->json('reference_contacts')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Add tier3 specific fields to business_verifications
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->integer('total_ownership_declared')->nullable()->after('business_type');
            $table->boolean('all_ubos_identified')->default(false)->after('total_ownership_declared');
            $table->boolean('sanctions_screening_completed')->default(false)->after('all_ubos_identified');
            $table->boolean('edd_completed')->default(false)->after('sanctions_screening_completed');
            $table->timestamp('edd_started_at')->nullable()->after('edd_completed');
            $table->timestamp('edd_completed_at')->nullable()->after('edd_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'total_ownership_declared',
                'all_ubos_identified',
                'sanctions_screening_completed',
                'edd_completed',
                'edd_started_at',
                'edd_completed_at',
            ]);
        });

        Schema::dropIfExists('edd_reviews');
        Schema::dropIfExists('sanctions_screening_results');
        Schema::dropIfExists('beneficial_owners');
    }
};
