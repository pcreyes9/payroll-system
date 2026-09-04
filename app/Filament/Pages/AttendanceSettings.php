<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class AttendanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Attendance Settings';

    protected static ?string $navigationLabel = 'Attendance Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'filament.pages.attendance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([

            /*
             * =================================================
             * WORK SCHEDULE
             * =================================================
             */

            'official_time_in' => Setting::getValue(
                'attendance',
                'official_time_in',
                '09:00'
            ),

            'official_time_out' => Setting::getValue(
                'attendance',
                'official_time_out',
                '17:00'
            ),

            'grace_period_minutes' => Setting::getValue(
                'attendance',
                'grace_period_minutes',
                15
            ),

            'workdays' => Setting::getValue(
                'attendance',
                'workdays',
                [1, 2, 3, 4, 5]
            ),


            /*
             * =================================================
             * OVERTIME
             * =================================================
             */

            'overtime_enabled' => Setting::getValue(
                'attendance',
                'overtime_enabled',
                true
            ),

            'minimum_overtime_minutes' => Setting::getValue(
                'attendance',
                'minimum_overtime_minutes',
                0
            ),


            /*
             * =================================================
             * NIGHT SHIFT DIFFERENTIAL
             * =================================================
             *
             * Only detection settings belong here.
             *
             * The NSD RATE is managed under Payroll Settings.
             * =================================================
             */

            'night_shift_differential_enabled' => Setting::getValue(
                'attendance',
                'night_shift_differential_enabled',
                true
            ),

            'night_shift_differential_start' => Setting::getValue(
                'attendance',
                'night_shift_differential_start',
                '22:00'
            ),

            'night_shift_differential_end' => Setting::getValue(
                'attendance',
                'night_shift_differential_end',
                '06:00'
            ),


            /*
             * =================================================
             * BREAK
             * =================================================
             */

            'break_enabled' => Setting::getValue(
                'attendance',
                'break_enabled',
                false
            ),

            'break_start' => Setting::getValue(
                'attendance',
                'break_start',
                null
            ),

            'break_end' => Setting::getValue(
                'attendance',
                'break_end',
                null
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                 * =================================================
                 * WORK SCHEDULE
                 * =================================================
                 */

                Section::make('Work Schedule')
                    ->description(
                        'Configure the regular employee working schedule.'
                    )
                    ->schema([

                        TimePicker::make('official_time_in')
                            ->label('Official Time In')
                            ->seconds(false)
                            ->required(),

                        TimePicker::make('official_time_out')
                            ->label('Official Time Out')
                            ->seconds(false)
                            ->required(),

                        TextInput::make('grace_period_minutes')
                            ->label('Grace Period')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('minutes')
                            ->required(),

                        CheckboxList::make('workdays')
                            ->label('Working Days')
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday',
                            ])
                            ->columns(4)
                            ->required()
                            ->columnSpanFull(),

                    ])
                    ->columns(3),


                /*
                 * =================================================
                 * OVERTIME & BREAK
                 * =================================================
                 */

                Section::make('Overtime & Break')
                    ->description(
                        'Configure overtime recognition and the regular employee break period.'
                    )
                    ->schema([

                        /*
                         * -----------------------------------------
                         * OVERTIME
                         * -----------------------------------------
                         */

                        Section::make('Overtime')
                            ->schema([

                                Toggle::make('overtime_enabled')
                                    ->label('Enable Overtime')
                                    ->live(),

                                TextInput::make('minimum_overtime_minutes')
                                    ->label('Minimum Overtime')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('minutes')
                                    ->required()
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('overtime_enabled') === true
                                    ),

                            ])
                            ->columns(2),


                        /*
                         * -----------------------------------------
                         * NIGHT SHIFT DIFFERENTIAL
                         * -----------------------------------------
                         */

                        Section::make('Night Shift Differential')
                            ->description(
                                'Configure when night shift hours are detected. The NSD rate is configured under Payroll Settings.'
                            )
                            ->schema([

                                Toggle::make('night_shift_differential_enabled')
                                    ->label('Enable Night Shift Differential')
                                    ->live()
                                    ->columnSpanFull(),

                                TimePicker::make('night_shift_differential_start')
                                    ->label('NSD Start')
                                    ->seconds(false)
                                    ->default('22:00')
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('night_shift_differential_enabled') === true
                                    )
                                    ->required(),

                                TimePicker::make('night_shift_differential_end')
                                    ->label('NSD End')
                                    ->seconds(false)
                                    ->default('06:00')
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('night_shift_differential_enabled') === true
                                    )
                                    ->required(),

                            ])
                            ->columns(2),


                        /*
                         * -----------------------------------------
                         * BREAK
                         * -----------------------------------------
                         */

                        Section::make('Break')
                            ->schema([

                                Toggle::make('break_enabled')
                                    ->label('Enable Break')
                                    ->live()
                                    ->columnSpanFull(),

                                TimePicker::make('break_start')
                                    ->label('Break Start')
                                    ->seconds(false)
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('break_enabled') === true
                                    ),

                                TimePicker::make('break_end')
                                    ->label('Break End')
                                    ->seconds(false)
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('break_enabled') === true
                                    ),

                            ])
                            ->columns(2),

                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();


        /*
         * =================================================
         * WORK SCHEDULE
         * =================================================
         */

        Setting::setValue(
            'attendance',
            'official_time_in',
            $data['official_time_in'],
            'time',
            'Official employee time in.'
        );

        Setting::setValue(
            'attendance',
            'official_time_out',
            $data['official_time_out'],
            'time',
            'Official employee time out.'
        );

        Setting::setValue(
            'attendance',
            'grace_period_minutes',
            $data['grace_period_minutes'],
            'integer',
            'Allowed grace period before an employee is considered late.'
        );

        Setting::setValue(
            'attendance',
            'workdays',
            $data['workdays'],
            'json',
            'Regular employee working days.'
        );


        /*
         * =================================================
         * OVERTIME
         * =================================================
         */

        Setting::setValue(
            'attendance',
            'overtime_enabled',
            $data['overtime_enabled'],
            'boolean',
            'Enable overtime calculation.'
        );

        Setting::setValue(
            'attendance',
            'minimum_overtime_minutes',
            $data['minimum_overtime_minutes'],
            'integer',
            'Minimum overtime minutes required before overtime is counted.'
        );


        /*
         * =================================================
         * NIGHT SHIFT DIFFERENTIAL
         * =================================================
         *
         * Detection only.
         *
         * Rate is stored under:
         * payroll.night_shift_differential_rate
         * =================================================
         */

        Setting::setValue(
            'attendance',
            'night_shift_differential_enabled',
            $data['night_shift_differential_enabled'],
            'boolean',
            'Enable night shift differential detection.'
        );

        Setting::setValue(
            'attendance',
            'night_shift_differential_start',
            $data['night_shift_differential_start'],
            'time',
            'Start of the night shift differential period.'
        );

        Setting::setValue(
            'attendance',
            'night_shift_differential_end',
            $data['night_shift_differential_end'],
            'time',
            'End of the night shift differential period.'
        );


        /*
         * =================================================
         * BREAK
         * =================================================
         */

        Setting::setValue(
            'attendance',
            'break_enabled',
            $data['break_enabled'],
            'boolean',
            'Enable the configured employee break period.'
        );

        Setting::setValue(
            'attendance',
            'break_start',
            $data['break_start'] ?? null,
            'time',
            'Start of the configured break period.'
        );

        Setting::setValue(
            'attendance',
            'break_end',
            $data['break_end'] ?? null,
            'time',
            'End of the configured break period.'
        );


        Notification::make()
            ->title('Attendance settings saved')
            ->success()
            ->send();
    }
}
