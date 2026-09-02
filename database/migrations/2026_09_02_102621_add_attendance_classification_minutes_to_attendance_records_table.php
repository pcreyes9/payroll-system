<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('regular_minutes')
                ->default(0)
                ->after('worked_minutes');

            $table->unsignedInteger('rest_day_minutes')
                ->default(0)
                ->after('regular_minutes');

            $table->unsignedInteger('rest_day_overtime_minutes')
                ->default(0)
                ->after('rest_day_minutes');

            $table->unsignedInteger('night_shift_minutes')
                ->default(0)
                ->after('rest_day_overtime_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'regular_minutes',
                'rest_day_minutes',
                'rest_day_overtime_minutes',
                'night_shift_minutes',
            ]);
        });
    }
};
