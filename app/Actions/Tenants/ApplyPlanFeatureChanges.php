<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Features\FeatureCatalog;

/**
 * Editing a plan's feature switches updates every company on that plan. Only the switches that changed in the plan are
 * applied, so a company's own extras on other switches are kept; connected features follow (SetTenantFeatureFlags).
 */
final class ApplyPlanFeatureChanges
{
    public function __construct(private SetTenantFeatureFlags $setFlags) {}

    /**
     * @param  array<string, bool>|null  $before  the plan's switches before the edit
     * @return int companies updated
     */
    public function __invoke(Plan $plan, ?array $before): int
    {
        $catalog = FeatureCatalog::fromConfig();
        $old = $catalog->normalise($before ?? []);
        $new = $catalog->normalise($plan->feature_flags ?? []);
        $changed = array_diff_assoc($new, $old);
        if ($changed === []) {
            return 0;
        }

        $n = 0;
        foreach (Tenant::query()->where('plan_id', $plan->id)->cursor() as $tenant) {
            ($this->setFlags)($tenant, $changed);
            $n++;
        }

        return $n;
    }
}
