<?php

namespace App\Filament\Resources\Allowances\Pages;

use App\Filament\Resources\Allowances\AllowanceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAllowance extends ViewRecord
{
    protected static string $resource = AllowanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
