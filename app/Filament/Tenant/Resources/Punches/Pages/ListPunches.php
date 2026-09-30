<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Punches\Pages;

use App\Actions\Punches\CreateManualPunches;
use App\Filament\Tenant\Pages\DailySheet;
use App\Filament\Tenant\Resources\Punches\PunchResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPunches extends ListRecords
{
    protected static string $resource = PunchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dailySheet')->label(__('Daily sheet'))->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->url(DailySheet::getUrl()),
            Action::make('manual')->label(__('Manual entry'))->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn () => auth()->user()->can('attendance.manual_entry'))
                ->schema([
                    Select::make('employee_id')->label('Employee')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $s) => Employee::query()
                            ->where(fn ($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('code', $s))
                            ->limit(30)->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]))
                        ->getOptionLabelUsing(fn ($value) => Employee::find($value)?->fullName()),
                    DatePicker::make('date')->required()->default(now())->maxDate(now()),
                    TimePicker::make('in_time')->seconds(false),
                    TimePicker::make('out_time')->seconds(false),
                    TextInput::make('reason')->required()->maxLength(255)->placeholder('Machine down / forgot to punch'),
                    TextInput::make('note')->maxLength(255),
                ])
                ->action(function (array $data, CreateManualPunches $create) {
                    $create(
                        Employee::findOrFail($data['employee_id']),
                        substr((string) $data['date'], 0, 10),
                        $data['in_time'] ? substr((string) $data['in_time'], 0, 5) : null,
                        $data['out_time'] ? substr((string) $data['out_time'], 0, 5) : null,
                        (string) $data['reason'],
                        auth()->user(),
                        $data['note'] ?? null,
                    );
                    Notification::make()->success()->title(__('Punches saved'))->send();
                }),
        ];
    }
}
