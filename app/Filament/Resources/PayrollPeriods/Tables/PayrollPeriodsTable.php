<?php

namespace App\Filament\Resources\PayrollPeriods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Payroll Period')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label('Start')
                    ->date()
                    ->sortable(),

                TextColumn::make('period_end')
                    ->label('End')
                    ->date()
                    ->sortable(),

                TextColumn::make('pay_date')
                    ->label('Pay Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('pay_frequency')
                    ->label('Frequency')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'monthly' => 'Monthly',
                        'semi_monthly' => 'Semi-Monthly',
                        'weekly' => 'Weekly',
                        'bi_weekly' => 'Bi-Weekly',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'processing' => 'warning',
                        'calculated' => 'info',
                        'approved' => 'success',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
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
            ->defaultSort('period_start', 'desc');
    }
}
