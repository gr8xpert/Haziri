<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\VerifyType;
use App\Services\Attendance\Engine\PunchInput;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Punch extends Model
{
    use LogsActivity {
        shouldLogEvent as baseShouldLogEvent;
    }
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'punched_at' => 'immutable_datetime',
            'local_date' => 'date:Y-m-d',
            'direction' => PunchDirection::class,
            'source' => PunchSource::class,
            'verify_type' => VerifyType::class,
            'status' => PunchStatus::class,
            'is_manual' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Machine punches arrive in bulk; only human-entered ones go to the audit log.
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return ($this->is_manual || $eventName !== 'created') && $this->baseShouldLogEvent($eventName);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function toInput(): PunchInput
    {
        /** @var CarbonImmutable $at */
        $at = $this->punched_at;

        return new PunchInput(
            at: CarbonImmutable::parse($at->format('Y-m-d H:i:s'), 'UTC'),
            direction: $this->direction,
            source: $this->source,
            isManual: $this->is_manual,
            geofenceFail: (bool) ($this->meta['geofence_fail'] ?? false),
        );
    }
}
