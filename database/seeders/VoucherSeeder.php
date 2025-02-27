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
                'discount' => 10000,
                'quantity'=>200,
                'limit' => 10,
                'type' =>'amount',
                'start_date_time'=>now(),
                'end_date_time' => Carbon::now()->addDays(10), // Hết hạn sau 10 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '50K',
                'title' => 'Giảm 50k',
                'description' => 'Giảm 50k cho hóa đơn từ 200k',
                'discount' => 50000,
                'quantity'=>200,
                'limit' => 10 ,
                'type' =>'amount',
                'start_date_time'=>now(),
                'end_date_time' => Carbon::now()->addDays(5), // Hết hạn sau 5 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SUMMER50',
                'title' => 'Giảm 50k',
                'description' => 'Giảm ngay 100k cho hóa đơn trên 500k',
                'discount' => 100000,
                'quantity'=>200,
                'limit' => 10,
                'type' =>'amount',
                'start_date_time'=>now(),
                'end_date_time' => Carbon::now()->addDays(15), // Hết hạn sau 15 ngày
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('vouchers')->insert($vouchers);
    }
}
