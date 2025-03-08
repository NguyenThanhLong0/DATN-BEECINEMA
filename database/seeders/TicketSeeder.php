<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TicketSeeder extends Seeder
{
    public function run()
{   
    $vouchers = Voucher::get();
    $users = User::pluck('id');
    $faker = Faker::create();

    if ($vouchers->isEmpty() || $users->isEmpty()) {
        $this->command->info('⚠️ Không có dữ liệu trong bảng vouchers hoặc users. Hãy chạy seeder!');
        return;
    }

    foreach (range(1, 4) as $index) {
        $voucher = $faker->randomElement([$vouchers->random(), null]);
        $userId = $users->random();

        // Tạo ticket trước nhưng có total_price = 0 tạm thời
        $ticketId = DB::table('tickets')->insertGetId([
            'user_id'           => $userId,
            'cinema_id'         => 1,
            'room_id'           => rand(1, 3),
            'movie_id'          => rand(1, 3),
            'showtime_id'       => 1,
            'voucher_code'      => $voucher?->code, // Tránh lỗi khi voucher là null
            'voucher_discount'  => $voucher?->discount_value ?? 0, // Nếu null thì giảm giá là 0
            'payment_name'      => $faker->randomElement(['Momo', 'ZaloPay', 'Visa', 'Tiền mặt']),
            'code'              => strtoupper($faker->unique()->bothify('TICKET-#######')),
            'total_price'       => 0, // Giữ giá trị tạm
            'status'            => $faker->randomElement(['chưa xuất vé', 'đã xuất vé', 'đã huỷ']),
            'expiry'            => $faker->dateTimeBetween('+1 week', '+6 months'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Tổng tiền ghế ngồi
        $totalSeatPrice = 0;
        foreach (range(1, rand(1, 3)) as $seatIndex) {
            $seatPrice = $faker->numberBetween(50000, 100000);
            $totalSeatPrice += $seatPrice;
            DB::table('ticket_seats')->insert([
                'ticket_id'  => $ticketId,
                'seat_id'    => rand(1, 50),
                'price'      => $seatPrice,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tổng tiền combo
        $totalComboPrice = 0;
        foreach (range(1, rand(1, 2)) as $comboIndex) {
            $comboPrice = $faker->numberBetween(30000, 50000);
            $quantity = rand(1, 3);
            $totalComboPrice += $comboPrice * $quantity;
            DB::table('ticket_combos')->insert([
                'ticket_id'  => $ticketId,
                'combo_id'   => 1,
                'price'      => $comboPrice,
                'quantity'   => $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tính tổng tiền & cập nhật lại ticket
        $totalPrice = max(0, ($totalSeatPrice + $totalComboPrice) - $voucher?->discount_value ?? 0);
        DB::table('tickets')->where('id', $ticketId)->update(['total_price' => $totalPrice]);
    }
}

}
