<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->index(); // Giới hạn độ dài + index
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date')->index();
            $table->dateTime('end_date')->index();
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->integer('discount_value')->unsigned();
            $table->integer('min_order_amount')->unsigned();
            $table->integer('max_discount_amount')->nullable()->default(0)->unsigned();
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('is_active')->default(1)->comment('0: expired, 1: available')->index();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
