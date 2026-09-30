<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Settings\TenantSettings;

/**
 * Spec §12.6: paste the company's attendance policy; the model proposes values for known settings and the default
 * shift. Returned as a diff (current → proposed) that the owner confirms item by item. Unknown keys are dropped.
 */
final class SetupAssistant
{
    public const SETTING_KEYS = ['lates_per_absent', 'half_day_counts_as', 'absent_deduction_days', 'overtime_enabled',
        'overtime_rounding_min', 'auto_absent_after_days', 'dedupe_window_sec'];

    public const SHIFT_KEYS = ['start_time', 'end_time', 'grace_in_min', 'half_day_if_late_after_min',
        'half_day_if_worked_less_than_min', 'break_min', 'weekly_off_days'];

    public function __construct(private readonly AiGateway $ai, private readonly TenantSettings $settings) {}

    /** @return array{ok: bool, diff?: list<array{target: string, key: string, current: mixed, proposed: mixed, reason: string}>, error?: string} */
    public function propose(string $policy, int $userId): array
    {
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['changes'], 'properties' => [
            'changes' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                'required' => ['target', 'key', 'value', 'reason'],
                'properties' => [
                    'target' => ['type' => 'string', 'enum' => ['setting', 'shift']],
                    'key' => ['type' => 'string', 'enum' => [...self::SETTING_KEYS, ...self::SHIFT_KEYS]],
                    'value' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ]]],
        ]];

        $result = $this->ai->ask('setup', mb_substr($policy, 0, 500),
            'You configure an attendance system from a company policy. Propose only values the policy clearly states. '
            . 'Settings: lates_per_absent (int, 0=off), half_day_counts_as (0..1), absent_deduction_days, overtime_enabled (bool), '
            . 'overtime_rounding_min, auto_absent_after_days, dedupe_window_sec. Default shift: start_time/end_time "HH:MM", '
            . 'grace_in_min, half_day_if_late_after_min, half_day_if_worked_less_than_min, break_min (minutes), '
            . 'weekly_off_days (comma-separated 0=Sunday..6=Saturday). Give every value as a string. Quote the policy sentence in reason.',
            [['type' => 'text', 'text' => $policy]], $schema, 'medium', $userId, 3000);

        if (! $result->ok) {
            return ['ok' => false, 'error' => $result->error];
        }
        $shift = \App\Models\Shift::query()->where('is_default', true)->first();
        $diff = [];
        foreach ($result->data['changes'] ?? [] as $c) {
            $allowed = $c['target'] === 'setting' ? self::SETTING_KEYS : self::SHIFT_KEYS;
            if (! in_array($c['key'], $allowed, true)) {
                continue;
            }
            $current = $c['target'] === 'setting' ? $this->settings->get($c['key']) : $shift?->{$c['key']};
            $diff[] = ['target' => $c['target'], 'key' => $c['key'], 'current' => $current, 'proposed' => self::cast($c['key'], (string) $c['value']),
                'reason' => (string) $c['reason']];
        }

        return ['ok' => true, 'diff' => $diff];
    }

    /** Model values arrive as strings; convert to what each key stores. */
    public static function cast(string $key, string $value): mixed
    {
        return match ($key) {
            'overtime_enabled' => in_array(strtolower($value), ['1', 'true', 'yes'], true),
            'half_day_counts_as', 'absent_deduction_days' => (float) $value,
            'start_time', 'end_time' => substr($value, 0, 5),
            'weekly_off_days' => array_values(array_map('intval', array_filter(array_map('trim', explode(',', $value)), 'is_numeric'))),
            default => (int) $value,
        };
    }
}
