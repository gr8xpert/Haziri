<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Periods\Pages;

use App\Actions\Periods\PeriodActions;
use App\Filament\Tenant\Resources\Periods\PeriodResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPeriods extends ListRecords
{
    protected static string $resource = PeriodResource::class;

    public function getSubheading(): string
    {
        return __('Close each month once it has been checked: Open (punches still coming in) → Review (HR fixes missing punches and '
            . 'approves requests) → Locked (final: nothing in that month can change, and payroll runs on it). '
            . 'Only the owner can unlock, with a reason. "View" shows the numbers of every employee for the month.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label(__('Add month'))->icon(Heroicon::OutlinedCalendarDays)
                ->schema([TextInput::make('month')->type('month')->required()->default(now()->subMonth()->format('Y-m'))])
                ->action(fn (array $data, PeriodActions $a) => $a->period((int) substr($data['month'], 0, 4), (int) substr($data['month'], 5, 2))),
        ];
    }
}
