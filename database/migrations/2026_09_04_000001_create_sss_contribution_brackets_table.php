<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sss_contribution_brackets', function (Blueprint $table) {
            $table->id();

            // Compensation range this bracket applies to.
            // salary_to is nullable to represent the open-ended top bracket.
            $table->decimal('salary_from', 12, 2);
            $table->decimal('salary_to', 12, 2)->nullable();

            // Monthly Salary Credit this range maps to (for reference/reporting).
            $table->decimal('monthly_salary_credit', 12, 2);

            $table->decimal('employee_share', 12, 2);
            $table->decimal('employer_share', 12, 2);
            $table->decimal('ec_contribution', 12, 2)->default(0);

            // Effective date lets you keep historical brackets when SSS
            // publishes a new contribution schedule, instead of overwriting.
            $table->date('effective_date');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['effective_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sss_contribution_brackets');
    }
};
