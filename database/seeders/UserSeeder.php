<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'avatar' => 'avata.jpg',
                'phone' => '0123456789',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'address' => '123 Admin Street, City',
                'gender' => 'male',
                'birthday' => '1990-01-01',
                'role' => User::TYPE_ADMIN,
                'email_verified_at' => Carbon::now(),
                'remember_token' => null,
                'cinema_id' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Member User',
                'avatar' => 'avt.jpg',
                'phone' => '0987654321',
                'email' => 'member@example.com',
                'password' => Hash::make('password123'),
                'address' => '456 Member Avenue, City',
                'gender' => 'female',
                'birthday' => '1995-05-15',
                'role' => User::TYPE_MEMBER,
                'email_verified_at' => Carbon::now(),
                'remember_token' => null,
                'cinema_id' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
