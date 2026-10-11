<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
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
                'from' => self::day($l->from_date),
                'to' => self::day($l->to_date),
                'part' => $l->day_part->getLabel(),
                'days' => $l->days + count($l->sandwich_dates ?? []),
                'status' => $l->status->getLabel(),
                'approvals' => collect($l->approver_chain ?? [])->filter(fn ($s) => $s['decision'])
                    ->map(fn ($s) => __(':step: :decision by :name', [
                        'step' => ApprovalChain::stepLabel((string) $s['step']),
                        'decision' => $s['decision'] === 'approved' ? __('approved') : __('rejected'),
                        'name' => $names[$s['decided_by']] ?? '?',
                    ]))->implode('; '),
                'reason' => $l->reason,
            ])->all();
    }
}
