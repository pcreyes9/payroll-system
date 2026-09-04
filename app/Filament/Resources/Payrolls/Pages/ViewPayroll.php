<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Filament\Resources\Payrolls\PayrollResource;
use App\Services\Payroll\PayrollProcessor;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    public function getTitle(): string
    {
        return $this->record->employee?->full_name ?? 'Payroll';
    }

    public function calculatePayroll(): void
    {
        try {
            $this->record = app(PayrollProcessor::class)
                ->calculate($this->record);

            Notification::make()
                ->title('Payroll calculated')
                ->body('The payroll has been recalculated successfully.')
                ->success()
                ->send();

        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Calculation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
