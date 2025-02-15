<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Banner::create([
            'name' => 'Khuyến mãi mùa hè',
            'description' => 'Ưu đãi giảm giá đặc biệt cho mùa hè này!',
            'img_thumbnail_url' => json_encode([
                'https://picsum.photos/seed/picsum/200/300',
                'https://picsum.photos/seed/picsum/200/300',
                'https://picsum.photos/seed/picsum/200/300'
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
