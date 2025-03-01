<?php

use App\Models\Combo;
use App\Models\Ticket;
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
        Schema::create('ticket_combos', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Ticket::class)->constrained();
            $table->foreignIdFor(Combo::class)->constrained();
            $table->unsignedInteger('price');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_combos');
    }
};