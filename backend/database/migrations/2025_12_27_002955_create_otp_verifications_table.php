<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->index();
            $table->string('otp_code', 6);
            $table->enum('purpose', ['registration', 'login', 'withdrawal'])->default('registration');
            $table->timestamp('expires_at');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index(['phone_number', 'purpose', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};