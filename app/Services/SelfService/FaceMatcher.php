<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
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

    /** @var array<string, string> face-check results on a punch (meta.face_check.result) */
    public const CHECK_LABELS = [
        'match' => 'Face matches', 'mismatch' => 'Face does not match', 'no_face' => 'No face in the photo',
        'not_enrolled' => 'Face not enrolled', 'unverified_device' => 'Kiosk not locked to approved tablets',
    ];

    /**
     * Face check on a punch made with a PIN, QR card or phone, when the company turned it on (setting
     * face_check_photo_punches, needs kiosk_face). Null = not checked; anything but "match" sends the punch to approval.
     *
     * Limitation: the descriptor is computed on the client. The kiosk only trusts approved tablets (KioskPunch); on a
     * phone the employee controls the device, so a technically able person could send a descriptor computed from a
     * colleague's picture. There it deters ordinary buddy punching; the stored selfie stays the evidence for HR.
     *
     * @return array{result: string, similarity: ?float}|null
     */
    public function checkPunch(Employee $employee, mixed $descriptor): ?array
    {
        if (! $this->settings->featureEnabled('kiosk_face') || ! $this->settings->get('face_check_photo_punches')) {
            return null;
        }

        return $this->verify($employee, $descriptor);
    }

    /**
     * 1:1 check: does this face belong to the employee? (Kiosk face mode instead searches everyone, see identify().)
     *
     * @return array{result: string, similarity: ?float}
     */
    public function verify(Employee $employee, mixed $descriptor): array
    {
        $templates = FaceTemplate::query()->where('employee_id', $employee->id)->get();
        if ($templates->isEmpty()) {
            return ['result' => 'not_enrolled', 'similarity' => null];
        }
        $clean = self::clean($descriptor);
        if ($clean === null) {
            return ['result' => 'no_face', 'similarity' => null];
        }
        $best = (float) $templates->max(fn (FaceTemplate $t) => self::similarity($clean, $t->descriptor));

        return ['result' => $best >= (float) $this->settings->get('face_match_threshold', 0.6) ? 'match' : 'mismatch', 'similarity' => $best];
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
