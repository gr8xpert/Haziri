<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\RequestStatus;
use App\Models\AttendanceDay;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * R21 — Monthly leave summary for one leave type: previous balance, allotted this month, availed, late / half-day
 * adjustments, credits, leave without pay and the closing balance. Leave is counted in the month it starts;
 * adjustments by their effective date.
 */
final class MonthlyLeaveSummaryReport extends Report
{
    public function title(): string
    {
        return __('Monthly leave summary');
    }

    public function filterSchema(): array
    {
        return [
            TextInput::make('month')->type('month')->required(),
            Select::make('leave_type_id')->label(__('Leave type'))->required()
                ->options(fn () => LeaveType::query()->where('is_active', true)->pluck('name', 'id')),
            ...$this->orgFilters(),
        ];
    }

    public function defaults(): array
    {
        $code = app(TenantSettings::class)->get('late_adjustment_leave_type');
        $type = ($code ? LeaveType::where('code', $code)->first() : null)
            ?? LeaveType::query()->where('is_active', true)->where('is_paid', true)->orderBy('id')->first();

        return ['month' => $this->currentCycleMonth(), 'leave_type_id' => $type?->id];
    }

    public function ready(array $filters): bool
    {
        return ! empty($filters['month']) && ! empty($filters['leave_type_id']);
    }

    public function subtitle(array $filters): string
    {
        return trim((LeaveType::find($filters['leave_type_id'] ?? 0)?->name ?? '') . ' · ' . $this->cycleLabel($filters['month']), ' ·');
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return [
            'payroll_id' => 'Payroll ID', 'code' => 'Emp #', 'name' => 'Name', 'designation' => 'Designation', 'doj' => 'D.O.J',
            'previous' => 'Prev month bal', 'allotted' => 'Allotted', 'total' => 'Total', 'availed' => 'Availed',
            'adjusted' => 'Adj (late / half day)', 'deducted' => 'Total deducted', 'credit' => 'Credit leave', 'lwp' => 'LWP',
            'closing' => 'Cur month bal',
        ];
    }

    public function rows(array $filters): array
    {
        $type = LeaveType::find($filters['leave_type_id'] ?? 0);
        if ($type === null) {
            return [];
        }
        $leaveYear = app(LeaveYear::class);
        $balances = app(LeaveBalanceService::class);
        [$start, $end] = $this->cycleRange($filters['month']);
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $year = $leaveYear->yearFor($to);
        [$yearStart] = $leaveYear->range($year);
        $employees = $this->employees($filters)->with('designation')->get();
        $ids = $employees->pluck('id');

        $openings = LeaveBalance::query()->where(['leave_type_id' => $type->id, 'year' => $year])->whereIn('employee_id', $ids)
            ->pluck('opening', 'employee_id');
        $requests = LeaveRequest::query()->where('leave_type_id', $type->id)->where('status', RequestStatus::Approved)
            ->whereIn('employee_id', $ids)->whereBetween('from_date', [$yearStart->format('Y-m-d'), $to])->get()->groupBy('employee_id');
        $adjustments = LeaveAdjustment::query()->where(['leave_type_id' => $type->id, 'year' => $year])->whereIn('employee_id', $ids)
            ->get()->groupBy('employee_id');
        $lwp = AttendanceDay::query()->whereIn('employee_id', $ids)->whereBetween('date', [$from, $to])
            ->where('status', AttendanceStatus::LeaveUnpaid)->selectRaw('employee_id, count(*) as n')->groupBy('employee_id')->pluck('n', 'employee_id');
        $periodHasYearStart = $yearStart->betweenIncluded($start, $end);

        $rows = [];
        foreach ($employees as $e) {
            if ($e->joined_on !== null && $e->joined_on->format('Y-m-d') > $to) {
                continue;
            }
            if ($type->accrual === 'monthly') {
                $before = $balances->accrued($e, $type, $year, $start->subDay());
                $allotted = $balances->accrued($e, $type, $year, $end) - $before;
            } else {
                $full = $balances->accrued($e, $type, $year);
                $before = $periodHasYearStart ? 0.0 : $full;
                $allotted = $periodHasYearStart ? $full : 0.0;
            }
            $own = $requests->get($e->id, collect());
            $daysOf = fn (LeaveRequest $r) => (float) $r->days + count($r->sandwich_dates ?? []);
            $usedBefore = $own->filter(fn (LeaveRequest $r) => $r->from_date->format('Y-m-d') < $from)->sum($daysOf);
            $availed = $own->filter(fn (LeaveRequest $r) => $r->from_date->format('Y-m-d') >= $from)->sum($daysOf);

            $adjBefore = 0.0;
            $deducted = 0.0;
            $credit = 0.0;
            foreach ($adjustments->get($e->id, collect()) as $a) {
                $on = ($a->effective_on ?? CarbonImmutable::parse($a->created_at))->format('Y-m-d');
                if ($on < $from) {
                    $adjBefore += $a->days;
                } elseif ($on <= $to && $a->days < 0) {
                    $deducted -= $a->days;
                } elseif ($on <= $to) {
                    $credit += $a->days;
                }
            }

            $previous = (float) ($openings[$e->id] ?? 0) + $before - $usedBefore + $adjBefore;
            $rows[] = [
                'payroll_id' => $e->payroll_id,
                'code' => $e->code,
                'name' => $e->fullName(),
                'designation' => $e->designation?->name,
                'doj' => $e->joined_on?->format('d-M-Y'),
                'previous' => round($previous, 1),
                'allotted' => round($allotted, 1),
                'total' => round($previous + $allotted, 1),
                'availed' => $availed ?: null,
                'adjusted' => $deducted ?: null,
                'deducted' => ($availed + $deducted) ?: null,
                'credit' => $credit ?: null,
                'lwp' => (int) ($lwp[$e->id] ?? 0) ?: null,
                'closing' => round($previous + $allotted - $availed - $deducted + $credit, 1),
            ];
        }

        return $rows;
    }
}
