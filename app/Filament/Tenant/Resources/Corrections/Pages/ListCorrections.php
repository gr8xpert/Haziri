<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Corrections\Pages;

use App\Actions\Corrections\SubmitCorrection;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Filament\Tenant\Resources\Corrections\CorrectionResource;
use App\Models\Employee;
use App\Models\Punch;
use App\Services\Attendance\MissingPunchSuggester;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListCorrections extends ListRecords
{
    protected static string $resource = CorrectionResource::class;

    protected static ?string $title = 'Corrections';

    protected function getHeaderActions(): array
    {
        $is = fn (Get $get, CorrectionType ...$types) => in_array(
            $get('type') instanceof CorrectionType ? $get('type') : CorrectionType::tryFrom((string) $get('type')), $types, true,
        );

        return [
            Action::make('request')->label(__('New request'))->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Select::make('employee_id')->label('Employee')->required()->searchable()->live()
                        ->options(fn () => $this->employeeOptions()),
                    DatePicker::make('date')->required()->live(),
                    Select::make('type')->options(CorrectionType::class)->required()->live()
                        ->helperText(fn (Get $get) => ($get('type') instanceof CorrectionType ? $get('type') : CorrectionType::tryFrom((string) $get('type')))?->hint()
                            ?? __('Pick what needs fixing; a short explanation appears here.'))
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            // Missing punch-out? Offer the employee's usual out time (spec §12.4).
                            if ((string) ($state instanceof CorrectionType ? $state->value : $state) === 'add_punch' && $get('employee_id')) {
                                $set('time', app(MissingPunchSuggester::class)->suggestOut(Employee::find($get('employee_id')), $get('date')));
                                $set('direction', 'out');
                            }
                        }),
                    TimePicker::make('time')->seconds(false)->visible(fn (Get $get) => $is($get, CorrectionType::AddPunch))
                        ->helperText('Pre-filled with the employee\'s usual out time when available'),
                    Select::make('direction')->options(['in' => 'In', 'out' => 'Out'])->visible(fn (Get $get) => $is($get, CorrectionType::AddPunch)),
                    Select::make('punch_id')->label('Punch to remove')->visible(fn (Get $get) => $is($get, CorrectionType::RemovePunch))
                        ->helperText(fn (Get $get) => $get('employee_id') && $get('date') ? null : __('Choose the employee and date first.'))
                        ->options(fn (Get $get) => $get('employee_id') && $get('date')
                            ? Punch::where('employee_id', $get('employee_id'))->whereDate('local_date', $get('date'))->get()
                                ->mapWithKeys(fn (Punch $p) => [$p->id => $p->punched_at->setTimezone(tenant('timezone'))->format('H:i:s') . " ({$p->source->getLabel()})"])
                            : []),
                    Select::make('status')->options(AttendanceStatus::class)->visible(fn (Get $get) => $is($get, CorrectionType::ChangeStatus)),
                    Textarea::make('reason')->required(),
                ])
                ->action(function (array $data, SubmitCorrection $submit) {
                    $type = $data['type'] instanceof CorrectionType ? $data['type'] : CorrectionType::from($data['type']);
                    $payload = match ($type) {
                        CorrectionType::AddPunch => ['time' => substr((string) $data['time'], 0, 5), 'direction' => $data['direction'] ?? 'out'],
                        CorrectionType::RemovePunch => ['punch_id' => (int) $data['punch_id']],
                        CorrectionType::ChangeStatus => ['status' => $data['status'] instanceof AttendanceStatus ? $data['status']->value : $data['status']],
                        default => [],
                    };
                    try {
                        $submit(Employee::findOrFail($data['employee_id']), substr((string) $data['date'], 0, 10), $type, $payload,
                            (string) $data['reason'], auth()->user());
                        Notification::make()->success()->title(__('Request submitted'))->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                    }
                }),
        ];
    }

    /** @return array<int, string> */
    private function employeeOptions(): array
    {
        $user = auth()->user();
        $query = Employee::query()->orderBy('code');
        if (! $user->can('corrections.approve_final')) {
            $query->where(fn ($q) => $q->whereKey($user->employee_id ?? 0)->orWhere('manager_employee_id', $user->employee_id ?? 0));
        }

        return $query->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])->all();
    }
}
