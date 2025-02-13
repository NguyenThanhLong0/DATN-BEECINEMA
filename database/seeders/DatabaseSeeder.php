<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\ComboFood;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            BranchSeeder::class,
            CinemaSeeder::class,
            TypeRoomSeeder::class,
            TypeSeatSeeder::class,
            SeatTemplateSeeder::class,
            RoomSeeder::class,
            SeatSeeder::class,
            FoodSeeder::class,
            ComboSeeder::class,
            ComboFood::class,
            MoviesSeeder::class,
            MovieVersionSeeder::class,
        ]);
    }
}
