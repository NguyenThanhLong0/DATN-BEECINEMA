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
        Schema::create('special_days', function (Blueprint $table) {
            $table->id();
            $table->date('special_date'); // Ngày đặc biệt
            $table->string('name');       // Tên ngày đặc biệt (ví dụ: Tết, Giáng sinh)
            $table->string('type');       // Loại ngày đặc biệt (ví dụ: Holiday, Event)
            $table->timestamps();         // Cột created_at và updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_days');
    }
};
