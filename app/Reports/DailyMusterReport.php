<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

/** R1 — Daily muster roll. */
final class DailyMusterReport extends Report
{
    public function title(): string
    {
        return __('Daily muster roll');
    }

    public function filterSchema(): array
    {
        return [
            DatePicker::make('date')->required()->maxDate(now()),
            Select::make('att_status')->label(__('Attendance status'))->placeholder(__('All'))
                ->options(['late' => 'Late'] + collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->name])->all()),
            ...$this->orgFilters(),
        ];
    }

    public function defaults(): array
    {
        return ['date' => CarbonImmutable::now($this->tz())->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return CarbonImmutable::parse($filters['date'])->format('l, d M Y');
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return [
            'code' => 'Code', 'name' => 'Name', 'department' => 'Department', 'shift' => 'Shift', 'in' => 'In', 'out' => 'Out',
            'worked' => 'Worked', 'late' => 'Late', 'early' => 'Early', 'status' => 'Status', 'flags' => 'Flags', 'source' => 'Source',
        ];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->with(['department'])->get();
        $days = AttendanceDay::query()->with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))->whereDate('date', $filters['date'])->get()->keyBy('employee_id');
        $tz = $this->tz();

        $rows = [];
        foreach ($employees as $e) {
            $d = $days->get($e->id);
            if ($d === null && ($e->joined_on?->format('Y-m-d') > $filters['date'])) {
                continue;
            }
            $wanted = $filters['att_status'] ?? null;
            if ($wanted !== null && ($wanted === 'late' ? ! in_array('late', $d->flags ?? [], true) : $d?->status?->value !== $wanted)) {
                continue;
            }
            $rows[] = [
                'code' => $e->code,
                'name' => $e->fullName(),
                'department' => $e->department?->name,
                'shift' => $d?->shift?->code,
                'in' => $d?->first_in?->setTimezone($tz)->format('H:i'),
                'out' => $d?->last_out?->setTimezone($tz)->format('H:i'),
                'worked' => self::hm($d->worked_min ?? 0),
                'late' => ($d->late_min ?? 0) ?: null,
                'early' => ($d->early_out_min ?? 0) ?: null,
                'status' => $d?->status?->name ?? 'Pending',
                'flags' => implode(', ', $d->flags ?? []),
                'source' => implode(', ', array_keys($d->source_summary ?? [])),
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        $counts = array_count_values(array_column($rows, 'status'));
        ksort($counts);

        return $counts + ['Total' => count($rows)];
    }
}
