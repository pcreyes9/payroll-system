<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                /*
                |--------------------------------------------------------------------------
                | Employee
                |--------------------------------------------------------------------------
                */

                TextColumn::make('full_name')
                    ->label('Employee')
                    ->state(fn ($record) => $record->full_name)
                    ->weight('bold')
                    ->description(
                        fn ($record) => $record->employee_id
                    )
                    ->searchable([
                        'first_name',
                        'middle_name',
                        'last_name',
                        'suffix',
                        'employee_id',
                    ])
                    ->sortable(
                        ['last_name', 'first_name']
                    ),

                /*
                |--------------------------------------------------------------------------
                | Organization
                |--------------------------------------------------------------------------
                */

                TextColumn::make('department.name')
                    ->label('Department')
                    ->weight('bold')
                    ->description(
                        fn ($record) => $record->position?->name
                    )
                    ->searchable()
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Employment
                |--------------------------------------------------------------------------
                */

                TextColumn::make('employmentType.name')
                    ->label('Employment')
                    ->weight('bold')
                    ->description(
                        fn ($record) => match ($record->employment_status) {
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'resigned' => 'Resigned',
                            'terminated' => 'Terminated',
                            'retired' => 'Retired',
                            default => ucfirst(
                                $record->employment_status ?? 'Unknown'
                            ),
                        }
                    )
                    ->searchable()
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Compensation
                |--------------------------------------------------------------------------
                */

                TextColumn::make('basic_salary')
                    ->label('Basic Salary')
                    ->money('PHP')
                    ->weight('bold')
                    ->description(
                        fn ($record) => $record->payroll_enabled
                            ? 'Payroll Enabled'
                            : 'Payroll Disabled'
                    )
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | Status
                |--------------------------------------------------------------------------
                */

                IconColumn::make('payroll_enabled')
                    ->label('Payroll')
                    ->boolean()
                    ->sortable(),
            ])

            ->filters([

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('position_id')
                    ->label('Position')
                    ->relationship('position', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('employment_type_id')
                    ->label('Employment Type')
                    ->relationship('employmentType', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('employment_status')
                    ->label('Employment Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'resigned' => 'Resigned',
                        'terminated' => 'Terminated',
                        'retired' => 'Retired',
                    ]),

                SelectFilter::make('payroll_enabled')
                    ->label('Payroll')
                    ->options([
                        1 => 'Enabled',
                        0 => 'Disabled',
                    ]),
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

            ->defaultSort('last_name', 'asc');
    }
}
