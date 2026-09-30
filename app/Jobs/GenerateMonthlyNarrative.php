<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AttendancePeriod;
use App\Services\Ai\AiGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Spec §12.2: after a month is locked, a 200–300-word English + Urdu summary written from the locked snapshot
 * (and the previous month's, for comparison). Stored on the period; HR can edit it.
 */
final class GenerateMonthlyNarrative implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $periodId) {}

    public function handle(AiGateway $ai): void
    {
        $period = AttendancePeriod::find($this->periodId);
        if ($period === null || ! $ai->available()) {
            return;
        }
        $previous = AttendancePeriod::query()
            ->where(fn ($q) => $q->where('year', $period->month === 1 ? $period->year - 1 : $period->year)
                ->where('month', $period->month === 1 ? 12 : $period->month - 1))
            ->value('summary');

        $facts = json_encode([
            'company' => tenant('company_name'),
            'month' => date('F Y', mktime(0, 0, 0, $period->month, 1, $period->year)),
            'this_month' => self::compact($period->summary ?? []),
            'previous_month' => $previous ? self::compact($previous) : null,
        ], JSON_UNESCAPED_UNICODE);

        $result = $ai->ask('narrative', 'monthly narrative ' . $period->year . '-' . $period->month,
            "You write a monthly attendance summary for a company owner in Pakistan. Use only the numbers given. "
            . "200–300 words, plain language, no headings or bullet lists. Cover the attendance rate, the most frequent late arrivals, "
            . "absence patterns, leave usage, overtime, and the change from the previous month when it is given. "
            . "Also give a faithful Urdu version.",
            [['type' => 'text', 'text' => $facts]],
            ['type' => 'object', 'properties' => ['en' => ['type' => 'string'], 'ur' => ['type' => 'string']],
                'required' => ['en', 'ur'], 'additionalProperties' => false],
            'medium', null, 6000);

        if ($result->ok) {
            $period->update(['narrative' => $result->data + ['generated_at' => now()->toIso8601String()]]);
        }
    }

    /** Totals + the ten most notable employees, so the prompt stays small and contains no CNICs or salaries. */
    private static function compact(array $summary): array
    {
        $employees = collect($summary['employees'] ?? []);

        return [
            'totals' => $summary['totals'] ?? [],
            'employees' => $employees->count(),
            'most_late' => $employees->sortByDesc('lateCount')->take(5)->map(fn ($e) => ['name' => $e['name'], 'lates' => $e['lateCount']])->values(),
            'most_absent' => $employees->sortByDesc('absentDays')->take(5)->map(fn ($e) => ['name' => $e['name'], 'absent' => $e['absentDays']])->values(),
            'paid_leave_days' => $employees->sum('paidLeaveDays'),
            'unpaid_leave_days' => $employees->sum('unpaidLeaveDays'),
            'overtime_hours' => round($employees->sum('overtimeMinutes') / 60, 1),
        ];
    }
}
