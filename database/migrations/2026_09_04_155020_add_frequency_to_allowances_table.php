<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->string('frequency', 30)
                ->default('monthly')
                ->after('calculation_type');
        });
    }

    public function down(): void
    {
        Schema::table('allowances', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }
};