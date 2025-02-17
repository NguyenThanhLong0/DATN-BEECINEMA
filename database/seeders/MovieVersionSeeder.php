<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MovieVersion;

class MovieVersionSeeder extends Seeder
{
    public function run(): void
    {
        MovieVersion::insert([
            [
                'movie_id' => 1,
                'name' => 'Vietsub',
            ],
            [
                'movie_id' => 1,
                'name' => 'Lồng tiếng',
            ],
            [
                'movie_id' => 1,
                'name' => 'Thuyết minh',
            ],
        ]);
    }
}
