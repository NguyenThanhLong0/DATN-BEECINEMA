<?php

namespace Database\Seeders;

use App\Models\TypeRoom;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TypeRoom::insert([
            ['name' => '2D', 'surcharge' => 0],
            ['name' => '3D', 'surcharge' => 20000],
            ['name' => '4D', 'surcharge' => 50000],
        ]);
    }
}
