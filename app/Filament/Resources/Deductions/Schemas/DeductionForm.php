<?php

namespace App\Filament\Resources\Deductions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeductionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                 * -------------------------------------------------
                 * BASIC INFORMATION
                 * -------------------------------------------------
                 */

                Section::make('Deduction Information')
                    ->description(
                        'Define the deduction that can be assigned to employees.'
                    )
                    ->schema([

                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(30)
                            ->alphaDash()
                            ->placeholder('LOAN')
                            ->helperText(
                                'Use a short unique code.'
                            ),

                        TextInput::make('name')
                            ->label('Deduction Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Company Loan'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->placeholder(
                                'Describe the purpose of this deduction.'
                            ),
                    ])
                    ->columns(2),

                /*
                 * -------------------------------------------------
                 * CALCULATION
                 * -------------------------------------------------
                 */

                Section::make('Calculation')
                    ->description(
                        'Define how the deduction amount is calculated.'
                    )
                    ->schema([

                        Select::make('deduction_type')
                            ->label('Calculation Type')
                            ->options([
                                'fixed' => 'Fixed Amount',
                                'per_payroll' => 'Per Payroll',
                                'percentage_basic' => 'Percentage of Basic Salary',
                            ])
                            ->required()
                            ->default('fixed')
                            ->native(false)
                            ->live(),

                        TextInput::make('default_amount')
                            ->label('Default Amount')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(0)
                            ->prefix(
                                fn (callable $get) =>
                                    $get('deduction_type') === 'percentage_basic'
                                        ? '%'
                                        : '₱'
                            )
                            ->suffix(
                                fn (callable $get) =>
                                    $get('deduction_type') === 'percentage_basic'
                                        ? null
                                        : null
                            )
                            ->helperText(
                                fn (callable $get): string =>
                                    match ($get('deduction_type')) {
                                        'percentage_basic'
                                            => 'Enter the percentage. Example: 5 = 5%.',

                                        'per_payroll'
                                            => 'This amount is deducted every payroll period.',

                                        default
                                            => 'Enter the default peso amount.',
                                    }
                            ),

                    ])
                    ->columns(2),

                /*
                 * -------------------------------------------------
                 * APPLICATION
                 * -------------------------------------------------
                 */

                Section::make('Application')
                    ->description(
                        'Control whether this deduction is available and recurring.'
                    )
                    ->schema([

                        Toggle::make('is_recurring')
                            ->label('Recurring')
                            ->default(true)
                            ->inline(false)
                            ->helperText(
                                'Recurring deductions remain applicable while the employee assignment is active.'
                            ),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false)
                            ->helperText(
                                'Inactive deductions cannot be selected for new employee assignments.'
                            ),

                    ])
                    ->columns(2),
            ]);
    }
}
