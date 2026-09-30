<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DevicePunchSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Device extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['comm_key'];

    protected function casts(): array
    {
        return [
            'type' => DevicePunchSourceType::class,
            'last_seen_at' => 'immutable_datetime',
            'last_sync_at' => 'immutable_datetime',
            'settings' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'serial_number', 'type', 'branch_id', 'timezone'])->logOnlyDirty();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }
}
