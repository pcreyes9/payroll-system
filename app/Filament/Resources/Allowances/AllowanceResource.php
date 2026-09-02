<?php

namespace App\Filament\Resources\Allowances;

use App\Filament\Resources\Allowances\Pages\CreateAllowance;
use App\Filament\Resources\Allowances\Pages\EditAllowance;
use App\Filament\Resources\Allowances\Pages\ListAllowances;
use App\Filament\Resources\Allowances\Pages\ViewAllowance;
use App\Filament\Resources\Allowances\Schemas\AllowanceForm;
use App\Filament\Resources\Allowances\Schemas\AllowanceInfolist;
use App\Filament\Resources\Allowances\Tables\AllowancesTable;
use App\Models\Allowance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AllowanceResource extends Resource
{
    protected static ?string $model = Allowance::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Compensation';
    protected static ?string $navigationLabel = 'Allowances';
    protected static ?int $navigationSort = 1;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AllowanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AllowanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllowancesTable::configure($table);
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
            'index' => ListAllowances::route('/'),
            'create' => CreateAllowance::route('/create'),
            'view' => ViewAllowance::route('/{record}'),
            'edit' => EditAllowance::route('/{record}/edit'),
        ];
    }
}
