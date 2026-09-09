<?php

namespace App\Filament\Resources\Payrolls\Pages;

use App\Exports\PayrollExport;
use App\Filament\Resources\Payrolls\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('Export Payroll')
                ->modalDescription(
                    'Select a payroll period to export the payroll and attendance records.'
                )
                ->schema([
                    Select::make('payroll_period_id')
                        ->label('Payroll Period')
                        ->options(
                            PayrollPeriod::query()
                                ->orderByDesc('period_start')
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $periodId = (int) $data['payroll_period_id'];

                    $hasPayroll = Payroll::query()
                        ->where('payroll_period_id', $periodId)
                        ->exists();

                    if (! $hasPayroll) {
                        Notification::make()
                            ->title('No payroll records found')
                            ->body(
                                'There are no payroll records for the selected payroll period.'
                            )
                            ->warning()
                            ->send();

                        return;
                    }

                    $payrollPeriod = PayrollPeriod::find($periodId);

                    $periodName = $payrollPeriod?->name
                        ? str_replace(
                            [' ', '/', '\\'],
                            '-',
                            $payrollPeriod->name
                        )
                        : now()->format('Y-m-d');

                    $filename = 'PSA-PAYROLL-' . $periodName . '.xlsx';

                    return Excel::download(
                        new PayrollExport($periodId),
                        $filename
                    );
                }),
        ];
    }
}