<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compliance reports
        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type'); // CBN_monthly, quarterly_risk, annual_kyc, etc.
            $table->string('report_period'); // 2024-Q1, 2024-12, etc.
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft'); // draft, submitted, acknowledged
            $table->json('report_data'); // Actual report content
            $table->json('statistics'); // Key metrics
            $table->string('file_path')->nullable(); // Path to generated PDF/CSV
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_to')->nullable(); // CBN, NDPC, etc.
            $table->json('submission_response')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'report_period']);
            $table->index('status');
        });

        // Data subject requests (GDPR/NDPR)
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique(); // Auto-generated
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('request_type'); // access, deletion, portability, rectification
            $table->string('status')->default('pending'); // pending, processing, completed, rejected
            $table->text('request_details')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->string('data_export_path')->nullable(); // For access/portability requests
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('data_deleted_at')->nullable(); // For deletion requests
            $table->json('metadata')->nullable(); // Additional tracking info
            $table->timestamps();

            $table->index(['user_id', 'request_type']);
            $table->index('status');
        });

        // Record retention policy
        Schema::create('record_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('data_type'); // transactions, kyc_documents, communications, etc.
            $table->integer('retention_years'); // How long to keep
            $table->text('description');
            $table->string('deletion_method'); // soft_delete, hard_delete, archive
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('data_type');
        });

        // Scheduled deletions (for retention automation)
        Schema::create('scheduled_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('record_type'); // order, kyc_document, etc.
            $table->unsignedBigInteger('record_id');
            $table->date('scheduled_for'); // When to delete
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->foreignId('policy_id')->constrained('record_retention_policies')->onDelete('cascade');
            $table->timestamp('deleted_at')->nullable();
            $table->text('deletion_notes')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
            $table->index(['scheduled_for', 'status']);
        });

        // Compliance checklist (for audits)
        Schema::create('compliance_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('checklist_type'); // monthly_review, quarterly_audit, annual_compliance
            $table->date('review_date');
            $table->json('checklist_items'); // Array of items with status
            $table->integer('items_completed')->default(0);
            $table->integer('items_total');
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->string('status')->default('in_progress'); // in_progress, completed, overdue
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('review_date');
            $table->index('status');
        });

        // Regulatory submissions log
        Schema::create('regulatory_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_type'); // SAR, CTR, KYC_update, etc.
            $table->string('regulator'); // CBN, NDPC, EFCC, etc.
            $table->string('reference_number')->unique();
            $table->json('submission_data');
            $table->string('file_path')->nullable();
            $table->string('status')->default('submitted'); // submitted, acknowledged, under_review, approved
            $table->timestamp('submitted_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('submitted_by');
            $table->json('response_data')->nullable();
            $table->timestamps();

            $table->index('regulator');
            $table->index('submission_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_submissions');
        Schema::dropIfExists('compliance_checklists');
        Schema::dropIfExists('scheduled_deletions');
        Schema::dropIfExists('record_retention_policies');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('compliance_reports');
    }
};
