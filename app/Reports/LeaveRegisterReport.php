<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Filament\Forms\Components\Select;

/** R9 — Leave register: request-level list with approvals. */
final class LeaveRegisterReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Leave register');
    }

    public function filterSchema(): array
    {
        return [
            ...parent::filterSchema(),
            Select::make('leave_type_id')->label(__('Leave type'))->options(fn () => LeaveType::pluck('name', 'id'))->placeholder(__('All')),
        ];
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'type' => 'Type', 'from' => 'From', 'to' => 'To', 'part' => 'Part',
            'days' => 'Days', 'status' => 'Status', 'approvals' => 'Approvals', 'reason' => 'Reason'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->get()->keyBy('id');
        $names = User::pluck('name', 'id');

        return LeaveRequest::query()->with('leaveType')->whereIn('employee_id', $employees->keys())
            ->where('from_date', '<=', $filters['to'])->where('to_date', '>=', $filters['from'])
            ->when($filters['leave_type_id'] ?? null, fn ($q, $v) => $q->where('leave_type_id', $v))
            ->orderBy('from_date')->get()
            ->map(fn (LeaveRequest $l) => [
                'code' => $employees[$l->employee_id]->code,
                'name' => $employees[$l->employee_id]->fullName(),
                'type' => $l->leaveType?->code,
                'from' => $l->from_date->format('Y-m-d'),
                'to' => $l->to_date->format('Y-m-d'),
                'part' => $l->day_part->value,
                'days' => $l->days + count($l->sandwich_dates ?? []),
                'status' => $l->status->value,
                'approvals' => collect($l->approver_chain ?? [])->filter(fn ($s) => $s['decision'])
                    ->map(fn ($s) => "{$s['step']}: {$s['decision']} by " . ($names[$s['decided_by']] ?? '?'))->implode('; '),
                'reason' => $l->reason,
            ])->all();
    }
}
