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
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('total_spent', 10, 2)->default(0)->comment('Tổng số tiền đã chi tiêu');
            $table->decimal('ticket_percentage', 5, 2)->default(0)->comment('Phần trăm giảm giá vé');
            $table->decimal('combo_percentage', 5, 2)->default(0)->comment('Phần trăm giảm giá combo');
            $table->boolean('is_default')->default(false)->comment('Xác định rank mặc định');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
