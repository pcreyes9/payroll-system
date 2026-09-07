<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Allowance;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllowancesRelationManager extends RelationManager
{
    protected static string $relationship = 'allowances';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                 * -------------------------------------------------
                 * ALLOWANCE
                 * -------------------------------------------------
                 */

                Select::make('allowance_id')
                    ->label('Allowance')
                    ->relationship('allowance', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (
                        $state,
                        callable $set,
                        callable $get
                    ): void {

                        /*
                         * No allowance selected.
                         */
                        if (! $state) {
                            $set('percentage', null);
                            $set('amount', null);

                            return;
                        }

                        $allowance = Allowance::find($state);

                        /*
                         * Allowance not found.
                         */
                        if (! $allowance) {
                            $set('percentage', null);
                            $set('amount', null);

                            return;
                        }

                        /*
                         * -------------------------------------------------
                         * PERCENTAGE-BASED ALLOWANCE
                         * -------------------------------------------------
                         *
                         * Percentage is entered per employee.
                         *
                         * Example:
                         *
                         * Basic Salary = ₱20,604.83
                         * Percentage   = 10%
                         *
                         * Amount = ₱2,060.48
                         */

                        if (
                            $allowance->calculation_type ===
                            'percentage_basic'
                        ) {
                            /*
                             * If selecting a new allowance,
                             * start with no percentage.
                             */
                            $set('percentage', null);
                            $set('amount', 0);

                            return;
                        }

                        /*
                         * -------------------------------------------------
                         * NON-PERCENTAGE ALLOWANCE
                         * -------------------------------------------------
                         *
                         * Use the allowance's default amount.
                         */

                        $set('percentage', null);

                        $set(
                            'amount',
                            round(
                                (float) (
                                    $allowance->default_amount ?? 0
                                ),
                                2
                            )
                        );
                    }),


                /*
                 * -------------------------------------------------
                 * EMPLOYEE-SPECIFIC PERCENTAGE
                 * -------------------------------------------------
                 */

                TextInput::make('percentage')
                    ->label('Percentage of Basic Salary')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->live()

                    /*
                     * Show only when the selected allowance
                     * uses percentage of basic salary.
                     */
                    ->visible(function (callable $get): bool {

                        $allowanceId =
                            $get('allowance_id');

                        if (! $allowanceId) {
                            return false;
                        }

                        return Allowance::query()
                            ->whereKey($allowanceId)
                            ->where(
                                'calculation_type',
                                'percentage_basic'
                            )
                            ->exists();
                    })

                    /*
                     * Percentage is required for
                     * percentage-based allowances.
                     */
                    ->required(function (callable $get): bool {

                        $allowanceId =
                            $get('allowance_id');

                        if (! $allowanceId) {
                            return false;
                        }

                        return Allowance::query()
                            ->whereKey($allowanceId)
                            ->where(
                                'calculation_type',
                                'percentage_basic'
                            )
                            ->exists();
                    })

                    /*
                     * Calculate the amount whenever
                     * the employee's percentage changes.
                     */
                    ->afterStateUpdated(function (
                        $state,
                        callable $set,
                        callable $get
                    ): void {

                        $allowanceId =
                            $get('allowance_id');

                        /*
                         * No allowance selected.
                         */
                        if (! $allowanceId) {
                            $set('amount', 0);

                            return;
                        }

                        $allowance =
                            Allowance::find($allowanceId);

                        if (! $allowance) {
                            $set('amount', 0);

                            return;
                        }

                        /*
                         * Only calculate percentage-based
                         * allowances here.
                         */
                        if (
                            $allowance->calculation_type !==
                            'percentage_basic'
                        ) {
                            return;
                        }

                        /*
                         * Get the employee that owns
                         * this relation manager.
                         */
                        $employee =
                            $this->getOwnerRecord();

                        /*
                         * Current basic salary.
                         */
                        $basicSalary = (float) (
                            $employee->basic_salary ?? 0
                        );

                        /*
                         * Employee-specific percentage.
                         */
                        $percentage = (float) (
                            $state ?? 0
                        );

                        /*
                         * Calculate allowance.
                         *
                         * Basic Salary × Percentage ÷ 100
                         */
                        $amount =
                            $basicSalary
                            * ($percentage / 100);

                        /*
                         * Store/display amount rounded
                         * to two decimal places.
                         */
                        $set(
                            'amount',
                            round($amount, 2)
                        );
                    })

                    /*
                     * Recalculate the amount when editing
                     * an existing employee allowance.
                     *
                     * This is important because the record may
                     * already contain a percentage but the stored
                     * amount may still be ₱0.00 or outdated.
                     */
                    ->afterStateHydrated(function (
                        $state,
                        callable $set,
                        callable $get
                    ): void {

                        $allowanceId =
                            $get('allowance_id');

                        if (
                            ! $allowanceId ||
                            $state === null
                        ) {
                            return;
                        }

                        $allowance =
                            Allowance::find($allowanceId);

                        if (! $allowance) {
                            return;
                        }

                        if (
                            $allowance->calculation_type !==
                            'percentage_basic'
                        ) {
                            return;
                        }

                        $employee =
                            $this->getOwnerRecord();

                        $basicSalary = (float) (
                            $employee->basic_salary ?? 0
                        );

                        $percentage = (float) $state;

                        $amount =
                            $basicSalary
                            * ($percentage / 100);

                        $set(
                            'amount',
                            round($amount, 2)
                        );
                    }),


                /*
                 * -------------------------------------------------
                 * AMOUNT
                 * -------------------------------------------------
                 */

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0)
                    ->required()

                    /*
                     * Disable amount when the allowance
                     * is percentage-based.
                     */
                    ->disabled(function (
                        callable $get
                    ): bool {

                        $allowanceId =
                            $get('allowance_id');

                        if (! $allowanceId) {
                            return false;
                        }

                        return Allowance::query()
                            ->whereKey($allowanceId)
                            ->where(
                                'calculation_type',
                                'percentage_basic'
                            )
                            ->exists();
                    })

                    /*
                     * Disabled fields normally aren't
                     * submitted. Keep the calculated amount
                     * in the form state/database.
                     */
                    ->dehydrated(),


                /*
                 * -------------------------------------------------
                 * EFFECTIVE DATE
                 * -------------------------------------------------
                 */

                DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->native(false)
                    ->required()
                    ->default(now()),


                /*
                 * -------------------------------------------------
                 * END DATE
                 * -------------------------------------------------
                 */

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->native(false)
                    ->afterOrEqual('effective_date'),


                /*
                 * -------------------------------------------------
                 * ACTIVE
                 * -------------------------------------------------
                 */

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                /*
                 * CODE
                 */

                TextColumn::make('allowance.code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),


                /*
                 * ALLOWANCE NAME
                 */

                TextColumn::make('allowance.name')
                    ->label('Allowance')
                    ->searchable()
                    ->sortable(),


                /*
                 * EMPLOYEE-SPECIFIC PERCENTAGE
                 */

                TextColumn::make('percentage')
                    ->label('Percentage')
                    ->formatStateUsing(function ($state): string {

                        if ($state === null) {
                            return '—';
                        }

                        return number_format(
                            (float) $state,
                            2
                        ) . '%';
                    })
                    ->sortable(),


                /*
                 * CALCULATED/STORED AMOUNT
                 */

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable(),


                /*
                 * EFFECTIVE DATE
                 */

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),


                /*
                 * END DATE
                 */

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Present')
                    ->sortable(),


                /*
                 * ACTIVE
                 */

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])

            /*
             * -------------------------------------------------
             * CREATE
             * -------------------------------------------------
             */

            ->headerActions([

                CreateAction::make()

                    ->before(function (
                        CreateAction $action,
                        array $data
                    ): void {

                        $employee =
                            $this->getOwnerRecord();

                        /*
                         * New allowance period.
                         */
                        $newStart =
                            Carbon::parse(
                                $data['effective_date']
                            );

                        $newEnd =
                            ! empty($data['end_date'])
                                ? Carbon::parse(
                                    $data['end_date']
                                )
                                : null;

                        /*
                         * Check if the employee already has
                         * the same allowance during an
                         * overlapping period.
                         */
                        $overlapExists =
                            $employee
                                ->allowances()
                                ->where(
                                    'allowance_id',
                                    $data['allowance_id']
                                )
                                ->get()
                                ->contains(
                                    function ($existing)
                                    use (
                                        $newStart,
                                        $newEnd
                                    ): bool {

                                        $existingStart =
                                            Carbon::parse(
                                                $existing
                                                    ->effective_date
                                            );

                                        $existingEnd =
                                            $existing->end_date
                                                ? Carbon::parse(
                                                    $existing
                                                        ->end_date
                                                )
                                                : null;

                                        /*
                                         * New period must end
                                         * after existing period starts.
                                         */
                                        $newPeriodEndsAfterExistingStarts =
                                            ! $newEnd
                                            || $newEnd->gte(
                                                $existingStart
                                            );

                                        /*
                                         * Existing period must end
                                         * after new period starts.
                                         */
                                        $existingPeriodEndsAfterNewStarts =
                                            ! $existingEnd
                                            || $existingEnd->gte(
                                                $newStart
                                            );

                                        return
                                            $newPeriodEndsAfterExistingStarts
                                            && $existingPeriodEndsAfterNewStarts;
                                    }
                                );

                        if ($overlapExists) {

                            Notification::make()
                                ->title(
                                    'Overlapping allowance'
                                )
                                ->body(
                                    'This employee already has this allowance during the selected period.'
                                )
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])

            /*
             * -------------------------------------------------
             * RECORD ACTIONS
             * -------------------------------------------------
             */

            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}