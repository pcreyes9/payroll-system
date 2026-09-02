<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeSalary')
                ->label('Change Salary')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->schema([
                    TextInput::make('current_salary')
                        ->label('Current Salary')
                        ->prefix('₱')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(fn () => $this->record->basic_salary),

                    TextInput::make('new_salary')
                        ->label('New Salary')
                        ->numeric()
                        ->prefix('₱')
                        ->minValue(0.01)
                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if ((float) $value === (float) $this->record->basic_salary) {
                                    $fail('The new salary must be different from the current salary.');
                                }
                            };
                        }),

                    DatePicker::make('effective_date')
                        ->label('Effective Date')
                        ->native(false)
                        ->minDate(fn () => $this->record->date_hired)
                        ->required(),

                    TextInput::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Annual Salary Increase'),
                ])
                ->action(function (array $data): void {
                    $employee = $this->record;

                    $effectiveDate = Carbon::parse($data['effective_date']);

                    if (
                        $employee->date_hired &&
                        $effectiveDate->lt(Carbon::parse($employee->date_hired))
                    ) {
                        Notification::make()
                            ->title('Invalid effective date')
                            ->body('The salary effective date cannot be earlier than the employee hire date.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $currentHistory = $employee
                        ->salaryHistories()
                        ->whereNull('end_date')
                        ->latest('effective_date')
                        ->first();

                    if ($currentHistory) {
                        $currentEffectiveDate = Carbon::parse(
                            $currentHistory->effective_date
                        );

                        if ($effectiveDate->lte($currentEffectiveDate)) {
                            Notification::make()
                                ->title('Invalid effective date')
                                ->body('The new salary must take effect after the current salary.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $currentHistory->update([
                            'end_date' => $effectiveDate->copy()->subDay(),
                        ]);
                    }

                    $employee->salaryHistories()->create([
                        'basic_salary' => $data['new_salary'],
                        'effective_date' => $effectiveDate,
                        'reason' => $data['reason'],
                    ]);

                    $employee->update([
                        'basic_salary' => $data['new_salary'],
                    ]);
                })
                ->successNotificationTitle('Salary updated successfully'),

            DeleteAction::make(),
        ];
    }
}
