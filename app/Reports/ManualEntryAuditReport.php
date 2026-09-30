<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Punch;

/** R14 — Manual entry audit: every manual / removed punch with who and why. */
final class ManualEntryAuditReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Manual entry audit');
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'time' => 'Time', 'code' => 'Code', 'name' => 'Name', 'direction' => 'Dir.',
            'reason' => 'Reason', 'by' => 'Entered by', 'entered_at' => 'Entered at', 'removed' => 'Removed'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->get()->keyBy('id');
        $tz = $this->tz();

        return Punch::withTrashed()->with('enteredBy')
            ->whereIn('employee_id', $employees->keys())
            ->whereBetween('local_date', [$filters['from'], $filters['to']])
            ->where(fn ($q) => $q->where('is_manual', true)->orWhereNotNull('deleted_at'))
            ->orderBy('punched_at')->get()
            ->map(fn (Punch $p) => [
                'date' => $p->local_date->format('Y-m-d'),
                'time' => $p->punched_at->setTimezone($tz)->format('H:i'),
                'code' => $employees[$p->employee_id]->code,
                'name' => $employees[$p->employee_id]->fullName(),
                'direction' => $p->direction->value,
                'reason' => $p->reason ?? ($p->meta['removed_reason'] ?? ''),
                'by' => $p->enteredBy?->name,
                'entered_at' => $p->created_at->setTimezone($tz)->format('Y-m-d H:i'),
                'removed' => $p->deleted_at ? $p->deleted_at->setTimezone($tz)->format('Y-m-d H:i') : '',
            ])->all();
    }
}
