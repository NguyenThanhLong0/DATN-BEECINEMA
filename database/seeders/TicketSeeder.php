<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TicketSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        foreach (range(1, 4) as $index) {
            DB::table('tickets')->insert([
                'user_id'           => 1,
                'cinema_id'         => 1,
                'room_id'           => rand(1, 3),
                'movie_id'          => rand(1, 3),
                'showtime_id'       => rand(1, 3),
                'voucher_code'      => $faker->optional()->bothify('VC####'),
                'voucher_discount'  => $faker->optional()->numberBetween(5, 50),
                'payment_name'      => $faker->randomElement(['Momo', 'ZaloPay', 'Visa', 'Tiền mặt']),
                'code'              => strtoupper($faker->unique()->bothify('TICKET-#######')),
                'total_price'       => $faker->numberBetween(50000, 200000),
                'status'            => $faker->randomElement(['chưa xuất vé', 'đã xuất vé', 'đã huỷ']),
                'expiry'            => $faker->dateTimeBetween('+1 week', '+6 months'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);            
        }
    }
}
