<?php

declare(strict_types=1);

namespace App\Filament\Central;

use App\Services\Features\FeatureCatalog;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Feature switches grouped under Attendance / Payroll Lite / Payroll Full / Add-ons, for plans and per client.
 * Flipping one switch flips the connected ones straight away (see FeatureCatalog::apply) and says which.
 */
final class FeatureToggles
{
    /**
     * @param  string  $statePath  form key holding the {feature: bool} map, e.g. "flags" or "feature_flags"
     * @return list<Section>
     */
    public static function schema(string $statePath): array
    {
        $catalog = FeatureCatalog::fromConfig();
        $sections = [];
        foreach ($catalog->groups() as $group => $meta) {
            $toggles = [];
            foreach ($catalog->inGroup($group) as $key => $feature) {
                $needs = array_map(fn (string $r) => $catalog->label($r), $feature['requires']);
                $toggles[] = Toggle::make("{$statePath}.{$key}")
                    ->label($feature['label'])
                    ->helperText($needs === [] ? null : __('Needs: :list', ['list' => implode(', ', $needs)]))
                    ->live()
                    ->afterStateUpdated(fn (?bool $state, ?bool $old, Get $get, Set $set) => self::cascade($statePath, $key, (bool) $old, $get, $set));
            }
            $sections[] = Section::make($meta['label'])->description($meta['description'] ?? null)->columns(2)->schema($toggles)->compact();
        }

        return $sections;
    }

    private static function cascade(string $statePath, string $changed, bool $old, Get $get, Set $set): void
    {
        $catalog = FeatureCatalog::fromConfig();
        $after = array_map('boolval', (array) $get($statePath));
        $before = [$changed => $old] + $after;
        $result = $catalog->apply($before, $after);

        foreach ($result['flags'] as $key => $on) {
            if (($after[$key] ?? null) !== $on) {
                $set("{$statePath}.{$key}", $on);
            }
        }
        $labels = fn (array $keys) => implode(', ', array_map(fn (string $k) => $catalog->label($k), $keys));
        if ($result['turned_on'] !== []) {
            Notification::make()->info()->title(__('Also turned on: :list', ['list' => $labels($result['turned_on'])]))->send();
        }
        if ($result['turned_off'] !== []) {
            Notification::make()->warning()->title(__('Also turned off: :list', ['list' => $labels($result['turned_off'])]))->send();
        }
    }

    /**
     * Full {feature: bool} map for a form, with every catalog key present.
     *
     * @param  array<string, bool>|null  $stored
     * @return array<string, bool>
     */
    public static function fill(?array $stored): array
    {
        $catalog = FeatureCatalog::fromConfig();

        return array_combine($catalog->keys(), array_map(fn (string $k) => (bool) ($stored[$k] ?? $catalog->default($k)), $catalog->keys()));
    }
}
