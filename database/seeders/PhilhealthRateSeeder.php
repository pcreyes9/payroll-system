<?php

namespace Database\Seeders;

use App\Models\PhilhealthRate;
use Illuminate\Database\Seeder;

class PhilhealthRateSeeder extends Seeder
{
    /**
     * 2025-2026 PhilHealth premium rate, confirmed unchanged for 2026
     * under the Universal Health Care Act (RA 11223) schedule:
     *
     *   - Total premium: 5% of monthly basic salary
     *   - Employee share: 2.5%
     *   - Employer share: 2.5%
     *   - Salary floor: ₱10,000 (minimum premium ₱500/month)
     *   - Salary ceiling: ₱100,000 (maximum premium ₱5,000/month)
     */
    public function run(): void
    {
        PhilhealthRate::updateOrCreate(
            ['effective_date' => '2025-01-01'],
            [
                'premium_rate' => 5.00,
                'employee_share_rate' => 2.50,
                'employer_share_rate' => 2.50,
                'salary_floor' => 10000.00,
                'salary_ceiling' => 100000.00,
                'is_active' => true,
            ]
        );
    }
}
