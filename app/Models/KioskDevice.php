<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** A physical tablet that opened a kiosk link. Only approved tablets may punch on a locked kiosk. */
class KioskDevice extends Model
{
    use LogsActivity;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const BLOCKED = 'blocked';

    protected $guarded = ['id'];

    protected $hidden = ['key_hash'];

    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'decided_by'])->logOnlyDirty();
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }
}
