<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Filament\Forms\Components\TextInput;

/** R8 — Leave balance: employee × leave types (quota/used/pending/remaining). */
final class LeaveBalanceReport extends Report
{
    public function title(): string
    {
        return __('Leave balances');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('year')->numeric()->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['year' => (int) date('Y')];
    }

    public function subtitle(array $filters): string
    {
        return (string) ($filters['year'] ?? '');
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        $cols = ['code' => 'Code', 'name' => 'Name'];
        foreach (LeaveType::where('is_active', true)->orderBy('code')->get() as $t) {
            $cols["{$t->code}_q"] = "{$t->code} quota";
            $cols["{$t->code}_u"] = "{$t->code} used";
            $cols["{$t->code}_p"] = "{$t->code} pending";
            $cols["{$t->code}_r"] = "{$t->code} left";
        }

        return $cols;
    }

    public function rows(array $filters): array
    {
        $types = LeaveType::where('is_active', true)->get()->keyBy('id');
        $employees = $this->employees($filters)->get();
        $balances = LeaveBalance::query()->whereIn('employee_id', $employees->pluck('id'))->where('year', (int) $filters['year'])
            ->get()->groupBy('employee_id');

        return $employees->map(function ($e) use ($types, $balances) {
            $row = ['code' => $e->code, 'name' => $e->fullName()];
            foreach ($balances->get($e->id, collect()) as $b) {
                $code = $types[$b->leave_type_id]->code ?? null;
                if ($code === null) {
                    continue;
                }
                $row["{$code}_q"] = $b->opening + $b->accrued + $b->adjusted;
                $row["{$code}_u"] = $b->used;
                $row["{$code}_p"] = $b->pending;
                $row["{$code}_r"] = $b->remaining();
            }

            return $row;
        })->all();
    }
}
