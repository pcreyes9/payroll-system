<?php

namespace App\Filament\Resources\Allowances\Tables;

use App\Filament\Resources\Allowances\AllowanceResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllowancesTable
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
                    ->label('Allowance')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('calculation_type')
                    ->label('Calculation')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fixed' => 'Fixed Amount',
                        'percentage_basic' => '% of Basic Salary',
                        'per_day' => 'Per Day',
                        'per_hour' => 'Per Hour',
                        default => $state,
                    }),

                TextColumn::make('default_amount')
                    ->label('Default Amount')
                    ->money('PHP')
                    ->sortable(),

                IconColumn::make('is_taxable')
                    ->label('Taxable')
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
            ->recordUrl(
                fn ($record) => AllowanceResource::getUrl('edit', ['record' => $record]),
            )
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