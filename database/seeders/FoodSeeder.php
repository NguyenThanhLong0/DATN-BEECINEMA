<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FoodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Food::insert([
            [
                'name' => "Cocacola",
                'img_thumbnail' => "https://example.com/combo.jpg",
                'price' => 500,
                'type' => "Nước",
                'description' => "Nước giải khát quà tặng từ thiên nhiên",
                'is_active' => true
            ],
            [
                'name' => "Bắp",
                'img_thumbnail' => "https://example.com/combo.jpg",
                'price' => 600,
                'type' => "Luộc",
                'description' => "Ăn luôn",
                'is_active' => true
            ]
        ]);
        
    }
}
