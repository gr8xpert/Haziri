<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Visitor extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'phone', 'cnic', 'company', 'host_employee_id', 'purpose', 'photo_path', 'branch_id',
        'checked_in_at', 'checked_out_at', 'badge_no', 'entered_by',
    ];

    protected function casts(): array
    {
        return ['checked_in_at' => 'immutable_datetime', 'checked_out_at' => 'immutable_datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'host_employee_id', 'checked_in_at', 'checked_out_at'])->logOnlyDirty();
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'host_employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Next free badge number today: V001, V002… */
    public static function nextBadge(): string
    {
        $today = now(tenant('timezone'))->startOfDay()->utc();
        $n = static::query()->where('checked_in_at', '>=', $today)->count() + 1;

        return sprintf('V%03d', $n);
    }
}
