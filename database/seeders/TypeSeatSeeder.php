<?php

namespace Database\Seeders;

use App\Models\TypeSeat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TypeSeatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seats = [
            ['name' => 'Ghế thường', 'price' => 50000],
            ['name' => 'Ghế vip', 'price' => 75000],
            ['name' => 'Ghế đôi', 'price' => 120000],
        ];

        // Chạy vòng lặp để tạo các loại ghế
        foreach ($seats as $seat) {
            TypeSeat::create($seat);
        }

        $this->command->info('Dữ liệu loại ghế đã được tạo.');
    }
}
