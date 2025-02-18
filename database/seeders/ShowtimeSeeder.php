<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowtimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $showtimes = [
            [
                'cinema_id' => 1,
                'room_id' => 1,
                'slug' => 'showtime-1',
                'format' => '2D Lồng tiếng',
                'movie_version_id' => 1,
                'movie_id' => 1,
                'date' => '2025-02-20',
                'start_time' => '2025-02-20 14:00:00',
                'end_time' => '2025-02-20 16:00:00',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cinema_id' => 1,
                'room_id' => 2,
                'slug' => 'showtime-2',
                'format' => '3D Phụ đề',
                'movie_version_id' => 2,
                'movie_id' => 1,
                'date' => '2025-02-21',
                'start_time' => '2025-02-21 18:00:00',
                'end_time' => '2025-02-21 20:30:00',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cinema_id' => 2,
                'room_id' => 3,
                'slug' => 'showtime-3',
                'format' => '4DX Lồng tiếng',
                'movie_version_id' => 3,
                'movie_id' => 2,
                'date' => '2025-02-22',
                'start_time' => '2025-02-22 20:00:00',
                'end_time' => '2025-02-22 22:15:00',
                'is_active' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('showtimes')->insert($showtimes);
    }
}
