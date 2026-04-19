<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'default_interest_rate',
                'value' => '10',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'late_fee_percentage',
                'value' => '2',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}