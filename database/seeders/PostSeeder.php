<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // Post::create([
        //     'user_id' => 1,
        //     'title' => 'Inception - Giấc mơ trong giấc mơ',
        //     'slug' => Str::slug('Inception - Giấc mơ trong giấc mơ'),
        //     'img_post' => 'https://picsum.photos/seed/picsum/200/300',
        //     'description' => 'Bộ phim khoa học viễn tưởng kinh điển của đạo diễn Christopher Nolan, kể về nghệ thuật xâm nhập giấc mơ.',
        //     'content' => '<p>Inception là một bộ phim xoay quanh Dom Cobb, một kẻ trộm tài ba có khả năng xâm nhập vào giấc mơ của người khác để đánh cắp bí mật.</p>',
        //     'is_active' => true,
        //     'view_count' => 100,
        //     'created_at' => now(),
        //     'updated_at' => now()
        // ]);
    }
}
