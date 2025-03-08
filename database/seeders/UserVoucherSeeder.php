<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserVoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách ID của user và voucher từ database
        $userIds = DB::table('users')->pluck('id')->toArray();
        $vouchers = DB::table('vouchers')->get();

        // Kiểm tra nếu database chưa có dữ liệu
        if (empty($userIds)) {
            return;
        }

        $userVouchers = [];
        foreach ($userIds as $userId) {
            $voucher=$vouchers->random();
                $userVouchers[] = [
                    'user_id' => $userId,
                    'voucher_id' => $voucher->id,
                    // 'usage_count' => rand(0, 3), // Random lượt sử dụng từ 0 đến 3
                    'discount_applied'=>$voucher->discount_value,
                ];
            
        }

        DB::table('user_vouchers')->insert($userVouchers);
    }
}
