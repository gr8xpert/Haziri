<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Provident fund movement: monthly contributions (on payroll finalise), opening balances, payouts. */
class PfLedgerEntry extends Model
{
    use ScopedToUserBranches;

    protected $table = 'pf_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date:Y-m-d', 'employee_amount' => 'integer', 'employer_amount' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return array{employee: int, employer: int, total: int} */
    public static function balance(int $employeeId, ?string $asOf = null): array
    {
        $row = static::query()->where('employee_id', $employeeId)->when($asOf, fn ($q) => $q->where('entry_date', '<=', $asOf))
            ->selectRaw('COALESCE(SUM(employee_amount),0) e, COALESCE(SUM(employer_amount),0) r')->first();

        return ['employee' => (int) $row->e, 'employer' => (int) $row->r, 'total' => (int) $row->e + (int) $row->r];
    }
}
