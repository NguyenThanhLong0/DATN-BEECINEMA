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
        Schema::create('point_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('cascade');
            $table->integer('points');
            $table->enum('type', ['Nhận điểm', 'Dùng điểm']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_histories');
    }
};
