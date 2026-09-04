<?php

namespace App\Filament\Resources\Allowances\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AllowanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Allowance Information')
                    ->description('Define the allowance and how it will be calculated.')
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30)
                            ->placeholder('TRANS'),

                        TextInput::make('name')
                            ->label('Allowance Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Transportation Allowance'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('calculation_type')
                            ->label('Calculation Type')
                            ->options([
                                'fixed' => 'Fixed Amount',
                                'percentage_basic' => 'Percentage of Basic Salary',
                                'per_day' => 'Per Day',
                                'per_hour' => 'Per Hour',
                            ])
                            ->required()
                            ->default('fixed')
                            ->live(),

                        Select::make('frequency')
                            ->label('Frequency')
                            ->options([
                                'semi_monthly' => 'Semi-monthly',
                                'monthly' => 'Monthly',
                            ])
                            ->required()
                            ->default('monthly')
                            ->helperText('Monthly allowances are split across both semi-monthly payroll runs.'),

                        TextInput::make('default_amount')
                            ->label('Default Amount')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->required()
                            ->default(0),

                        Toggle::make('is_taxable')
                            ->label('Taxable')
                            ->helperText('Include this allowance as taxable income.')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}