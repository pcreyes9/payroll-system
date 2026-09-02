<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get a setting value.
     *
     * Example:
     *
     * Setting::getValue(
     *     'attendance',
     *     'official_time_in',
     *     '09:00'
     * );
     */
    public static function getValue(
        string $group,
        string $key,
        mixed $default = null
    ): mixed {
        $setting = static::query()
            ->where('group', $group)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $setting) {
            return $default;
        }

        return static::castValue(
            $setting->value,
            $setting->type
        );
    }

    /**
     * Create or update a setting.
     *
     * Example:
     *
     * Setting::setValue(
     *     'attendance',
     *     'official_time_in',
     *     '09:00',
     *     'time',
     *     'Official employee time in.'
     * );
     */
    public static function setValue(
        string $group,
        string $key,
        mixed $value,
        string $type = 'string',
        ?string $description = null
    ): static {
        return static::updateOrCreate(
            [
                'group' => $group,
                'key' => $key,
            ],
            [
                'value' => static::prepareValue(
                    $value,
                    $type
                ),

                'type' => $type,

                'description' => $description,

                'is_active' => true,
            ]
        );
    }

    /**
     * Convert a stored database value
     * into the appropriate PHP value.
     */
    protected static function castValue(
        mixed $value,
        string $type
    ): mixed {
        if ($value === null) {
            return null;
        }

        return match ($type) {

            'integer' => (int) $value,

            'decimal' => (float) $value,

            'boolean' => filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN
            ),

            'json' => json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            ),

            'time',
            'date',
            'string' => (string) $value,

            default => $value,
        };
    }

    /**
     * Convert a PHP value into a database value.
     */
    protected static function prepareValue(
        mixed $value,
        string $type
    ): ?string {
        if ($value === null) {
            return null;
        }

        return match ($type) {

            'boolean' => $value
                ? '1'
                : '0',

            'json' => json_encode(
                $value,
                JSON_THROW_ON_ERROR
            ),

            default => (string) $value,
        };
    }
}
