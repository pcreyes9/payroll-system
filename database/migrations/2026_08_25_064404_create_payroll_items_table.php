<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * earning / deduction
             */
            $table->string('item_type', 30);

            /*
             * Examples:
             * BASIC
             * ALLOWANCE
             * OVERTIME
             * SSS
             * PHILHEALTH
             * PAGIBIG
             * TAX
             * DEDUCTION
             */
            $table->string('code', 50);

            $table->string('description');

            /*
             * Original reference.
             *
             * Example:
             * allowance_id
             * deduction_id
             */
            $table->unsignedBigInteger('reference_id')
                ->nullable();

            /*
             * Calculation details
             */
            $table->decimal('quantity', 12, 4)
                ->default(1);

            $table->decimal('rate', 12, 4)
                ->default(0);

            $table->decimal('amount', 12, 2)
                ->default(0);

            $table->integer('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'payroll_id',
                'item_type',
            ]);

            $table->index([
                'code',
            ]);

            $table->index([
                'reference_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
