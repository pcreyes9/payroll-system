<?php

namespace App\Filament\Resources\Deductions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeductionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Deduction')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deduction_type')
                    ->label('Calculation')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed' => 'Fixed Amount',
                        'percentage_basic' => '% of Basic Salary',
                        'per_payroll' => 'Per Payroll',
                        default => $state,
                    }),

                TextColumn::make('default_amount')
                    ->label('Default Amount')
                    ->money('PHP')
                    ->sortable(),

                IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
