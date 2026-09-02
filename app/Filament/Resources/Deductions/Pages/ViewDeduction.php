<?php

namespace App\Filament\Resources\Deductions\Pages;

use App\Filament\Resources\Deductions\DeductionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeduction extends ViewRecord
{
    protected static string $resource = DeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
