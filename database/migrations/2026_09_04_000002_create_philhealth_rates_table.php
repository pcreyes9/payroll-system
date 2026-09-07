<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('philhealth_rates', function (Blueprint $table) {
            $table->id();

            // Total premium rate as a percentage, e.g. 5.00 = 5%
            $table->decimal('premium_rate', 5, 2);

            // Split of the premium rate between employee and employer.
            // Stored explicitly rather than assuming an even 50/50 split,
            // since the law has changed this ratio before.
            $table->decimal('employee_share_rate', 5, 2);
            $table->decimal('employer_share_rate', 5, 2);

            // Salary floor and ceiling the premium rate is applied to.
            $table->decimal('salary_floor', 12, 2);
            $table->decimal('salary_ceiling', 12, 2);

            $table->date('effective_date');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['effective_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('philhealth_rates');
    }
};
