<?php

namespace App\Filament\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Basic employee information.')
                    ->schema([
                        TextEntry::make('employee_id')
                            ->label('Employee ID')
                            ->badge(),

                        TextEntry::make('full_name')
                            ->label('Full Name')
                            ->weight('bold'),

                        TextEntry::make('date_of_birth')
                            ->label('Date of Birth')
                            ->date(),

                        TextEntry::make('sex')
                            ->label('Sex'),

                        TextEntry::make('civil_status')
                            ->label('Civil Status'),
                    ])
                    ->columns(2),

                Section::make('Contact Information')
                    ->description('Employee contact details.')
                    ->schema([
                        TextEntry::make('email')
                            ->label('Email Address'),

                        TextEntry::make('mobile_number')
                            ->label('Mobile Number'),

                        TextEntry::make('address')
                            ->label('Address')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Employment Information')
                    ->description('Employee employment details.')
                    ->schema([
                        TextEntry::make('department.name')
                            ->label('Department'),

                        TextEntry::make('position.name')
                            ->label('Position'),

                        TextEntry::make('employmentType.name')
                            ->label('Employment Type'),

                        TextEntry::make('date_hired')
                            ->label('Date Hired')
                            ->date(),

                        TextEntry::make('date_regularized')
                            ->label('Date Regularized')
                            ->date()
                            ->placeholder('Not yet regularized'),

                        TextEntry::make('employment_status')
                            ->label('Employment Status')
                            ->badge(),
                    ])
                    ->columns(2),

                Section::make('Payroll Information')
                    ->description('Information used for payroll processing.')
                    ->schema([
                        TextEntry::make('basic_salary')
                            ->label('Current Basic Salary')
                            ->money('PHP'),

                        TextEntry::make('pay_frequency')
                            ->label('Pay Frequency'),

                        TextEntry::make('payroll_enabled')
                            ->label('Payroll Status')
                            ->badge()
                            ->formatStateUsing(
                                fn ($state) => $state
                                    ? 'Enabled'
                                    : 'Disabled'
                            )
                            ->color(
                                fn ($state) => $state
                                    ? 'success'
                                    : 'gray'
                            ),
                    ])
                    ->columns(3),
            ]);
    }
}
