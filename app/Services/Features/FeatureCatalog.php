<?php

declare(strict_types=1);

namespace App\Services\Features;

/**
 * The sellable features (config/features.php) and how they depend on each other. Pure: built from the config array,
 * so it can be unit-tested without Laravel.
 */
final class FeatureCatalog
{
    /**
     * @param  array{groups: array<string, array{label: string, description?: string}>, features: array<string, array{group: string, label: string, requires: list<string>, default: bool}>}  $config
     */
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self(config('features'));
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->config['features']);
    }

    /** @return array<string, array{label: string, description?: string}> */
    public function groups(): array
    {
        return $this->config['groups'];
    }

    /** @return array<string, array{group: string, label: string, requires: list<string>, default: bool}> features in $group */
    public function inGroup(string $group): array
    {
        return array_filter($this->config['features'], fn (array $f) => $f['group'] === $group);
    }

    public function label(string $feature): string
    {
        return $this->config['features'][$feature]['label'] ?? $feature;
    }

    public function default(string $feature): bool
    {
        return (bool) ($this->config['features'][$feature]['default'] ?? false);
    }

    /** @return list<string> everything $feature needs, directly or indirectly */
    public function requirements(string $feature): array
    {
        $seen = [];
        $stack = $this->config['features'][$feature]['requires'] ?? [];
        while ($stack !== []) {
            $next = array_pop($stack);
            if (! isset($seen[$next])) {
                $seen[$next] = true;
                array_push($stack, ...($this->config['features'][$next]['requires'] ?? []));
            }
        }

        return array_keys($seen);
    }

    /** @return list<string> everything that needs $feature, directly or indirectly */
    public function dependants(string $feature): array
    {
        return array_values(array_filter($this->keys(), fn (string $f) => in_array($feature, $this->requirements($f), true)));
    }

    /**
     * Apply a change of switches: whatever was newly turned on brings its requirements, whatever was newly turned off
     * takes its dependants with it. Unknown keys are dropped; every known key is present in the result.
     *
     * @param  array<string, bool>  $before
     * @param  array<string, bool>  $after
     * @return array{flags: array<string, bool>, turned_on: list<string>, turned_off: list<string>} turned_on / turned_off = automatic changes only
     */
    public function apply(array $before, array $after): array
    {
        $flags = [];
        foreach ($this->keys() as $key) {
            $flags[$key] = (bool) ($after[$key] ?? $before[$key] ?? $this->default($key));
        }
        $was = fn (string $k) => (bool) ($before[$k] ?? $this->default($k));
        $autoOn = [];
        $autoOff = [];

        foreach ($this->keys() as $key) {
            if ($flags[$key] && ! $was($key)) {
                foreach ($this->requirements($key) as $required) {
                    if (! $flags[$required]) {
                        $flags[$required] = true;
                        $autoOn[] = $required;
                    }
                }
            }
        }
        foreach ($this->keys() as $key) {
            if (! $flags[$key] && $was($key)) {
                foreach ($this->dependants($key) as $dependant) {
                    if ($flags[$dependant]) {
                        $flags[$dependant] = false;
                        $autoOff[] = $dependant;
                    }
                }
            }
        }
        $normalised = $this->normalise($flags);
        $autoOff = [...$autoOff, ...array_keys(array_diff_assoc($flags, $normalised))];

        return ['flags' => $normalised, 'turned_on' => array_values(array_unique($autoOn)), 'turned_off' => array_values(array_unique($autoOff))];
    }

    /**
     * Turn off anything whose requirements are off (e.g. a plan saved before a rule existed). Every known key is present.
     *
     * @param  array<string, bool>  $flags
     * @return array<string, bool>
     */
    public function normalise(array $flags): array
    {
        $out = [];
        foreach ($this->keys() as $key) {
            $out[$key] = (bool) ($flags[$key] ?? $this->default($key));
        }
        foreach ($this->keys() as $key) {
            if ($out[$key] && array_filter($this->requirements($key), fn (string $r) => ! $out[$r]) !== []) {
                $out[$key] = false;
            }
        }

        return $out;
    }
}
