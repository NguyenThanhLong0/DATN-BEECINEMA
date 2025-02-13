<?php

use App\Models\Movie;
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
        Schema::create('movie_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Movie::class)->constrained()->onDelete('cascade');
            $table->string('name');             //Vietsub, Lồng tiếng, thuyết minh
            $table->timestamps();
            $table->unique(['movie_id', 'name'], 'movie_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_versions');
    }
};
