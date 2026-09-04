<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class PayrollSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Regular Day
        Setting::setValue(
            'payroll',
            'regular_day_rate',
            100,
            'percentage',
            'Regular working day rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_day_overtime_rate',
            125,
            'percentage',
            'Overtime rate for a regular working day.'
        );

        // Rest Day
        Setting::setValue(
            'payroll',
            'rest_day_rate',
            130,
            'percentage',
            'Rest day work rate.'
        );

        Setting::setValue(
            'payroll',
            'rest_day_overtime_rate',
            169,
            'percentage',
            'Overtime rate for rest day work.'
        );

        // Special Non-Working Holiday
        Setting::setValue(
            'payroll',
            'special_holiday_rate',
            130,
            'percentage',
            'Special non-working holiday rate.'
        );

        Setting::setValue(
            'payroll',
            'special_holiday_overtime_rate',
            169,
            'percentage',
            'Overtime rate for special non-working holiday work.'
        );

        // Special Non-Working Holiday + Rest Day
        Setting::setValue(
            'payroll',
            'special_holiday_rest_day_rate',
            150,
            'percentage',
            'Special non-working holiday falling on a rest day rate.'
        );

        Setting::setValue(
            'payroll',
            'special_holiday_rest_day_overtime_rate',
            195,
            'percentage',
            'Overtime rate for special non-working holiday falling on a rest day.'
        );

        // Regular Holiday
        Setting::setValue(
            'payroll',
            'regular_holiday_rate',
            200,
            'percentage',
            'Regular holiday rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_holiday_overtime_rate',
            260,
            'percentage',
            'Overtime rate for regular holiday work.'
        );

        // Regular Holiday + Rest Day
        Setting::setValue(
            'payroll',
            'regular_holiday_rest_day_rate',
            260,
            'percentage',
            'Regular holiday falling on a rest day rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_holiday_rest_day_overtime_rate',
            338,
            'percentage',
            'Overtime rate for regular holiday falling on a rest day.'
        );

        // Night Shift Differential
        Setting::setValue(
            'payroll',
            'night_shift_differential_rate',
            10,
            'percentage',
            'Night shift differential additional rate.'
        );
    }
}
