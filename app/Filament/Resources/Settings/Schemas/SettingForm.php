<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting Information')
                    ->description('Configure a system setting.')
                    ->schema([
                        Select::make('group')
                            ->label('Category')
                            ->options([
                                'attendance' => 'Attendance',
                                'payroll' => 'Payroll',
                                'contributions' => 'Contributions',
                                'company' => 'Company',
                                'system' => 'System',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('key')
                            ->label('Setting Key')
                            ->required()
                            ->maxLength(100)
                            ->helperText(
                                'Example: official_time_in'
                            ),

                        Select::make('type')
                            ->options([
                                'string' => 'Text',
                                'integer' => 'Integer',
                                'decimal' => 'Decimal',
                                'boolean' => 'Boolean',
                                'time' => 'Time',
                                'date' => 'Date',
                                'json' => 'JSON',
                            ])
                            ->required()
                            ->default('string')
                            ->native(false),

                        Textarea::make('value')
                            ->label('Value')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
