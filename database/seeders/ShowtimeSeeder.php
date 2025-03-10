<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Showtime;

class ShowtimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Showtime::create([
            'cinema_id' => 1,
            'room_id' => 1,
            'slug' => Showtime::generateCustomRandomString(),
            'format' => '2D Lồng tiếng',
            'movie_version_id' => 1,
            'movie_id' => 1,
            'date' => Carbon::today(),
            'start_time' => Carbon::now(),
            'end_time' => Carbon::now()->addHours(2),
            'is_active' => true,
        ]);
    }
}
