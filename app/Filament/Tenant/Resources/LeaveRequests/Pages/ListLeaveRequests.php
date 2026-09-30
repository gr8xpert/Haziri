<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveRequests\Pages;

use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\LeaveDayPart;
use App\Filament\Tenant\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveType;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')->label(__('New leave request'))->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    Select::make('employee_id')->label('Employee')->required()->searchable()
                        ->options(fn () => $this->employeeOptions())
                        ->default(fn () => auth()->user()->can('leave.request_on_behalf') ? null : auth()->user()->employee_id),
                    Select::make('leave_type_id')->label('Type')->required()
                        ->options(fn () => LeaveType::where('is_active', true)->pluck('name', 'id')),
                    Select::make('day_part')->options(LeaveDayPart::class)->default(LeaveDayPart::Full)->required()->live(),
                    DatePicker::make('from_date')->required(),
                    DatePicker::make('to_date')->required()->afterOrEqual('from_date'),
                    TimePicker::make('short_leave_from')->seconds(false)->label('Short leave from (optional)'),
                    TimePicker::make('short_leave_to')->seconds(false)->label('Short leave to'),
                    Textarea::make('reason'),
                    FileUpload::make('attachment_path')->label('Attachment')->disk('local')->directory('leave-attachments')->maxSize(5120),
                ])
                ->action(function (array $data, SubmitLeaveRequest $submit) {
                    try {
                        $submit(
                            Employee::findOrFail($data['employee_id']),
                            LeaveType::findOrFail($data['leave_type_id']),
                            substr((string) $data['from_date'], 0, 10),
                            substr((string) $data['to_date'], 0, 10),
                            auth()->user(),
                            $data['day_part'] instanceof LeaveDayPart ? $data['day_part'] : LeaveDayPart::from($data['day_part']),
                            $data['reason'] ?? null,
                            $data['attachment_path'] ?? null,
                            $data['short_leave_from'] ? substr((string) $data['short_leave_from'], 0, 5) : null,
                            $data['short_leave_to'] ? substr((string) $data['short_leave_to'], 0, 5) : null,
                        );
                        Notification::make()->success()->title(__('Leave request submitted'))->send();
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
        if (! $user->can('leave.request_on_behalf')) {
            $query->where(fn ($q) => $q->whereKey($user->employee_id ?? 0)->orWhere('manager_employee_id', $user->employee_id ?? 0));
        }

        return $query->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])->all();
    }
}
