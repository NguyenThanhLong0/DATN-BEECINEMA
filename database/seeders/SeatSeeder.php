<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Room;
use App\Models\TypeSeat;

class SeatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách các phòng chiếu
        $rooms = Room::all();

        // Kiểm tra nếu chưa có phòng chiếu nào thì không seed
        if ($rooms->isEmpty()) {
            echo "No rooms found. Please seed rooms first.\n";
            return;
        }

        // Lấy danh sách loại ghế
        $regularSeat = TypeSeat::where('name', 'Ghế thường')->first();
        $vipSeat = TypeSeat::where('name', 'Ghế VIP')->first();

        // Kiểm tra nếu chưa có loại ghế thì dừng
        if (!$regularSeat || !$vipSeat) {
            echo "No seat types found. Please seed type_seats first.\n";
            return;
        }

        // Xóa dữ liệu cũ
        DB::table('seats')->truncate();

        // Duyệt qua từng phòng và thêm ghế
        foreach ($rooms as $room) {
            for ($y = 'A'; $y <= 'J'; $y++) { // 10 hàng (A -> J)
                for ($x = 1; $x <= 10; $x++) { // 10 cột (1 -> 10)
                    
                    // Xác định loại ghế: A-E là thường, F-J là VIP
                    $type_seat_id = in_array($y, ['A', 'B', 'C', 'D', 'E']) ? $regularSeat->id : $vipSeat->id;

                    DB::table('seats')->insert([
                        'room_id' => $room->id,
                        'type_seat_id' => $type_seat_id,
                        'coordinates_x' => $x,
                        'coordinates_y' => $y,
                        'name' => $y . $x, // Ví dụ: A1, B5, J10
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
