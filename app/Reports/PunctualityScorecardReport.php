<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;
use App\Services\Analytics\PunctualityScore;
use Carbon\CarbonImmutable;

/** R17 — Punctuality scorecard: score, rank and trend vs the previous period of equal length. */
final class PunctualityScorecardReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Punctuality scorecard');
    }

    public function defaults(): array
    {
        $now = CarbonImmutable::now($this->tz());

        return ['from' => $now->subDays(29)->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
    }

    public function columns(array $filters): array
    {
        return ['rank' => 'Rank', 'code' => 'Code', 'name' => 'Name', 'score' => 'Score', 'trend' => 'vs previous',
            'working' => 'Working days', 'late' => 'Late', 'half' => 'Half days', 'absent' => 'Absent'];
    }

    public function rows(array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from']);
        $to = CarbonImmutable::parse($filters['to']);
        $length = (int) $from->diffInDays($to) + 1;
        $prevFrom = $from->subDays($length);

        $employees = $this->employees($filters)->get()->keyBy('id');
        $days = AttendanceDay::query()->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$prevFrom->format('Y-m-d'), $to->format('Y-m-d')])->get()->groupBy('employee_id');

        $rows = $employees->map(function ($e) use ($days, $from) {
            $all = $days->get($e->id, collect());
            $now = PunctualityScore::of($all->filter(fn ($d) => $d->date->format('Y-m-d') >= $from->format('Y-m-d')));
            $before = PunctualityScore::of($all->filter(fn ($d) => $d->date->format('Y-m-d') < $from->format('Y-m-d')));

            return ['code' => $e->code, 'name' => $e->fullName(), 'trend' => $before['working'] ? sprintf('%+d', $now['score'] - $before['score']) : ''] + $now;
        })->filter(fn ($r) => $r['working'] > 0)->sortByDesc('score')->values();

        return $rows->map(fn ($r, $i) => ['rank' => $i + 1] + $r)->all();
    }
}
