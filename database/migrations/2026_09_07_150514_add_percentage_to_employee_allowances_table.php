<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)
                ->nullable()
                ->after('allowance_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->dropColumn('percentage');
        });
    }
};