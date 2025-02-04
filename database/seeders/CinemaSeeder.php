<?php

namespace Database\Seeders;

use App\Models\Cinema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CinemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Cinema::create([
            'branch_id' => 1,
            'name' => 'Cinema 1',
            'slug' => 'cinema-1',
            'address' => '123 Cinema Street',
            'surcharge' => 10.00,
            'description' => 'Rạp chiếu phim hiện đại',
            'is_active' => true,
        ]);
    }
}
