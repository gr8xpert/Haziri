<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Models\AttendanceDay;
use App\Models\LeaveRequest;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\TextInput;

/** R2 — Monthly attendance sheet: employees × days with status codes, totals and deduction days. */
final class MonthlySheetReport extends Report
{
    public function title(): string
    {
        return __('Monthly attendance sheet');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('month')->type('month')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['month' => CarbonImmutable::now($this->tz())->format('Y-m')];
    }

    public function subtitle(array $filters): string
    {
        return CarbonImmutable::parse($filters['month'] . '-01')->format('F Y');
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        $cols = ['code' => 'Code', 'name' => 'Name'];
        foreach ($this->period($filters) as $d) {
            $cols['d' . $d->format('j')] = $d->format('j');
        }

        return $cols + ['P' => 'P', 'A' => 'A', 'HD' => 'HD', 'LV' => 'LV', 'UL' => 'UL', 'L' => 'Late', 'ded' => 'Ded.', 'pay' => 'Payable'];
    }

    public function rows(array $filters): array
    {
        $period = $this->period($filters);
        $from = $period->getStartDate()->format('Y-m-d');
        $to = $period->getEndDate()->format('Y-m-d');
        $employees = $this->employees($filters)->get();

        $days = AttendanceDay::query()->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$from, $to])->get()->groupBy('employee_id');
        $leaveCodes = LeaveRequest::query()->with('leaveType')->whereIn('id', $days->flatten()->pluck('leave_request_id')->filter()->unique())
            ->get()->mapWithKeys(fn (LeaveRequest $l) => [$l->id => $l->leaveType?->code ?? 'LV'])->all();
        $settings = app(TenantSettings::class)->all();
        $calculator = new PeriodCalculator;

        $rows = [];
        foreach ($employees as $e) {
            $own = $days->get($e->id, collect())->keyBy(fn (AttendanceDay $d) => $d->date->format('Y-m-d'));
            $row = ['code' => $e->code, 'name' => $e->fullName()];
            foreach ($period as $d) {
                $row['d' . $d->format('j')] = StatusCodes::for($own->get($d->format('Y-m-d')), $leaveCodes);
            }
            $summary = $calculator->summarise(
                $own->map(fn (AttendanceDay $d) => $d->toResult())->values(),
                PeriodRules::fromSettings($settings, $e->late_deduction_exempt, $e->employment_type === EmploymentType::DailyWage),
            );
            $rows[] = $row + [
                'P' => $summary->presentDays + $summary->officialDutyDays + $summary->wfhDays,
                'A' => $summary->absentDays,
                'HD' => $summary->halfDays,
                'LV' => $summary->paidLeaveDays,
                'UL' => $summary->unpaidLeaveDays,
                'L' => $summary->lateCount,
                'ded' => $summary->deductionDays,
                'pay' => $summary->payableDays,
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return StatusCodes::legend();
    }

    private function period(array $filters): CarbonPeriod
    {
        $start = CarbonImmutable::parse(($filters['month'] ?? now()->format('Y-m')) . '-01');

        return CarbonPeriod::create($start, $start->endOfMonth()->startOfDay());
    }
}
