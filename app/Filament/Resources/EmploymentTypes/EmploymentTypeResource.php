<?php

namespace App\Filament\Resources\EmploymentTypes;

use App\Filament\Resources\EmploymentTypes\Pages\CreateEmploymentType;
use App\Filament\Resources\EmploymentTypes\Pages\EditEmploymentType;
use App\Filament\Resources\EmploymentTypes\Pages\ListEmploymentTypes;
use App\Filament\Resources\EmploymentTypes\Schemas\EmploymentTypeForm;
use App\Filament\Resources\EmploymentTypes\Tables\EmploymentTypesTable;
use App\Models\EmploymentType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmploymentTypeResource extends Resource
{
    protected static ?string $model = EmploymentType::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Management';
    protected static ?string $navigationLabel = 'Employment Types';
    protected static ?int $navigationSort = 4;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmploymentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmploymentTypesTable::configure($table);
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
            'index' => ListEmploymentTypes::route('/'),
            'create' => CreateEmploymentType::route('/create'),
            'edit' => EditEmploymentType::route('/{record}/edit'),
        ];
    }
}
