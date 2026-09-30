<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Services\Attendance\Engine\DayContext;
use App\Services\Attendance\Engine\EngineSettings;
use App\Services\Attendance\Engine\PunchInput;
use App\Services\Attendance\Engine\ResolvedShift;
use Carbon\CarbonImmutable;

/**
 * Builders for status-engine scenarios. All wall-clock strings are Asia/Karachi local time.
 * Reference week: 2026-09-01 is a Tuesday, 2026-09-04 Friday, 2026-09-06 Sunday.
 */
final class Scenario
{
    public const TZ = 'Asia/Karachi';

    public static function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, self::TZ)->utc();
    }

    public static function punch(
        string $local,
        PunchDirection $direction = PunchDirection::Unknown,
        PunchSource $source = PunchSource::Agent,
        bool $manual = false,
    ): PunchInput {
        return new PunchInput(self::at($local), $direction, $source, $manual);
    }

    /** Default office shift: 09:00–18:00, 60 min unpaid break → 480 expected minutes. */
    public static function shift(array $overrides = []): ResolvedShift
    {
        return (new ResolvedShift(
            shiftId: 1,
            startTime: '09:00',
            endTime: '18:00',
            graceInMin: 15,
            graceOutMin: 5,
            halfDayIfLateAfterMin: 120,
            halfDayIfWorkedLessThanMin: 240,
            breakMin: 60,
            weeklyOffDays: [0],
        ))->with($overrides);
    }

    /**
     * @param  list<string>  $punches  local datetimes
     * @param  array<string, mixed>  $overrides  DayContext constructor args
     */
    public static function day(string $date, array $punches = [], array $overrides = []): DayContext
    {
        $args = array_merge([
            'date' => $date,
            'timezone' => self::TZ,
            'now' => self::at('2026-09-20 12:00'),
            'joinedOn' => '2025-01-01',
            'leftOn' => null,
            'shift' => self::shift(),
            'punches' => array_map(fn (string $p) => self::punch($p), $punches),
            'settings' => new EngineSettings,
        ], $overrides);

        return new DayContext(...$args);
    }
}
