<?php

namespace Database\Seeders;

use App\Models\Combo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComboSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Combo::create([
            'name' => 'Combo 1',
            'img_thumbnail' => 'https://example.com/combo.jpg',
            'price' => 5000, 
            'discount_price' => 3000,
            'description' => 'kkkkkk',
            'is_active' =>true
        ]);
    }
}
