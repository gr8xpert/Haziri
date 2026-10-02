<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Services\Attendance\Period\PayCycle;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AttendancePeriod extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PeriodStatus::class, 'locked_at' => 'immutable_datetime', 'summary' => 'array', 'narrative' => 'array'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'locked_by', 'locked_at'])->logOnlyDirty();
    }

    /** Is the pay-cycle month containing $date locked? */
    public static function isLocked(string $date): bool
    {
        [$y, $m] = app(PayCycle::class)->periodFor($date);

        return static::query()->where(['year' => $y, 'month' => $m, 'status' => PeriodStatus::Locked])->exists();
    }

    /** @return array{0: string, 1: string} first and last date (Y-m-d) of this pay-cycle month */
    public function dates(): array
    {
        return app(PayCycle::class)->dates($this->year, $this->month);
    }

    public function label(): string
    {
        return app(PayCycle::class)->label($this->year, $this->month);
    }
}
