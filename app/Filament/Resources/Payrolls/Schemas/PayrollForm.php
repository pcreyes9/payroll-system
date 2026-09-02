<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payroll_period_id')
                    ->relationship('payrollPeriod', 'name')
                    ->required(),
                Select::make('employee_id')
                    ->relationship('employee', 'id')
                    ->required(),
                TextInput::make('basic_salary')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('basic_pay')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('allowances')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('overtime_pay')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('other_earnings')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('gross_pay')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('sss_contribution')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('philhealth_contribution')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('pagibig_contribution')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('withholding_tax')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('other_deductions')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_deductions')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('net_pay')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                DateTimePicker::make('calculated_at'),
                DateTimePicker::make('approved_at'),
                DateTimePicker::make('paid_at'),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
