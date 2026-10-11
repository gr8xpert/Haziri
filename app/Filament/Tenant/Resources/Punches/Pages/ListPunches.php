<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Punches\Pages;

use App\Actions\Punches\CreateManualPunches;
use App\Actions\Punches\CreateManualPunchRange;
use App\Filament\Tenant\Pages\DailySheet;
use App\Filament\Tenant\Resources\Punches\PunchResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListPunches extends ListRecords
{
    protected static string $resource = PunchResource::class;

    protected function getHeaderActions(): array
    {
        $employee = fn () => Select::make('employee_id')->label('Employee')->required()->searchable()
            ->getSearchResultsUsing(fn (string $s) => Employee::query()
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('code', $s))
                ->limit(30)->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]))
            ->getOptionLabelUsing(fn ($value) => Employee::find($value)?->fullName());

        return [
            ActionGroup::make([
                Action::make('manualRange')->label(__('Manual attendance — date range'))->icon(Heroicon::OutlinedCalendarDateRange)
                    ->visible(fn () => auth()->user()->can('attendance.manual_edit'))
                    ->modalDescription(__('Same in/out time on every day. Days that already have punches are skipped.'))
                    ->schema([
                        $employee(),
                        DatePicker::make('from')->required()->maxDate(now()),
                        DatePicker::make('to')->required()->afterOrEqual('from')->maxDate(now()),
                        TimePicker::make('in_time')->seconds(false),
                        TimePicker::make('out_time')->seconds(false),
                        TextInput::make('reason')->required()->maxLength(255)->placeholder('Machine down / site visit'),
                        Toggle::make('working_days_only')->label('Skip weekly offs, holidays and leave')->default(true),
                    ])
                    ->action(function (array $data, CreateManualPunchRange $range) {
                        $result = $range(
                            Employee::findOrFail($data['employee_id']),
                            substr((string) $data['from'], 0, 10),
                            substr((string) $data['to'], 0, 10),
                            $data['in_time'] ? substr((string) $data['in_time'], 0, 5) : null,
                            $data['out_time'] ? substr((string) $data['out_time'], 0, 5) : null,
                            (string) $data['reason'],
                            auth()->user(),
                            (bool) $data['working_days_only'],
                        );
                        $skipped = collect($result['skipped'])->map(fn ($why, $date) => "{$date}: {$why}")->implode("\n");
                        Notification::make()->success()->title(__(':n days saved', ['n' => count($result['saved'])]))
                            ->body($skipped !== '' ? __('Skipped:') . "\n" . $skipped : null)->persistent($skipped !== '')->send();
                    }),
                Action::make('removeManualRange')->label(__('Remove manual punches — date range'))->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn () => auth()->user()->can('attendance.manual_edit'))
                    ->requiresConfirmation()
                    ->schema([
                        $employee(),
                        DatePicker::make('from')->required(),
                        DatePicker::make('to')->required()->afterOrEqual('from'),
                        TextInput::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (array $data, CreateManualPunchRange $range) {
                        $n = $range->remove(Employee::findOrFail($data['employee_id']), substr((string) $data['from'], 0, 10),
                            substr((string) $data['to'], 0, 10), (string) $data['reason'], auth()->user());
                        Notification::make()->success()->title(__(':n manual punches removed', ['n' => $n]))->send();
                    }),
            ])->label(__('Bulk manual'))->icon(Heroicon::OutlinedSquare2Stack)->button()->color('gray'),
            Action::make('dailySheet')->label(__('Manual Entry'))->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->url(DailySheet::getUrl()),
            Action::make('manual')->label(__('Add one punch'))->icon(Heroicon::OutlinedPencilSquare)
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
                ->action(function (array $data, CreateManualPunches $create, Action $action) {
                    try {
                        $create(
                            Employee::findOrFail($data['employee_id']),
                            substr((string) $data['date'], 0, 10),
                            $data['in_time'] ? substr((string) $data['in_time'], 0, 5) : null,
                            $data['out_time'] ? substr((string) $data['out_time'], 0, 5) : null,
                            (string) $data['reason'],
                            auth()->user(),
                            $data['note'] ?? null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                        $action->halt();
                    }
                    Notification::make()->success()->title(__('Punches saved'))->send();
                }),
        ];
    }
}
