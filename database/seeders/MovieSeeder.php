<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MovieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $movies = [
            [
                'name' => 'Avengers: Endgame',
                'slug' => 'avengers-endgame',
                'category' => 'Hành động, Phiêu lưu',
                'img_thumbnail' => 'avengers_endgame.jpg',
                'description' => 'Trận chiến cuối cùng chống lại Thanos.',
                'cast' => 'Robert Downey Jr., Chris Evans, Scarlett Johansson',
                'duration' => 181,
                'release_date' => '2019-04-26',
                'end_date' => '2019-06-30',
                'trailer_url' => 'https://www.youtube.com/watch?v=TcMBFSGVi1c',
                'is_active' => true,
                'is_hot' => true,
            ],
            [
                'name' => 'Spider-Man: No Way Home',
                'slug' => 'spider-man-no-way-home',
                'category' => 'Hành động, Khoa học viễn tưởng',
                'img_thumbnail' => 'spiderman_nwh.jpg',
                'description' => 'Peter Parker tìm cách sửa chữa danh tính bị lộ.',
                'cast' => 'Tom Holland, Zendaya, Benedict Cumberbatch',
                'duration' => 148,
                'release_date' => '2021-12-17',
                'end_date' => '2022-02-20',
                'trailer_url' => 'https://www.youtube.com/watch?v=JfVOs4VSpmA',
                'is_active' => true,
                'is_hot' => true,
            ],
            [
                'name' => 'Dune: Part Two',
                'slug' => 'dune-part-two',
                'category' => 'Khoa học viễn tưởng, Phiêu lưu',
                'img_thumbnail' => 'dune_part_two.jpg',
                'description' => 'Hành trình tiếp theo của Paul Atreides.',
                'cast' => 'Timothée Chalamet, Zendaya, Rebecca Ferguson',
                'duration' => 165,
                'release_date' => '2024-03-01',
                'end_date' => null,
                'trailer_url' => 'https://www.youtube.com/watch?v=Way9Dexny3w',
                'is_active' => true,
                'is_hot' => false,
            ]
        ];

        foreach ($movies as &$movie) {
            $movie['created_at'] = Carbon::now();
            $movie['updated_at'] = Carbon::now();
        }

        DB::table('movies')->insert($movies);
    }
}
