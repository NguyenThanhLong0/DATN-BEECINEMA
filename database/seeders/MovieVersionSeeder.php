<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MovieVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $movieVersions = [
            ['movie_id' => 1, 'name' => 'Vietsub'],
            ['movie_id' => 1, 'name' => 'Lồng tiếng'],
            ['movie_id' => 2, 'name' => 'Thuyết minh'],
            ['movie_id' => 2, 'name' => 'Vietsub'],
            ['movie_id' => 3, 'name' => 'Lồng tiếng'],
            ['movie_id' => 3, 'name' => 'Thuyết minh'],
        ];

        foreach ($movieVersions as &$version) {
            $version['created_at'] = Carbon::now();
            $version['updated_at'] = Carbon::now();
        }

        DB::table('movie_versions')->insert($movieVersions);
    }
}
