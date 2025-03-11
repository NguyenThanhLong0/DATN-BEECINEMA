<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PointHistory;
use App\Models\Membership;
use App\Models\Ticket;

class PointHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $memberships = Membership::all();
        $tickets=Ticket::all();
        $ticket=$tickets->random();

        foreach ($memberships as $membership) {
            // Tạo bản ghi "tích điểm"
            PointHistory::create([
                'membership_id' => $membership->id,
                'ticket_id' => $ticket->id,
                'points' => 100000,
                'type' => 'Nhận điểm',
            ]);

            // Tạo bản ghi "đổi điểm"
            PointHistory::create([
                'membership_id' => $membership->id,
                'ticket_id' => $ticket->id,
                'points' => 10000,
                'type' => 'Dùng điểm',
            ]);
        }
    }
}
