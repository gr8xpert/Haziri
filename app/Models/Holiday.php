<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Holiday extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /** branch_key (0 = all branches) backs the one-holiday-per-date-and-branch unique index. */
    protected static function booted(): void
    {
        static::saving(fn (Holiday $holiday) => $holiday->branch_key = $holiday->branch_id ?? 0);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => HolidayType::class,
            'is_paid' => 'boolean',
            'needs_confirmation' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logExcept(['branch_key'])->logOnlyDirty();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function optIns(): HasMany
    {
        return $this->hasMany(HolidayOptIn::class);
    }

    /**
     * Holidays that apply to this employee on this date: company-wide or their branch, and an optional holiday only
     * when they take it.
     */
    public function scopeAppliesTo(Builder $query, Employee $employee, string $date): Builder
    {
        return $query->whereDate('date', $date)
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))
            ->where(fn (Builder $q) => $q->where('type', '!=', HolidayType::Optional->value)
                ->orWhereHas('optIns', fn (Builder $o) => $o->where('employee_id', $employee->id)));
    }
}
