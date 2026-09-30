<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WebhookEndpoint extends Model
{
    use LogsActivity;

    public const EVENTS = ['punch.created', 'attendance_day.updated', 'leave.decided', 'period.locked', 'payroll.generated'];

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean', 'secret' => 'encrypted'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['url', 'events', 'is_active'])->logOnlyDirty();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
