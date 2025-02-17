<?php

use App\Models\Combo;
use App\Models\Food;
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
        Schema::create('combo_food', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Food::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Combo::class)->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_foods');
    }
};
