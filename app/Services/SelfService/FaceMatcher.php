<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Enums\EmployeeStatus;
use App\Models\FaceTemplate;
use App\Services\Settings\TenantSettings;

/**
 * Server-side face matching for the kiosk. Descriptors never leave the server: the tablet sends the descriptor of the
 * face in front of it, we compare it with the enrolled ones. Similarity is the same formula as Human's
 * `match.similarity` (euclidean distance × 25, normalised to 0..1 over 0.2..0.8), so > 0.5 is "probably the same face".
 */
final class FaceMatcher
{
    public const DESCRIPTOR_LENGTH = 1024;

    public function __construct(private readonly TenantSettings $settings) {}

    /** @param list<float> $a */
    public static function similarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($a as $i => $value) {
            $diff = (float) $value - (float) $b[$i];
            $sum += $diff * $diff;
        }
        $distance = round(100 * 25 * $sum) / 100;
        if ($distance == 0.0) {
            return 1.0;
        }
        $norm = (1 - (sqrt($distance) / 100) - 0.2) / (0.8 - 0.2);

        return round(100 * max(min($norm, 1), 0)) / 100;
    }

    /**
     * The best-matching active employee, if they clear the threshold and are clearly ahead of the next person.
     *
     * @param  list<float>  $descriptor
     * @return array{employee_id: ?int, similarity: float, runner_up: float}
     */
    public function identify(array $descriptor, ?int $branchId = null): array
    {
        $best = [];
        $query = FaceTemplate::query()->with('employee:id,branch_id,status')
            ->whereHas('employee', fn ($q) => $q->where('status', '!=', EmployeeStatus::Left)
                ->when($branchId, fn ($b) => $b->where(fn ($x) => $x->whereNull('branch_id')->orWhere('branch_id', $branchId))));
        foreach ($query->cursor() as $template) {
            $score = self::similarity($descriptor, $template->descriptor);
            $best[$template->employee_id] = max($best[$template->employee_id] ?? 0.0, $score);
        }
        arsort($best);
        $scores = array_values($best);
        $top = $scores[0] ?? 0.0;
        $second = $scores[1] ?? 0.0;
        $threshold = (float) $this->settings->get('face_match_threshold', 0.6);
        $margin = (float) $this->settings->get('face_match_margin', 0.05);

        return [
            'employee_id' => $top >= $threshold && $top - $second >= $margin ? (int) array_key_first($best) : null,
            'similarity' => $top,
            'runner_up' => $second,
        ];
    }

    /** @return list<float>|null a valid descriptor, or null */
    public static function clean(mixed $descriptor): ?array
    {
        if (! is_array($descriptor) || count($descriptor) !== self::DESCRIPTOR_LENGTH) {
            return null;
        }
        $out = [];
        foreach ($descriptor as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return null;
            }
            $out[] = round((float) $value, 6);
        }

        return $out;
    }
}
