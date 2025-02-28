<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PointHistory;
use App\Models\Membership;

class PointHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $memberships = Membership::all();

        foreach ($memberships as $membership) {
            // Tạo bản ghi "tích điểm"
            PointHistory::create([
                'membership_id' => $membership->id,
                'points' => rand(10, 500),
                'type' => 'Nhận điểm',
            ]);

            // Tạo bản ghi "đổi điểm"
            PointHistory::create([
                'membership_id' => $membership->id,
                'points' => -rand(5, 100),
                'type' => 'Dùng điểm',
            ]);
        }
    }
}
