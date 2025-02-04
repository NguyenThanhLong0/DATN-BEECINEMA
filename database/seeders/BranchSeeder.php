<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Branch::create([
            'name' => 'Branch 1',
            'slug' => 'branch-1',
            'is_active' => true,
        ]);

        Branch::create([
            'name' => 'Branch 2',
            'slug' => 'branch-2',
            'is_active' => false,
        ]);
    }
}
