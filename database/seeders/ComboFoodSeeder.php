<?php

namespace Database\Seeders;

use App\Models\ComboFood;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComboFoodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        ComboFood::create([
            'combo_id' => 1,
            'food_id' => 1,
            'quantity' => 2
        ]);
    }
}
