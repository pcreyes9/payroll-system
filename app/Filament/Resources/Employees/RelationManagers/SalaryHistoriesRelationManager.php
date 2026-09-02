<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalaryHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryHistories';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('basic_salary')
                    ->label('Basic Salary')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0)
                    ->required(),

                DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->native(false)
                    ->required(),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->native(false)
                    ->afterOrEqual('effective_date'),

                TextInput::make('reason')
                    ->label('Reason')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Present'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->searchable(),
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
