<?php

namespace App\Filament\Resources\Payrolls\Tables;

use App\Models\Payroll;
use App\Services\Payroll\PayrollProcessor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\SelectFilter;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->weight('bold')
                    ->description(
                        fn ($record) => $record->employee?->employee_id
                    )
                    ->searchable([
                        'first_name',
                        'middle_name',
                        'last_name',
                    ]),

                TextColumn::make('payrollPeriod.name')
                    ->label('Payroll Period')
                    ->description(
                        fn ($record) => $record->payrollPeriod?->pay_date?->format('M d, Y')
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employee.department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gross_pay')
                    ->label('Gross Pay')
                    ->money('PHP')
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('total_deductions')
                    ->label('Deductions')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('net_pay')
                    ->label('Net Pay')
                    ->money('PHP')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'Draft',
                        'processing' => 'Processing',
                        'calculated' => 'Calculated',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($state ?? ''),
                    })
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'processing' => 'warning',
                        'calculated' => 'info',
                        'approved' => 'success',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])

            ->filters([
                SelectFilter::make('payroll_period_id')
                    ->label('Payroll Period')
                    ->relationship('payrollPeriod', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'processing' => 'Processing',
                        'calculated' => 'Calculated',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('employee.department_id')
                    ->label('Department')
                    ->relationship('employee.department', 'name')
                    ->searchable()
                    ->preload(),
            ])

            ->recordActions([

                ViewAction::make(),

                ActionGroup::make([

                    Action::make('calculate')
                        ->label('Calculate')
                        ->icon('heroicon-o-calculator')
                        ->color('info')
                        ->visible(fn (Payroll $record): bool =>
                            in_array($record->status, [
                                Payroll::STATUS_DRAFT,
                                Payroll::STATUS_CALCULATING,
                                Payroll::STATUS_CALCULATED,
                            ], true)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Calculate Payroll')
                        ->modalDescription(
                            'This will recalculate the payroll using the current salary, allowances, deductions, and statutory deductions.'
                        )
                        ->action(function (Payroll $record): void {
                            try {
                                app(PayrollProcessor::class)
                                    ->calculate($record);

                                Notification::make()
                                    ->title('Payroll calculated')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Calculation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Payroll $record): bool =>
                            $record->status === Payroll::STATUS_CALCULATED
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Approve Payroll')
                        ->modalDescription(
                            'Once approved, this payroll can no longer be recalculated.'
                        )
                        ->action(function (Payroll $record): void {
                            try {
                                app(PayrollProcessor::class)
                                    ->approve($record);

                                Notification::make()
                                    ->title('Payroll approved')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Approval failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('mark_as_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Payroll $record): bool =>
                            $record->status === Payroll::STATUS_APPROVED
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mark Payroll as Paid')
                        ->modalDescription(
                            'This will mark the payroll as paid and record the payment date and time.'
                        )
                        ->action(function (Payroll $record): void {
                            try {
                                app(PayrollProcessor::class)
                                    ->markAsPaid($record);

                                Notification::make()
                                    ->title('Payroll marked as paid')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Payment update failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Payroll $record): bool =>
                            ! in_array($record->status, [
                                Payroll::STATUS_PAID,
                                Payroll::STATUS_CANCELLED,
                            ], true)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Payroll')
                        ->modalDescription(
                            'Are you sure you want to cancel this payroll?'
                        )
                        ->action(function (Payroll $record): void {
                            try {
                                app(PayrollProcessor::class)
                                    ->cancel($record);

                                Notification::make()
                                    ->title('Payroll cancelled')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Cancellation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    // ->button()
                    ->color('gray'),
            ])

            ->defaultSort('id', 'desc');
    }
}
