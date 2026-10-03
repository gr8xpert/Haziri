<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Actions\Tenants\ApplyPlanFeatureChanges;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;

    protected $fillable = ['code', 'name', 'price_per_employee', 'price_fixed', 'feature_flags', 'limits', 'is_active'];

    protected $casts = [
        'feature_flags' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Companies on the plan follow its feature switches (ApplyPlanFeatureChanges).
        static::updated(function (self $plan) {
            if ($plan->wasChanged('feature_flags')) {
                app(ApplyPlanFeatureChanges::class)($plan, $plan->getOriginal('feature_flags'));
            }
        });
    }
}
