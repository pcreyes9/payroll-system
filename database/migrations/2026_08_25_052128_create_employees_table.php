<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Employee Information
            $table->string('employee_id', 30)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 20)->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('civil_status', 30)->nullable();

            // Contact Information
            $table->string('email')->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->text('address')->nullable();

            // Employment Information
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('position_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('employment_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('date_hired')->nullable();
            $table->date('date_regularized')->nullable();

            $table->string('employment_status', 30)
                ->default('Active');

            // Payroll Information
            $table->decimal('basic_salary', 12, 2)
                ->default(0);

            $table->string('pay_frequency', 30)
                ->default('Semi-Monthly');

            $table->boolean('payroll_enabled')
                ->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
