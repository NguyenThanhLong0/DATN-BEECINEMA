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
        $voucherIds = DB::table('vouchers')->pluck('id')->toArray();

        // Kiểm tra nếu database chưa có dữ liệu
        if (empty($userIds) || empty($voucherIds)) {
            return;
        }

        $userVouchers = [];
        foreach ($userIds as $userId) {
            foreach ($voucherIds as $voucherId) {
                $userVouchers[] = [
                    'user_id' => $userId,
                    'voucher_id' => $voucherId,
                    'usage_count' => rand(0, 3), // Random lượt sử dụng từ 0 đến 3
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('user_vouchers')->insert($userVouchers);
    }
}
