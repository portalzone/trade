<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->onDelete('cascade');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->integer('file_size');
            $table->enum('evidence_type', ['photo', 'video', 'document'])->default('photo');
            $table->timestamps();

            $table->index('dispute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_uploads');
    }
};
