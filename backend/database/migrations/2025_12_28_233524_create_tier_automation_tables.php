<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tier change history
        Schema::create('tier_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('from_tier'); // Previous tier (0-3)
            $table->integer('to_tier'); // New tier (0-3)
            $table->string('change_type'); // upgrade, downgrade, manual
            $table->string('reason'); // kyc_approved, sanctions_match, admin_action, etc.
            $table->text('notes')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('change_type');
        });

        // Tier violation tracking
        Schema::create('tier_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('violation_type'); // sanctions_match, excessive_disputes, kyc_fraud, etc.
            $table->string('severity'); // minor, moderate, severe, critical
            $table->text('description');
            $table->json('evidence')->nullable(); // Links to related records
            $table->string('action_taken'); // warning, tier_downgrade, suspension, closure
            $table->boolean('tier_affected')->default(false);
            $table->integer('previous_tier')->nullable();
            $table->integer('new_tier')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'violation_type']);
            $table->index('severity');
        });

        // Tier upgrade requests (for manual review if needed)
        Schema::create('tier_upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('current_tier');
            $table->integer('requested_tier');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('justification')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Notification queue
        Schema::create('notification_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type'); // tier_change, kyc_approved, alert, etc.
            $table->string('channel'); // email, sms, push, in_app
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->json('data'); // Notification content
            $table->integer('retry_count')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['scheduled_for', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
        Schema::dropIfExists('tier_upgrade_requests');
        Schema::dropIfExists('tier_violations');
        Schema::dropIfExists('tier_changes');
    }
};
