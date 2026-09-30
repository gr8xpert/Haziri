<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Announcement extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'audience' => 'array', 'publish_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime',
            'requires_ack' => 'boolean', 'notify' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'publish_at', 'expires_at'])->logOnlyDirty();
    }

    public function acks(): HasMany
    {
        return $this->hasMany(AnnouncementAck::class);
    }

    /** Published, not expired, and addressed to the employee's branch/department (or everyone). */
    public static function visibleTo(Employee $employee): Builder
    {
        $targets = fn (string $key, ?int $id) => fn (Builder $q) => $q->whereNull("audience->{$key}")
            ->orWhereJsonLength("audience->{$key}", 0)
            ->when($id !== null, fn (Builder $q) => $q->orWhereJsonContains("audience->{$key}", $id));

        return static::query()->with('acks')
            ->where(fn (Builder $q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where($targets('branch_ids', $employee->branch_id))
            ->where($targets('department_ids', $employee->department_id))
            ->latest();
    }

    public function isFor(Employee $employee): bool
    {
        $branches = $this->audience['branch_ids'] ?? [];
        $departments = $this->audience['department_ids'] ?? [];

        return ($branches === [] || in_array($employee->branch_id, array_map('intval', $branches), true))
            && ($departments === [] || in_array($employee->department_id, array_map('intval', $departments), true));
    }
}
