<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Branch;
use App\Models\Cinema;
use App\Models\TypeRoom;
use App\Models\SeatTemplate;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Tạo dữ liệu mẫu cho phòng chiếu
        $rooms = [
            [
                'branch_id' => Branch::first()->id,
                'cinema_id' => Cinema::first()->id,
                'type_room_id' => TypeRoom::first()->id,
                'seat_template_id' => SeatTemplate::first()->id ?? null,
                'name' => 'Room 1',
                'is_active' => true,
                'is_publish' => true,
            ],
            [
                'branch_id' => Branch::first()->id,
                'cinema_id' => Cinema::first()->id,
                'type_room_id' => TypeRoom::first()->id,
                'seat_template_id' => SeatTemplate::first()->id ?? null,
                'name' => 'Room 2',
                'is_active' => true,
                'is_publish' => false,
            ],
            [
                'branch_id' => Branch::first()->id,
                'cinema_id' => Cinema::first()->id,
                'type_room_id' => TypeRoom::first()->id,
                'seat_template_id' => SeatTemplate::first()->id ?? null,
                'name' => 'Room 3',
                'is_active' => false,
                'is_publish' => true,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}
