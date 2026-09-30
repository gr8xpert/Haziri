<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Money is integer rupees (spec §2 conventions). */
class SalaryStructure extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $hidden = ['bank_account'];

    protected function casts(): array
    {
        return ['effective_from' => 'date:Y-m-d', 'allowances' => 'array', 'deductions_fixed' => 'array', 'bank_account' => 'encrypted'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['bank_account'])->logAll()->logOnlyDirty();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowancesTotal(): int
    {
        return (int) array_sum(array_map(fn ($a) => (int) ($a['amount'] ?? 0), $this->allowances ?? []));
    }

    /** Latest structure effective on or before $date. */
    public static function effectiveFor(int $employeeId, string $date): ?self
    {
        return static::query()->where('employee_id', $employeeId)->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->first();
    }
}
