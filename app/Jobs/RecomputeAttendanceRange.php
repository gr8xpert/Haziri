<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Attendance\AttendanceRecomputer;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Bulk recompute (holiday added, shift changed…). Callers chunk employees ≤ 500 per job (spec §15). */
final class RecomputeAttendanceRange implements ShouldQueue
{
    use Queueable;

    public const CHUNK = 500;

    public int $timeout = 300;

    /**
     * @param  list<int>  $employeeIds
     */
    public function __construct(public readonly array $employeeIds, public readonly string $from, public readonly string $to) {}

    /**
     * @param  iterable<int>  $employeeIds
     */
    public static function dispatchChunked(iterable $employeeIds, string $from, string $to): void
    {
        $ids = is_array($employeeIds) ? $employeeIds : iterator_to_array($employeeIds, false);
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            self::dispatch($chunk, $from, $to);
        }
    }

    public function handle(AttendanceRecomputer $recomputer): void
    {
        $now = CarbonImmutable::now('UTC');
        foreach (CarbonPeriod::create($this->from, $this->to) as $date) {
            foreach ($this->employeeIds as $id) {
                $recomputer->recompute($id, $date->format('Y-m-d'), $now);
            }
        }
    }
}
