<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Holidays\Pages;

use App\Actions\Holidays\LoadPakistanHolidays;
use App\Filament\Tenant\Resources\Holidays\HolidayResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
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
            CreateAction::make(),
        ];
    }
}
