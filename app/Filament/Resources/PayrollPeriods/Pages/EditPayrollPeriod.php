<?php

namespace App\Filament\Resources\PayrollPeriods\Pages;

use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use App\Services\Payroll\PayrollGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Models\Payroll;
use App\Services\Payroll\PayrollCalculator;

class EditPayrollPeriod extends EditRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generatePayroll')
                ->label('Generate Payroll')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function () {
                    try {
                        $count = app(PayrollGenerator::class)
                            ->generate($this->record);

                        Notification::make()
                            ->title('Payroll generated')
                            ->body("{$count} employee payroll record(s) were created.")
                            ->success()
                            ->send();

                        $this->refreshFormData([
                            'status',
                        ]);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Payroll generation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Cancel Payroll')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => in_array(
                    $this->record->status,
                    ['draft', 'processing']
                ))
                ->action(function () {
                    $this->record->update([
                        'status' => 'cancelled',
                    ]);

                    $this->refreshFormData([
                        'status',
                    ]);

                    Notification::make()
                        ->title('Payroll cancelled')
                        ->success()
                        ->send();
                }),

                Action::make('calculatePayroll')
                ->label('Calculate Payroll')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'processing')
                ->action(function () {
                    try {
                        $payrolls = $this->record
                            ->payrolls()
                            ->where('status', Payroll::STATUS_DRAFT)
                            ->get();

                        $calculator = app(PayrollCalculator::class);

                        foreach ($payrolls as $payroll) {
                            $calculator->calculate($payroll);
                        }

                        Notification::make()
                            ->title('Payroll calculated')
                            ->body("{$payrolls->count()} payroll record(s) calculated successfully.")
                            ->success()
                            ->send();

                        $this->refreshFormData([
                            'status',
                        ]);

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Payroll calculation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
