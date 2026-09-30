<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AttendancePeriod extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PeriodStatus::class, 'locked_at' => 'immutable_datetime', 'summary' => 'array'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'locked_by', 'locked_at'])->logOnlyDirty();
    }

    public static function isLocked(string $date): bool
    {
        [$y, $m] = array_map('intval', explode('-', $date));

        return static::query()->where(['year' => $y, 'month' => $m, 'status' => PeriodStatus::Locked])->exists();
    }
}
