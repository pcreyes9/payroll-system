<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            /*
             * Settings group/category.
             *
             * Examples:
             * attendance
             * payroll
             * company
             * system
             */
            $table->string('group', 50)->index();

            /*
             * Unique setting key within a group.
             *
             * Example:
             * official_time_in
             * official_time_out
             * grace_period_minutes
             */
            $table->string('key', 100);

            /*
             * Stored setting value.
             *
             * Everything is stored as text and converted
             * according to the "type" field.
             */
            $table->text('value')->nullable();

            /*
             * Value data type.
             *
             * Supported types can include:
             * string
             * integer
             * decimal
             * boolean
             * time
             * date
             * json
             */
            $table->string('type', 30)->default('string');

            /*
             * Human-readable explanation of the setting.
             */
            $table->text('description')->nullable();

            /*
             * Allows settings to be temporarily disabled
             * without deleting them.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * Prevent duplicate keys inside the same group.
             *
             * Example:
             * attendance + official_time_in
             * can only exist once.
             */
            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
