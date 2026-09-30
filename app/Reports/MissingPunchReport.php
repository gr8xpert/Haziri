<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Builder;

/** R6 — Missing punch report. */
final class MissingPunchReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Missing punches');
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'code' => 'Code', 'name' => 'Name', 'missing' => 'Missing', 'in' => 'In', 'out' => 'Out', 'auto' => 'Auto-closed'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->get()->keyBy('id');
        $tz = $this->tz();

        return AttendanceDay::query()->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->where(fn (Builder $q) => $q->whereJsonContains('flags', 'missing_out')->orWhereJsonContains('flags', 'missing_in'))
            ->orderBy('date')->get()
            ->map(fn (AttendanceDay $d) => [
                'date' => $d->date->format('Y-m-d'),
                'code' => $employees[$d->employee_id]->code,
                'name' => $employees[$d->employee_id]->fullName(),
                'missing' => in_array('missing_in', $d->flags ?? [], true) ? 'In' : 'Out',
                'in' => $d->first_in?->setTimezone($tz)->format('H:i'),
                'out' => $d->last_out?->setTimezone($tz)->format('H:i'),
                'auto' => in_array('auto_closed', $d->flags ?? [], true) ? 'yes' : '',
            ])->all();
    }
}
