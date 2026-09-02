<?php

namespace App\Filament\Resources\PayrollPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payroll Period')
                    ->schema([
                        TextInput::make('name')
                            ->label('Period Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('August 2026 - 1st Half'),

                        Select::make('pay_frequency')
                            ->label('Pay Frequency')
                            ->options([
                                'monthly' => 'Monthly',
                                'semi_monthly' => 'Semi-Monthly',
                                'weekly' => 'Weekly',
                                'bi_weekly' => 'Bi-Weekly',
                            ])
                            ->required()
                            ->default('semi_monthly'),

                        DatePicker::make('period_start')
                            ->label('Period Start')
                            ->required()
                            ->native(false),

                        DatePicker::make('period_end')
                            ->label('Period End')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('period_start'),

                        DatePicker::make('pay_date')
                            ->label('Pay Date')
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'processing' => 'Processing',
                                'calculated' => 'Calculated',
                                'approved' => 'Approved',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('draft'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
