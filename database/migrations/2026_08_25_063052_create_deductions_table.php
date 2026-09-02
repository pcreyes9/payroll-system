<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('deduction_type', 30)
                ->default('fixed');

            $table->decimal('default_amount', 12, 2)
                ->default(0);

            $table->boolean('is_recurring')
                ->default(true);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
