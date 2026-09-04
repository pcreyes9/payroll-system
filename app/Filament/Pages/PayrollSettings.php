<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class PayrollSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Payroll Settings';

    protected static ?string $navigationLabel = 'Payroll Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.pages.payroll-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            /*
             * Regular Day
             */
            'regular_day_rate' => Setting::getValue(
                'payroll',
                'regular_day_rate',
                100
            ),

            'regular_day_overtime_rate' => Setting::getValue(
                'payroll',
                'regular_day_overtime_rate',
                125
            ),

            /*
             * Rest Day
             */
            'rest_day_rate' => Setting::getValue(
                'payroll',
                'rest_day_rate',
                130
            ),

            'rest_day_overtime_rate' => Setting::getValue(
                'payroll',
                'rest_day_overtime_rate',
                169
            ),

            /*
             * Special Non-Working Holiday
             */
            'special_holiday_rate' => Setting::getValue(
                'payroll',
                'special_holiday_rate',
                130
            ),

            'special_holiday_overtime_rate' => Setting::getValue(
                'payroll',
                'special_holiday_overtime_rate',
                169
            ),

            /*
             * Special Non-Working Holiday + Rest Day
             */
            'special_holiday_rest_day_rate' => Setting::getValue(
                'payroll',
                'special_holiday_rest_day_rate',
                150
            ),

            'special_holiday_rest_day_overtime_rate' => Setting::getValue(
                'payroll',
                'special_holiday_rest_day_overtime_rate',
                195
            ),

            /*
             * Regular Holiday
             */
            'regular_holiday_rate' => Setting::getValue(
                'payroll',
                'regular_holiday_rate',
                200
            ),

            'regular_holiday_overtime_rate' => Setting::getValue(
                'payroll',
                'regular_holiday_overtime_rate',
                260
            ),

            /*
             * Regular Holiday + Rest Day
             */
            'regular_holiday_rest_day_rate' => Setting::getValue(
                'payroll',
                'regular_holiday_rest_day_rate',
                260
            ),

            'regular_holiday_rest_day_overtime_rate' => Setting::getValue(
                'payroll',
                'regular_holiday_rest_day_overtime_rate',
                338
            ),

            /*
             * Night Shift Differential
             */
            'night_shift_differential_rate' => Setting::getValue(
                'payroll',
                'night_shift_differential_rate',
                10
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                 * REGULAR DAY
                 */
                Section::make('Regular Day')
                    ->description(
                        'Rates applied when an employee works on a regular working day.'
                    )
                    ->schema([
                        TextInput::make('regular_day_rate')
                            ->label('Regular Day Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('regular_day_overtime_rate')
                            ->label('Regular Day Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

                /*
                 * REST DAY
                 */
                Section::make('Rest Day')
                    ->description(
                        'Rates applied when an employee works on their scheduled rest day.'
                    )
                    ->schema([
                        TextInput::make('rest_day_rate')
                            ->label('Rest Day Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('rest_day_overtime_rate')
                            ->label('Rest Day Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

                /*
                 * SPECIAL NON-WORKING HOLIDAY
                 */
                Section::make('Special Non-Working Holiday')
                    ->description(
                        'Rates applied when an employee works on a special non-working holiday.'
                    )
                    ->schema([
                        TextInput::make('special_holiday_rate')
                            ->label('Special Holiday Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('special_holiday_overtime_rate')
                            ->label('Special Holiday Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('special_holiday_rest_day_rate')
                            ->label('Special Holiday + Rest Day Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('special_holiday_rest_day_overtime_rate')
                            ->label('Special Holiday + Rest Day Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

                /*
                 * REGULAR HOLIDAY
                 */
                Section::make('Regular Holiday')
                    ->description(
                        'Rates applied when an employee works on a regular holiday.'
                    )
                    ->schema([
                        TextInput::make('regular_holiday_rate')
                            ->label('Regular Holiday Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('regular_holiday_overtime_rate')
                            ->label('Regular Holiday Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('regular_holiday_rest_day_rate')
                            ->label('Regular Holiday + Rest Day Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),

                        TextInput::make('regular_holiday_rest_day_overtime_rate')
                            ->label('Regular Holiday + Rest Day Overtime Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

                /*
                 * NIGHT SHIFT DIFFERENTIAL
                 */
                Section::make('Night Shift Differential')
                    ->description(
                        'Additional percentage applied to qualifying night shift hours.'
                    )
                    ->schema([
                        TextInput::make('night_shift_differential_rate')
                            ->label('Night Shift Differential Rate')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                    ])
                    ->columns(2),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Regular Day
        Setting::setValue(
            'payroll',
            'regular_day_rate',
            $data['regular_day_rate'],
            'integer',
            'Regular working day rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_day_overtime_rate',
            $data['regular_day_overtime_rate'],
            'integer',
            'Overtime rate for a regular working day.'
        );

        // Rest Day
        Setting::setValue(
            'payroll',
            'rest_day_rate',
            $data['rest_day_rate'],
            'integer',
            'Rest day work rate.'
        );

        Setting::setValue(
            'payroll',
            'rest_day_overtime_rate',
            $data['rest_day_overtime_rate'],
            'integer',
            'Overtime rate for rest day work.'
        );

        // Special Non-Working Holiday
        Setting::setValue(
            'payroll',
            'special_holiday_rate',
            $data['special_holiday_rate'],
            'integer',
            'Special non-working holiday rate.'
        );

        Setting::setValue(
            'payroll',
            'special_holiday_overtime_rate',
            $data['special_holiday_overtime_rate'],
            'integer',
            'Overtime rate for special non-working holiday work.'
        );

        Setting::setValue(
            'payroll',
            'special_holiday_rest_day_rate',
            $data['special_holiday_rest_day_rate'],
            'integer',
            'Special non-working holiday falling on a rest day rate.'
        );

        Setting::setValue(
            'payroll',
            'special_holiday_rest_day_overtime_rate',
            $data['special_holiday_rest_day_overtime_rate'],
            'integer',
            'Overtime rate for special non-working holiday falling on a rest day.'
        );

        // Regular Holiday
        Setting::setValue(
            'payroll',
            'regular_holiday_rate',
            $data['regular_holiday_rate'],
            'integer',
            'Regular holiday rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_holiday_overtime_rate',
            $data['regular_holiday_overtime_rate'],
            'integer',
            'Overtime rate for regular holiday work.'
        );

        Setting::setValue(
            'payroll',
            'regular_holiday_rest_day_rate',
            $data['regular_holiday_rest_day_rate'],
            'integer',
            'Regular holiday falling on a rest day rate.'
        );

        Setting::setValue(
            'payroll',
            'regular_holiday_rest_day_overtime_rate',
            $data['regular_holiday_rest_day_overtime_rate'],
            'integer',
            'Overtime rate for regular holiday falling on a rest day.'
        );

        // Night Shift Differential
        Setting::setValue(
            'payroll',
            'night_shift_differential_rate',
            $data['night_shift_differential_rate'],
            'integer',
            'Night shift differential additional rate.'
        );

        Notification::make()
            ->title('Payroll settings saved')
            ->body('Payroll rates have been successfully saved.')
            ->success()
            ->send();
    }
}
