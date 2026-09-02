<?php

namespace App\Filament\Resources\Positions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Department')
                    ->relationship(
                        name: 'department',
                        titleAttribute: 'name'
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),

                TextInput::make('code')
                    ->label('Position Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),

                TextInput::make('name')
                    ->label('Position Name')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
