<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\Features\FeatureCatalog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Current tenant's settings merged over config('hazri.settings_defaults').
 * Registered as a scoped binding, so each request / queued job re-reads its own tenant.
 */
final class TenantSettings
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->values === null) {
            $stored = Setting::query()->pluck('value', 'key')->all();
            $this->values = array_merge(config('hazri.settings_defaults'), $stored);
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** Provider credentials are stored encrypted (spec §15: "encrypt provider credentials"). */
    public function secret(string $key): ?string
    {
        $value = $this->get($key);
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    /** Unset switches fall back to the catalog default (config/features.php). */
    public function featureEnabled(string $flag): bool
    {
        $value = $this->get("feature.{$flag}");

        return $value === null ? FeatureCatalog::fromConfig()->default($flag) : (bool) $value;
    }

    public function flush(): void
    {
        $this->values = null;
    }
}
