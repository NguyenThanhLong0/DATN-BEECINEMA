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
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id(); // Cột id tự tăng
            $table->unsignedBigInteger('cinema_id'); // ID rạp chiếu
            $table->unsignedBigInteger('type_room_id')->nullable(); // ID loại phòng, có thể null
            $table->unsignedBigInteger('type_seat_id'); // ID loại ghế
            $table->string('day_type'); // Loại ngày (Weekday, Weekend, Holiday, v.v.)
            $table->string('time_slot'); // Khung giờ (Morning, Afternoon, Evening, Late)
            $table->integer('price'); // Giá vé (ví dụ: 100000)
            $table->date('valid_from'); // Ngày bắt đầu hiệu lực
            $table->date('valid_to')->nullable(); // Ngày kết thúc hiệu lực, có thể null
            $table->timestamps(); // Cột created_at và updated_at

            // Khóa ngoại
            $table->foreign('cinema_id')->references('id')->on('cinemas')->onDelete('cascade');
            $table->foreign('type_room_id')->references('id')->on('type_rooms')->onDelete('set null');
            $table->foreign('type_seat_id')->references('id')->on('type_seats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
