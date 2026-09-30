<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

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
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    public function featureEnabled(string $flag): bool
    {
        return (bool) $this->get("feature.{$flag}", false);
    }

    public function flush(): void
    {
        $this->values = null;
    }
}
