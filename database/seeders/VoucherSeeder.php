<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('vouchers')->insert([
            [
                'code' => 'DISCOUNT50',
                'title' => 'Giảm 50k cho đơn hàng từ 500k',
                'description' => 'Áp dụng cho tất cả đơn hàng trên 500k',
                'start_date_time' => Carbon::now(),
                'end_date_time' => Carbon::now()->addDays(30),
                'discount' => 50000,
                'quantity' => 100,
                'is_active' => 1,
                'limit' => 1,
                'type' => 0, // Fixed Amount
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SALE20',
                'title' => 'Giảm 20% tối đa 100k',
                'description' => 'Áp dụng cho đơn hàng từ 300k',
                'start_date_time' => Carbon::now(),
                'end_date_time' => Carbon::now()->addDays(15),
                'discount' => 20,
                'quantity' => 200,
                'is_active' => 1,
                'limit' => 2,
                'type' => 1, // Percentage
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
