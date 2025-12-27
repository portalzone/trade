<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->integer('file_size'); // in bytes
            $table->integer('position')->default(0); // for ordering images
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
