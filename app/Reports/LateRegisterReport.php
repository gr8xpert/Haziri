<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

/** R3 — Late register (and R4 early-out register when constructed with $early = true). */
class LateRegisterReport extends Report
{
    public function __construct(protected bool $early = false) {}

    public function title(): string
    {
        return __('Late & early-out');
    }

    /** Late arrivals or early leaving: a choice on the report (the old Early-out register is this with "early"). */
    private function isEarly(array $filters): bool
    {
        return $this->early || ($filters['kind'] ?? 'late') === 'early';
    }

    public function filterSchema(): array
    {
        return [
            Select::make('kind')->label(__('Show'))->options(['late' => __('Late arrivals'), 'early' => __('Early leaving')])->default('late')->required(),
            DatePicker::make('from')->required(), DatePicker::make('to')->required(), ...$this->orgFilters(),
        ];
    }

    public function defaults(): array
    {
        $now = CarbonImmutable::now($this->tz());

        return ['kind' => $this->early ? 'early' : 'late', 'from' => $this->cycleRange($this->currentCycleMonth())[0]->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return ($this->isEarly($filters) ? __('Early leaving') : __('Late arrivals')) . " · {$filters['from']} → {$filters['to']}";
    }

    public function columns(array $filters): array
    {
        return $this->isEarly($filters)
            ? ['date' => 'Date', 'code' => 'Code', 'name' => 'Name', 'shift_end' => 'Shift end', 'out' => 'Out', 'minutes' => 'Early (min)', 'count' => 'Running count']
            : ['date' => 'Date', 'code' => 'Code', 'name' => 'Name', 'shift_start' => 'Shift start', 'in' => 'In', 'minutes' => 'Late (min)', 'count' => 'Running count'];
    }

    public function rows(array $filters): array
    {
        $early = $this->isEarly($filters);
        $flag = $early ? 'early_out' : 'late';
        $employees = $this->employees($filters)->get()->keyBy('id');
        $days = AttendanceDay::query()->with('shift')
            ->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->whereJsonContains('flags', $flag)
            ->orderBy('employee_id')->orderBy('date')
            ->get();
        $tz = $this->tz();

        $rows = [];
        $running = [];
        foreach ($days as $d) {
            $e = $employees[$d->employee_id];
            $running[$e->id] = ($running[$e->id] ?? 0) + 1;
            $rows[] = $early ? [
                'date' => $d->date->format('Y-m-d'), 'code' => $e->code, 'name' => $e->fullName(),
                'shift_end' => substr((string) $d->shift?->end_time, 0, 5),
                'out' => $d->last_out?->setTimezone($tz)->format('H:i'),
                'minutes' => $d->early_out_min, 'count' => $running[$e->id],
            ] : [
                'date' => $d->date->format('Y-m-d'), 'code' => $e->code, 'name' => $e->fullName(),
                'shift_start' => substr((string) $d->shift?->start_time, 0, 5),
                'in' => $d->first_in?->setTimezone($tz)->format('H:i'),
                'minutes' => $d->late_min, 'count' => $running[$e->id],
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Occurrences' => count($rows), 'Employees' => count(array_unique(array_column($rows, 'code')))];
    }
}
