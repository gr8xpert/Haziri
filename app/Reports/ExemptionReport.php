<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\ShiftOverrideScope;
use App\Models\AttendanceExemption;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/** R22 — Exemptions: the dates on which lates or absences were excused, and for whom. */
final class ExemptionReport extends Report
{
    public function title(): string
    {
        return __('Exemptions');
    }

    public function filterSchema(): array
    {
        return [DatePicker::make('from')->required(), DatePicker::make('to')->required()];
    }

    public function defaults(): array
    {
        $now = CarbonImmutable::now($this->tz());

        return ['from' => $now->startOfYear()->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
    }

    public function ready(array $filters): bool
    {
        return ! empty($filters['from']) && ! empty($filters['to']);
    }

    public function subtitle(array $filters): string
    {
        return self::day($filters['from'] ?? null) . ' → ' . self::day($filters['to'] ?? null);
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'day' => 'Day', 'type' => 'Exemption type', 'reason' => 'Reason', 'scope' => 'Applies to',
            'by' => 'Entered by', 'created' => 'Created on'];
    }

    public function rows(array $filters): array
    {
        $users = User::pluck('name', 'id');

        return AttendanceExemption::query()->whereBetween('date', [$filters['from'], $filters['to']])->orderBy('date')->get()
            ->map(fn (AttendanceExemption $x) => [
                'date' => self::day($x->date),
                'day' => $x->date->format('l'),
                'type' => $x->type->getLabel(),
                'reason' => $x->reason,
                'scope' => match ($x->scope) {
                    ShiftOverrideScope::Branch => __('Branch: :name', ['name' => Branch::find($x->scope_id)?->name]),
                    ShiftOverrideScope::Department => __('Department: :name', ['name' => Department::find($x->scope_id)?->name]),
                    default => __('Whole company'),
                },
                'by' => $users[$x->created_by] ?? null,
                'created' => self::day($x->created_at?->setTimezone($this->tz())),
            ])->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Days' => count($rows)];
    }
}
