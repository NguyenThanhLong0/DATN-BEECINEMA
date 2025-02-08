<?php

use App\Models\Branch;
use App\Models\Cinema;
use App\Models\SeatTemplate;
use App\Models\TypeRoom;
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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Branch::class)->constrained();
            $table->foreignIdFor(Cinema::class)->constrained();
            $table->foreignIdFor(TypeRoom::class)->constrained();
            $table->foreignIdFor(SeatTemplate::class)->constrained();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_publish')->default(false);
            $table->timestamps();
            $table->unique(['name','cinema_id'],'name_cinema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
