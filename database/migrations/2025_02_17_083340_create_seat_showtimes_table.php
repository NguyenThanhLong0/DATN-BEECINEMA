<?php

use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
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
        Schema::create('seat_showtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Seat::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Showtime::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(User::class)->nullable();
            $table->string('status');
            $table->unsignedInteger('price')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['seat_id', 'showtime_id'], 'seat_showtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_showtimes');
    }
};
