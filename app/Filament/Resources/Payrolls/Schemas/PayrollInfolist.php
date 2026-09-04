<?php

namespace App\Filament\Resources\Payrolls\Schemas;

use App\Services\Payroll\PayrollDashboardData;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('payroll_dashboard')
                    ->label('')
                    ->view('filament.resources.payrolls.infolists.payroll-dashboard')
                    ->viewData(fn ($record) => app(PayrollDashboardData::class)->getData($record))
                    ->columnSpanFull(),
            ]);
    }
}
