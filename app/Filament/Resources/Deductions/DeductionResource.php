<?php

namespace App\Filament\Resources\Deductions;

use App\Filament\Resources\Deductions\Pages\CreateDeduction;
use App\Filament\Resources\Deductions\Pages\EditDeduction;
use App\Filament\Resources\Deductions\Pages\ListDeductions;
use App\Filament\Resources\Deductions\Pages\ViewDeduction;
use App\Filament\Resources\Deductions\Schemas\DeductionForm;
use App\Filament\Resources\Deductions\Schemas\DeductionInfolist;
use App\Filament\Resources\Deductions\Tables\DeductionsTable;
use App\Models\Deduction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeductionResource extends Resource
{
    protected static ?string $model = Deduction::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compensation';
    protected static ?string $navigationLabel = 'Deductions';
    protected static ?int $navigationSort = 2;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-minus-circle';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DeductionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeductionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeductionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeductions::route('/'),
            'create' => CreateDeduction::route('/create'),
            'view' => ViewDeduction::route('/{record}'),
            'edit' => EditDeduction::route('/{record}/edit'),
        ];
    }
}
