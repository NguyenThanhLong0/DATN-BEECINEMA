<?php

use App\Models\Room;
use App\Models\TypeSeat;
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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Room::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(TypeSeat::class)->constrained()->onDelete('cascade');  
            $table->unsignedTinyInteger('coordinates_x')->comment('Tọa độ X (1, 2, 3)');
            $table->string('coordinates_y')->comment('Tọa độ Y (A, B, C)'); // Tọa độ ghế bằng (x,y) <=> (5,B)
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
