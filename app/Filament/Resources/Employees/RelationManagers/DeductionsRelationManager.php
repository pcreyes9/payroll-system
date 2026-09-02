<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Deduction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeductionsRelationManager extends RelationManager
{
    protected static string $relationship = 'deductions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Deduction')
                    ->description('Select the deduction to apply to this employee.')
                    ->schema([

                        Select::make('deduction_id')
                            ->label('Deduction')
                            ->relationship('deduction', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {

                                if (! $state) {
                                    return;
                                }

                                $deduction = Deduction::find($state);

                                if (! $deduction) {
                                    return;
                                }

                                $set(
                                    'amount',
                                    $deduction->default_amount
                                );

                                $set(
                                    'original_amount',
                                    $deduction->default_amount
                                );

                                $set(
                                    'installment_amount',
                                    $deduction->default_amount
                                );

                                $set(
                                    'schedule_type',
                                    $deduction->is_recurring
                                        ? 'recurring'
                                        : 'one_time'
                                );
                            }),

                        TextInput::make('amount')
                            ->label('Deduction Amount')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0.01)
                            ->required(),

                        Select::make('schedule_type')
                            ->label('Schedule')
                            ->options([
                                'one_time' => 'One Time',
                                'recurring' => 'Recurring',
                                'installment' => 'Installment',
                            ])
                            ->default('recurring')
                            ->native(false)
                            ->live()
                            ->required(),

                    ])
                    ->columns(2),

                Section::make('Installment Details')
                    ->description('Use this section for loans, advances, and other deductions paid over multiple payrolls.')
                    ->visible(
                        fn (Get $get): bool =>
                            $get('schedule_type') === 'installment'
                    )
                    ->schema([

                        TextInput::make('original_amount')
                            ->label('Original Amount')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0.01)
                            ->required()
                            ->live(),

                        TextInput::make('total_installments')
                            ->label('Total Installments')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->live(),

                        TextInput::make('paid_installments')
                            ->label('Paid Installments')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('installment_amount')
                            ->label('Installment per Payroll')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0.01)
                            ->required()
                            ->live(),

                        TextInput::make('remaining_balance')
                            ->label('Remaining Balance')
                            ->numeric()
                            ->prefix('₱')
                            ->disabled()
                            ->dehydrated(),

                    ])
                    ->columns(2),

                Section::make('Schedule')
                    ->schema([

                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false)
                            ->required()
                            ->default(now()),

                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->native(false)
                            ->required()
                            ->default(now()),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false)
                            ->afterOrEqual('effective_date'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),

                    ])
                    ->columns(2),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('deduction.code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deduction.name')
                    ->label('Deduction')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Schedule')
                    ->badge(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('installment_amount')
                    ->label('Installment')
                    ->money('PHP')
                    ->placeholder('—'),

                TextColumn::make('remaining_balance')
                    ->label('Balance')
                    ->money('PHP')
                    ->placeholder('—'),

                TextColumn::make('effective_date')
                    ->label('Effective')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Present'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
