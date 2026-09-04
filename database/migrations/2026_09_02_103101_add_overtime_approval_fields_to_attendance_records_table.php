<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedInteger('detected_overtime_minutes')
                ->default(0)
                ->after('overtime_minutes');

            $table->unsignedInteger('approved_overtime_minutes')
                ->default(0)
                ->after('detected_overtime_minutes');

            $table->string('overtime_status', 20)
                ->default('none')
                ->after('approved_overtime_minutes');

            $table->foreignId('overtime_approved_by')
                ->nullable()
                ->after('overtime_status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('overtime_approved_at')
                ->nullable()
                ->after('overtime_approved_by');

            $table->text('overtime_remarks')
                ->nullable()
                ->after('overtime_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign([
                'overtime_approved_by',
            ]);

            $table->dropColumn([
                'detected_overtime_minutes',
                'approved_overtime_minutes',
                'overtime_status',
                'overtime_approved_by',
                'overtime_approved_at',
                'overtime_remarks',
            ]);
        });
    }
};
