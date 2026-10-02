<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveType;
use App\Services\Leave\LeaveMonthSummary;
use App\Services\Settings\TenantSettings;
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
        [$start, $end] = $this->cycleRange($filters['month']);
        $to = $end->format('Y-m-d');
        $employees = $this->employees($filters)->with('designation')->get()
            ->filter(fn ($e) => $e->joined_on === null || $e->joined_on->format('Y-m-d') <= $to);
        $summary = app(LeaveMonthSummary::class)->forEmployees($employees, $type, $start, $end);

        $rows = [];
        foreach ($employees as $e) {
            $m = $summary[$e->id];
            $rows[] = [
                'payroll_id' => $e->payroll_id,
                'code' => $e->code,
                'name' => $e->fullName(),
                'designation' => $e->designation?->name,
                'doj' => $e->joined_on?->format('d-M-Y'),
                'previous' => $m['previous'],
                'allotted' => $m['allotted'],
                'total' => $m['total'],
                'availed' => $m['availed'] ?: null,
                'adjusted' => $m['adjusted'] ?: null,
                'deducted' => ($m['availed'] + $m['adjusted']) ?: null,
                'credit' => $m['credit'] ?: null,
                'lwp' => $m['lwp'] ?: null,
                'closing' => $m['closing'],
            ];
        }

        return $rows;
    }
}
