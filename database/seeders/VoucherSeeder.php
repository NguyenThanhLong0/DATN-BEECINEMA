<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        $vouchers = [
            [
                'code' => 'DISCOUNT10',
                'title' => 'Giảm 10K',
                'description' => 'Giảm 10k cho hóa đơn trên 100k',
                'discount_value' => 10000,
                'discount_type' => 'fixed',
                'quantity' => 200,
                'per_user_limit' => 10,
                'min_order_amount' => 100000,
                'max_discount_amount' => null,
                'start_date' => now(),
                'end_date' => Carbon::now()->addDays(10), // Hết hạn sau 10 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '50K',
                'title' => 'Giảm 50k',
                'description' => 'Giảm 50k cho hóa đơn từ 200k',
                'discount_value' => 50000,
                'discount_type' => 'fixed',
                'quantity' => 200,
                'per_user_limit' => 10,
                'min_order_amount' => 200000,
                'max_discount_amount' => null,
                'start_date' => now(),
                'end_date' => Carbon::now()->addDays(5), // Hết hạn sau 5 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SUMMER50',
                'title' => 'Giảm 100K',
                'description' => 'Giảm ngay 100k cho hóa đơn trên 500k',
                'discount_value' => 100000,
                'discount_type' => 'fixed',
                'quantity' => 200,
                'per_user_limit' => 10,
                'min_order_amount' => 500000,
                'max_discount_amount' => null,
                'start_date' => now(),
                'end_date' => Carbon::now()->addDays(15), // Hết hạn sau 15 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('vouchers')->insert($vouchers);
    }
}
