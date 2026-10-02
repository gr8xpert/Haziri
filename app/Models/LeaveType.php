<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LeaveType extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    /** Mirror the DB defaults so a freshly created type behaves the same before it is reloaded. */
    protected $attributes = [
        'is_paid' => true,
        'is_active' => true,
        'accrual' => 'yearly_upfront',
        'allow_negative_balance' => false,
        'encashable' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'allow_half_day' => 'boolean',
            'sandwich_rule' => 'boolean',
            'counts_weekends' => 'boolean',
            'is_active' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'encashable' => 'boolean',
            'applies_to' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class);
    }
}
