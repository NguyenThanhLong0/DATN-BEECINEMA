<?php

namespace Database\Seeders;

use App\Models\Seat;
use App\Models\Showtime;
use App\Models\SeatShowtime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeatShowtimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Tắt kiểm tra khóa ngoại tạm thời
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Lấy tất cả các suất chiếu và ghế
        $showtimes = Showtime::all();
        $seats = Seat::all();

        // Duyệt qua tất cả các suất chiếu và ghế để tạo bản ghi seat_showtimes
        foreach ($showtimes as $showtime) {
            foreach ($seats as $seat) {
                // Tạo dữ liệu seat_showtime ngẫu nhiên
                SeatShowtime::create([
                    'seat_id' => $seat->id,
                    'showtime_id' => $showtime->id,
                    'status' => 'available', // Trạng thái ghế
                    'price' => 100000, // Giá vé ghế, có thể tính theo từng loại
                    'hold_expires_at' => now()->addMinutes(15), // Thời gian hết hạn giữ ghế (15 phút)
                ]);
            }
        }

        // Bật lại kiểm tra khóa ngoại
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
