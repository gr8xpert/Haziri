<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\EmploymentType;
use App\Enums\PeriodStatus;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\TextInput;

/**
 * R12 — Payroll input sheet: payable days, deduction breakdown, OT hours, unpaid leave.
 * Uses the locked snapshot when the month is locked, so the numbers match what was approved.
 */
final class PayrollInputReport extends Report
{
    public function title(): string
    {
        return __('Payroll input');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('month')->type('month')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['month' => CarbonImmutable::now($this->tz())->subMonth()->format('Y-m')];
    }

    public function subtitle(array $filters): string
    {
        [$y, $m] = array_map('intval', explode('-', $filters['month']));
        $locked = AttendancePeriod::where(['year' => $y, 'month' => $m, 'status' => PeriodStatus::Locked])->exists();

        return CarbonImmutable::create($y, $m, 1)->format('F Y') . ($locked ? ' · ' . __('locked') : ' · ' . __('NOT locked — provisional'));
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'calendar' => 'Calendar days', 'present' => 'Present', 'absent' => 'Absent',
            'half' => 'Half days', 'unpaid' => 'Unpaid leave', 'lates' => 'Lates', 'late_rollup' => 'Late deduction',
            'deduction' => 'Deduction days', 'payable' => 'Payable days', 'ot_hours' => 'OT hours'];
    }

    public function rows(array $filters): array
    {
        [$y, $m] = array_map('intval', explode('-', $filters['month']));
        $employees = $this->employees($filters)->get();
        $snapshot = AttendancePeriod::where(['year' => $y, 'month' => $m, 'status' => PeriodStatus::Locked])->value('summary');

        $start = CarbonImmutable::create($y, $m, 1);
        $days = $snapshot ? collect() : AttendanceDay::query()->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')])->get()->groupBy('employee_id');
        $settings = app(TenantSettings::class)->all();

        $rows = [];
        foreach ($employees as $e) {
            $s = $snapshot['employees'][$e->id] ?? null;
            if ($s === null && ! $snapshot) {
                $s = (new PeriodCalculator)->summarise(
                    $days->get($e->id, collect())->map(fn (AttendanceDay $d) => $d->toResult()),
                    PeriodRules::fromSettings($settings, $e->late_deduction_exempt, $e->employment_type === EmploymentType::DailyWage),
                )->toArray();
            }
            if ($s === null) {
                continue;
            }
            $rows[] = [
                'code' => $e->code, 'name' => $e->fullName(), 'calendar' => $s['calendarDays'], 'present' => $s['presentDays'],
                'absent' => $s['absentDays'], 'half' => $s['halfDays'], 'unpaid' => $s['unpaidLeaveDays'], 'lates' => $s['lateCount'],
                'late_rollup' => $s['lateRollupDays'], 'deduction' => $s['deductionDays'], 'payable' => $s['payableDays'],
                'ot_hours' => round($s['overtimeMinutes'] / 60, 1),
            ];
        }

        return $rows;
    }
}
