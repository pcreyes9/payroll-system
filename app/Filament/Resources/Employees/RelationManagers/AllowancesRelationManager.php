<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Allowance;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class AllowancesRelationManager extends RelationManager
{
    protected static string $relationship = 'allowances';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('allowance_id')
                    ->label('Allowance')
                    ->relationship('allowance', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! $state) {
                            return;
                        }

                        $allowance = Allowance::find($state);

                        if ($allowance) {
                            $set('amount', $allowance->default_amount);
                        }
                    }),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0)
                    ->required(),

                DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->native(false)
                    ->required()
                    ->default(now()),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->native(false)
                    ->afterOrEqual('effective_date'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('allowance.code')
                    ->label('Code')
                    ->searchable(),

                TextColumn::make('allowance.name')
                    ->label('Allowance')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Present'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->before(function (CreateAction $action, array $data): void {
                        $employee = $this->getOwnerRecord();

                        $newStart = Carbon::parse($data['effective_date']);

                        $newEnd = ! empty($data['end_date'])
                            ? Carbon::parse($data['end_date'])
                            : null;

                        $overlapExists = $employee->allowances()
                            ->where('allowance_id', $data['allowance_id'])
                            ->get()
                            ->contains(function ($existing) use ($newStart, $newEnd) {
                                $existingStart = Carbon::parse($existing->effective_date);

                                $existingEnd = $existing->end_date
                                    ? Carbon::parse($existing->end_date)
                                    : null;

                                $newPeriodEndsAfterExistingStarts =
                                    ! $newEnd || $newEnd->gte($existingStart);

                                $existingPeriodEndsAfterNewStarts =
                                    ! $existingEnd || $existingEnd->gte($newStart);

                                return $newPeriodEndsAfterExistingStarts
                                    && $existingPeriodEndsAfterNewStarts;
                            });

                        if ($overlapExists) {
                            Notification::make()
                                ->title('Overlapping allowance')
                                ->body('This employee already has this allowance during the selected period.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
