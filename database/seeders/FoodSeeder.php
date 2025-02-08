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
        Food::create([
            'name'=> "khoai",
            'img_thumbnail'=> "",
            'price'=> 500,
            'type'=> "luộc",
            'description'=> "ddd",
            'is_active'=> true
        ]);
    }
}
