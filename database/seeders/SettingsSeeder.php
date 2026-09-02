<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'group' => 'attendance',
                'key' => 'official_time_in',
                'value' => '09:00',
                'type' => 'time',
                'description' => 'Official employee time in.',
                'is_active' => true,
            ],

            [
                'group' => 'attendance',
                'key' => 'official_time_out',
                'value' => '17:00',
                'type' => 'time',
                'description' => 'Official employee time out.',
                'is_active' => true,
            ],

            [
                'group' => 'attendance',
                'key' => 'grace_period_minutes',
                'value' => '0',
                'type' => 'integer',
                'description' => 'Number of minutes allowed after official time in before the employee is considered late.',
                'is_active' => true,
            ],

            [
                'group' => 'attendance',
                'key' => 'overtime_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable overtime calculation.',
                'is_active' => true,
            ],

            [
                'group' => 'attendance',
                'key' => 'minimum_overtime_minutes',
                'value' => '0',
                'type' => 'integer',
                'description' => 'Minimum number of overtime minutes required before overtime is counted.',
                'is_active' => true,
            ],

            [
                'group' => 'attendance',
                'key' => 'workdays',
                'value' => json_encode([
                    1,
                    2,
                    3,
                    4,
                    5,
                ]),
                'type' => 'json',
                'description' => 'Regular working days. 1 = Monday through 7 = Sunday.',
                'is_active' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                [
                    'group' => $setting['group'],
                    'key' => $setting['key'],
                ],
                $setting
            );
        }
    }
}
