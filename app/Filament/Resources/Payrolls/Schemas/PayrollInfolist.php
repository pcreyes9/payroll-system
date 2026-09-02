<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | Employee Information
                |--------------------------------------------------------------------------
                */

                Section::make('Employee Information')
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employee')
                            ->weight('bold'),

                        TextEntry::make('employee.employee_id')
                            ->label('Employee ID'),

                        TextEntry::make('employee.department.name')
                            ->label('Department'),

                        TextEntry::make('employee.position.name')
                            ->label('Position'),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | Payroll Information
                |--------------------------------------------------------------------------
                */

                Section::make('Payroll Information')
                    ->schema([
                        TextEntry::make('payrollPeriod.name')
                            ->label('Payroll Period'),

                        TextEntry::make('payrollPeriod.period_start')
                            ->label('Period Start')
                            ->date(),

                        TextEntry::make('payrollPeriod.period_end')
                            ->label('Period End')
                            ->date(),

                        TextEntry::make('payrollPeriod.pay_date')
                            ->label('Pay Date')
                            ->date(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ])
                    ->columns(3),

                /*
                |--------------------------------------------------------------------------
                | Payroll Summary
                |--------------------------------------------------------------------------
                */

                Section::make('Payroll Summary')
                    ->schema([
                        TextEntry::make('basic_salary')
                            ->label('Monthly Basic Salary')
                            ->money('PHP'),

                        TextEntry::make('basic_pay')
                            ->label('Basic Pay')
                            ->money('PHP'),

                        TextEntry::make('overtime_pay')
                            ->label('Overtime Pay')
                            ->money('PHP'),

                        TextEntry::make('other_earnings')
                            ->label('Other Earnings')
                            ->money('PHP'),

                        TextEntry::make('gross_pay')
                            ->label('Gross Pay')
                            ->money('PHP')
                            ->weight('bold'),
                    ])
                    ->columns(3),

                /*
                |--------------------------------------------------------------------------
                | Net Pay
                |--------------------------------------------------------------------------
                */

                Section::make('Net Pay')
                    ->schema([
                        TextEntry::make('net_pay')
                            ->label('NET PAY')
                            ->money('PHP')
                            ->weight('bold')
                            ->size('3xl'),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Allowances
                |--------------------------------------------------------------------------
                */

                Section::make('Allowances')
                    ->schema([
                        RepeatableEntry::make('allowanceItems')
                            ->label('Allowances Items')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Allowance')
                                    ->weight('medium'),

                                TextEntry::make('quantity')
                                    ->label('Qty'),

                                TextEntry::make('rate')
                                    ->label('Rate')
                                    ->money('PHP'),

                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money('PHP')
                                    ->weight('semibold'),
                            ])
                            ->columns(4)
                            ->visible(
                                fn ($record) =>
                                    $record->allowanceItems()->exists()
                            ),

                        TextEntry::make('allowances')
                            ->label('Total Allowances')
                            ->money('PHP')
                            ->weight('bold')
                            ->visible(
                                fn ($record) =>
                                    (float) $record->allowances > 0
                            ),
                    ])
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Deductions
                |--------------------------------------------------------------------------
                */

                Section::make('Deductions')
                    ->schema([
                        RepeatableEntry::make('deductions')
                            ->label('Deduction Items')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Deduction')
                                    ->weight('medium'),

                                TextEntry::make('quantity')
                                    ->label('Qty'),

                                TextEntry::make('rate')
                                    ->label('Rate')
                                    ->money('PHP'),

                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money('PHP')
                                    ->weight('semibold'),
                            ])
                            ->columns(4),

                        TextEntry::make('total_deductions')
                            ->label('Total Deductions')
                            ->money('PHP')
                            ->weight('bold')
                            ->visible(
                                fn ($record) =>
                                    (float) $record->total_deductions > 0
                            ),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
