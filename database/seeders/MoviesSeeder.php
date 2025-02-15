<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movie;

class MoviesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Movie::create([
            'name' => 'Avengers: Endgame',
            'slug' => 'avengers-endgame',
            'category' => 'Action, Adventure',
            'img_thumbnail' => 'avengers-endgame-thumbnail.jpg',
            'description' => 'The epic conclusion to the Marvel Cinematic Universe.',
            'director' => 'Anthony Russo, Joe Russo',
            'cast' => 'Robert Downey Jr., Chris Evans, Mark Ruffalo',
            'rating' => 'T13',
            'duration' => 180,
            'release_date' => '2019-04-26',
            'end_date' => '2022-04-26',
            'trailer_url' => 'https://www.youtube.com/watch?v=TcMBFSGVi1c',
            'surcharge' => 20,
            'surcharge_desc' => '3D surcharge',
            'is_active' => true,
            'is_hot' => true,
            'is_special' => false,
            'is_publish' => true
        ]);
    }
}
