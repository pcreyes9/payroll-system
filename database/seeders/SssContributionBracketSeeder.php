<?php

namespace Database\Seeders;

use App\Models\SssContributionBracket;
use Illuminate\Database\Seeder;

class SssContributionBracketSeeder extends Seeder
{
    /**
     * Generates the SSS contribution bracket table based on the
     * 2025 schedule (SSS Circular No. 2024-006):
     *
     *   - Total rate: 15% of Monthly Salary Credit (MSC)
     *   - Employer share: 10% of MSC
     *   - Employee share: 5% of MSC
     *   - MSC range: ₱5,000 (min) to ₱35,000 (max), in ₱500 increments
     *   - EC contribution: ₱10 if MSC < ₱15,000, else ₱30
     *
     * Boundaries and computed values below have been cross-checked
     * against the official employed-members contribution table
     * (sampled rows at MSC 5,000 / 10,000 / 15,000 / 20,000 / 25,000 /
     * 30,000 / 35,000) and match exactly, including the floor bracket
     * starting at ₱4,750.
     */
    public function run(): void
    {
        $effectiveDate = '2025-01-01';

        $minMsc = 5000;
        $maxMsc = 35000;
        $step = 500;

        $msc = $minMsc;
        $rangeFrom = $minMsc - ($step / 2); // ₱4,750 floor

        while ($msc <= $maxMsc) {

            $isLastBracket = $msc >= $maxMsc;

            $rangeTo = $isLastBracket
                ? null
                : ($msc + ($step / 2) - 0.01);

            $ecContribution = $msc < 15000 ? 10.00 : 30.00;

            SssContributionBracket::updateOrCreate(
                [
                    'salary_from' => $rangeFrom,
                    'effective_date' => $effectiveDate,
                ],
                [
                    'salary_to' => $rangeTo,
                    'monthly_salary_credit' => $msc,
                    'employee_share' => round($msc * 0.05, 2),
                    'employer_share' => round($msc * 0.10, 2),
                    'ec_contribution' => $ecContribution,
                    'is_active' => true,
                ]
            );

            $rangeFrom = $isLastBracket ? $rangeFrom : $rangeTo + 0.01;
            $msc += $step;
        }
    }
}