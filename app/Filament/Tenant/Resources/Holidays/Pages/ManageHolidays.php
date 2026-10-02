<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Holidays\Pages;

use App\Actions\Holidays\LoadPakistanHolidays;
use App\Enums\HolidayType;
use App\Filament\Tenant\Resources\Holidays\HolidayResource;
use App\Models\Branch;
use App\Models\Holiday;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('loadPk')->label(__('Load Pakistan holidays'))->icon(Heroicon::OutlinedCalendarDays)->color('gray')
                ->schema([
                    Select::make('year')->options(fn () => collect(array_keys(config('holidays_pk.islamic')))
                        ->merge([now()->year, now()->year + 1])->unique()->sort()->mapWithKeys(fn ($y) => [$y => $y])->all())
                        ->default(now()->year + 1)->required(),
                ])
                ->action(function (array $data, LoadPakistanHolidays $load) {
                    $n = $load((int) $data['year']);
                    Notification::make()->success()->title(__(':n holidays added', ['n' => $n]))
                        ->body(__('Islamic holidays are provisional — confirm them when the federal notification is out.'))->send();
                }),
            Action::make('range')->label(__('Add holiday range'))->icon(Heroicon::OutlinedCalendarDateRange)->color('gray')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)->placeholder('Eid ul Fitr'),
                    DatePicker::make('from')->required(),
                    DatePicker::make('to')->required()->afterOrEqual('from'),
                    Select::make('type')->options(HolidayType::class)->default(HolidayType::Company)->required(),
                    Select::make('branch_id')->label('Branch')->options(fn () => Branch::pluck('name', 'id'))->placeholder('All branches'),
                    Toggle::make('is_paid')->default(true),
                ])
                ->action(function (array $data) {
                    $days = CarbonPeriod::create($data['from'], $data['to']);
                    foreach ($days as $day) {
                        Holiday::query()->updateOrCreate(
                            ['date' => $day->format('Y-m-d'), 'branch_id' => $data['branch_id'] ?? null],
                            ['name' => $data['name'], 'type' => $data['type'], 'is_paid' => (bool) $data['is_paid']],
                        );
                    }
                    Notification::make()->success()->title(__(':n holiday days saved', ['n' => count($days)]))->send();
                }),
            CreateAction::make(),
        ];
    }
}
