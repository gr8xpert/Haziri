<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Someone punched with PIN 47 but no employee has PIN 47" (spec §5.4). */
class UnmappedPin extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'immutable_datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function resolvedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'resolved_employee_id');
    }
}
