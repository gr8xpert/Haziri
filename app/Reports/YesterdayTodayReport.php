<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/**
 * R19 — Yesterday & today: yesterday's last out next to today's first in, grouped by department.
 * Shows who stayed late last night (night / long shifts) and who is in this morning.
 */
final class YesterdayTodayReport extends Report
{
    public function title(): string
    {
        return __('Yesterday & today time');
    }

    public function filterSchema(): array
    {
        return [DatePicker::make('date')->required()->maxDate(now()), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['date' => CarbonImmutable::now($this->tz())->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return CarbonImmutable::parse($filters['date'])->format('l, d M Y');
    }

    public function columns(array $filters): array
    {
        return ['department' => 'Department', 'code' => 'Emp Id', 'name' => 'Employee name', 'designation' => 'Designation',
            'shift' => 'Shift', 'yesterday_out' => 'Y/day out', 'today_in' => 'Today in', 'status' => 'Status'];
    }

    public function rows(array $filters): array
    {
        $today = CarbonImmutable::parse($filters['date'])->format('Y-m-d');
        $yesterday = CarbonImmutable::parse($today)->subDay()->format('Y-m-d');
        $employees = $this->employees($filters)->with(['department', 'designation'])->get()
            ->sortBy(fn (Employee $e) => [$e->department === null ? 1 : 0, $e->department?->name, $e->code]);
        $days = AttendanceDay::query()->with('shift')->whereIn('employee_id', $employees->pluck('id'))
            ->whereIn('date', [$yesterday, $today])->get()
            ->keyBy(fn (AttendanceDay $d) => $d->employee_id . '|' . $d->date->format('Y-m-d'));
        $tz = $this->tz();

        $rows = [];
        foreach ($employees as $e) {
            if ($e->joined_on !== null && $e->joined_on->format('Y-m-d') > $today) {
                continue;
            }
            $y = $days->get("{$e->id}|{$yesterday}");
            $t = $days->get("{$e->id}|{$today}");
            $shift = $t?->shift ?? $y?->shift;
            $rows[] = [
                'department' => $e->department?->name ?? '—',
                'code' => $e->code,
                'name' => $e->fullName(),
                'designation' => $e->designation?->name,
                'shift' => $shift ? substr((string) $shift->start_time, 0, 5) . '–' . substr((string) $shift->end_time, 0, 5) : null,
                'yesterday_out' => $y?->last_out?->setTimezone($tz)->format('H:i'),
                'today_in' => $t?->first_in?->setTimezone($tz)->format('H:i'),
                'status' => $t?->status?->getLabel(),
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return ['In today' => count(array_filter(array_column($rows, 'today_in'))), 'Total' => count($rows)];
    }
}
