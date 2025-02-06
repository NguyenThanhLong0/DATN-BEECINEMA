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
        Schema::create('seat_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('matrix_id')->unique();
            $table->string('name')->unique();
            $table->json('seat_structure')->nullable(); // JSON cho sơ đồ ghế
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('row_regular')->default(4);
            $table->unsignedTinyInteger('row_vip')->nullable();
            $table->unsignedTinyInteger('row_double')->nullable(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_publish')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_templates');
    }
};
