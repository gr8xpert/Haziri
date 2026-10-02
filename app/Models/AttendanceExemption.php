<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExemptionType;
use App\Enums\ShiftOverrideScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AttendanceExemption extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $attributes = ['scope' => 'tenant'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'type' => ExemptionType::class, 'scope' => ShiftOverrideScope::class];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    /** Exemptions on $date that cover this employee (whole company, their branch or their department). */
    public function scopeCovering(Builder $query, Employee $employee, string $date): Builder
    {
        return $query->whereDate('date', $date)->where(fn (Builder $q) => $q
            ->where('scope', ShiftOverrideScope::Tenant)
            ->orWhere(fn (Builder $b) => $b->where('scope', ShiftOverrideScope::Branch)->where('scope_id', $employee->branch_id))
            ->orWhere(fn (Builder $d) => $d->where('scope', ShiftOverrideScope::Department)->where('scope_id', $employee->department_id)));
    }

    /** @return list<int>|null employee ids in scope, null = everyone */
    public function employeeIds(): ?array
    {
        return match ($this->scope) {
            ShiftOverrideScope::Branch => Employee::where('branch_id', $this->scope_id)->pluck('id')->all(),
            ShiftOverrideScope::Department => Employee::where('department_id', $this->scope_id)->pluck('id')->all(),
            default => null,
        };
    }
}
