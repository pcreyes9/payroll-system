<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Basic information about the employee.')
                    ->schema([

                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('middle_name')
                            ->label('Middle Name')
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('suffix')
                            ->label('Suffix')
                            ->maxLength(20)
                            ->placeholder('Jr., Sr., III'),

                        DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->native(false)
                            ->maxDate(now()),

                        Select::make('sex')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                            ])
                            ->native(false),

                        Select::make('civil_status')
                            ->label('Civil Status')
                            ->options([
                                'Single' => 'Single',
                                'Married' => 'Married',
                                'Widowed' => 'Widowed',
                                'Separated' => 'Separated',
                                'Divorced' => 'Divorced',
                            ])
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Contact Information')
                    ->description('Employee contact details.')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('mobile_number')
                            ->label('Mobile Number')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('address')
                            ->label('Address')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Employment Information')
                    ->description('Employee employment details.')
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->relationship(
                                name: 'department',
                                titleAttribute: 'name'
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('position_id', null);
                            })
                            ->required(),

                        Select::make('position_id')
                            ->label('Position')
                            ->options(function (callable $get) {
                                $departmentId = $get('department_id');

                                if (! $departmentId) {
                                    return [];
                                }

                                return \App\Models\Position::query()
                                    ->where('department_id', $departmentId)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->native(false)
                            ->disabled(fn (callable $get): bool => ! $get('department_id'))
                            ->required(),

                        Select::make('employment_type_id')
                            ->label('Employment Type')
                            ->relationship(
                                name: 'employmentType',
                                titleAttribute: 'name'
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        DatePicker::make('date_hired')
                            ->label('Date Hired')
                            ->native(false),

                        DatePicker::make('date_regularized')
                            ->label('Date Regularized')
                            ->native(false),

                        Select::make('employment_status')
                            ->label('Employment Status')
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                                'On Leave' => 'On Leave',
                                'Suspended' => 'Suspended',
                                'Resigned' => 'Resigned',
                                'Terminated' => 'Terminated',
                                'Retired' => 'Retired',
                            ])
                            ->default('Active')
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Payroll Information')
                    ->description('Information used for payroll processing.')
                    ->schema([
                        TextInput::make('basic_salary')
                            ->label('Current Basic Salary')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('pay_frequency')
                            ->label('Pay Frequency')
                            ->options([
                                'Monthly' => 'Monthly',
                                'Semi-Monthly' => 'Semi-Monthly',
                                'Weekly' => 'Weekly',
                                'Daily' => 'Daily',
                            ])
                            ->default('Semi-Monthly')
                            ->native(false)
                            ->required(),

                        Toggle::make('payroll_enabled')
                            ->label('Payroll Enabled')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
