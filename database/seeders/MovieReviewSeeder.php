<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MovieReview;
use App\Models\Movie;
use App\Models\User;

class MovieReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $movies = Movie::all();
        $users = User::all();

        foreach ($movies as $movie) {
            foreach ($users as $user) {
                MovieReview::create([
                    'movie_id' => $movie->id,
                    'user_id' => $user->id,
                    'rating' => rand(1, 5),
                    'description' => "Đánh giá phim {$movie->title} của user {$user->name}",
                ]);
            }
        }
    }
}
