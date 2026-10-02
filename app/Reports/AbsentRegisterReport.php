<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/**
 * R5 — Absent register with consecutive-absence streak. Weekly offs / holidays between absences
 * don't break a streak (Friday absent + Monday absent = streak 2).
 */
final class AbsentRegisterReport extends Report
{
    public function title(): string
    {
        return __('Absent register');
    }

    public function filterSchema(): array
    {
        return [DatePicker::make('from')->required(), DatePicker::make('to')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        $now = CarbonImmutable::now($this->tz());

        return ['from' => $this->cycleRange($this->currentCycleMonth())[0]->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return "{$filters['from']} → {$filters['to']}";
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'code' => 'Code', 'name' => 'Name', 'department' => 'Department', 'streak' => 'Consecutive'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->with('department')->get()->keyBy('id');
        $days = AttendanceDay::query()
            ->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->whereIn('status', [AttendanceStatus::Absent, AttendanceStatus::WeeklyOff, AttendanceStatus::Holiday,
                AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid,
                AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh, AttendanceStatus::HalfDayLeave])
            ->orderBy('employee_id')->orderBy('date')
            ->get()->groupBy('employee_id');

        $rows = [];
        foreach ($days as $employeeId => $list) {
            $e = $employees[$employeeId];
            $streak = 0;
            foreach ($list as $d) {
                if (in_array($d->status, [AttendanceStatus::WeeklyOff, AttendanceStatus::Holiday], true)) {
                    continue; // non-working days neither extend nor break a streak
                }
                if ($d->status !== AttendanceStatus::Absent) {
                    $streak = 0;

                    continue;
                }
                $streak++;
                $rows[] = [
                    'date' => $d->date->format('Y-m-d'), 'code' => $e->code, 'name' => $e->fullName(),
                    'department' => $e->department?->name, 'streak' => $streak,
                ];
            }
        }
        usort($rows, fn ($a, $b) => [$a['date'], $a['code']] <=> [$b['date'], $b['code']]);

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Absent days' => count($rows), 'Longest streak' => $rows ? max(array_column($rows, 'streak')) : 0];
    }
}
