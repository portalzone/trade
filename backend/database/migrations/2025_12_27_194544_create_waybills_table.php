<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waybills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('waybill_number')->unique();
            $table->string('tracking_code')->unique()->nullable();
            $table->string('sender_name');
            $table->text('sender_address');
            $table->string('sender_phone');
            $table->string('recipient_name');
            $table->text('recipient_address');
            $table->string('recipient_phone');
            $table->string('item_description');
            $table->decimal('weight', 8, 2)->nullable()->comment('Weight in kg');
            $table->string('dimensions')->nullable()->comment('LxWxH in cm');
            $table->decimal('declared_value', 15, 2);
            $table->enum('delivery_type', ['standard', 'express', 'same_day'])->default('standard');
            $table->string('courier_service')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('waybill_number');
            $table->index('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waybills');
    }
};
