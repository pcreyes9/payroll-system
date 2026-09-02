<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_period_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('employee_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Salary snapshot
             */
            $table->decimal('basic_salary', 12, 2)
                ->default(0);

            $table->decimal('basic_pay', 12, 2)
                ->default(0);

            /*
             * Earnings
             */
            $table->decimal('allowances', 12, 2)
                ->default(0);

            $table->decimal('overtime_pay', 12, 2)
                ->default(0);

            $table->decimal('other_earnings', 12, 2)
                ->default(0);

            $table->decimal('gross_pay', 12, 2)
                ->default(0);

            /*
             * Government contributions
             */
            $table->decimal('sss_contribution', 12, 2)
                ->default(0);

            $table->decimal('philhealth_contribution', 12, 2)
                ->default(0);

            $table->decimal('pagibig_contribution', 12, 2)
                ->default(0);

            /*
             * Tax
             */
            $table->decimal('withholding_tax', 12, 2)
                ->default(0);

            /*
             * Other deductions
             */
            $table->decimal('other_deductions', 12, 2)
                ->default(0);

            $table->decimal('total_deductions', 12, 2)
                ->default(0);

            $table->decimal('net_pay', 12, 2)
                ->default(0);

            /*
             * Processing
             */
            $table->string('status', 30)
                ->default('draft');

            $table->timestamp('calculated_at')
                ->nullable();

            $table->timestamp('approved_at')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            /*
             * An employee should only have one
             * payroll record per payroll period.
             */
            $table->unique([
                'payroll_period_id',
                'employee_id',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
