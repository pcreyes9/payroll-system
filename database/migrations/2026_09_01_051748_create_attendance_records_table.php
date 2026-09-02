<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('attendance_date');

            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();

            $table->time('break_in')->nullable();
            $table->time('break_out')->nullable();

            $table->string('status', 30)
                ->default('present');

            $table->unsignedInteger('worked_minutes')
                ->default(0);

            $table->unsignedInteger('late_minutes')
                ->default(0);

            $table->unsignedInteger('undertime_minutes')
                ->default(0);

            $table->unsignedInteger('overtime_minutes')
                ->default(0);

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique([
                'employee_id',
                'attendance_date',
            ]);

            $table->index([
                'attendance_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
