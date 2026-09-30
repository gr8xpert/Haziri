<?php

declare(strict_types=1);

use App\Services\Attendance\Engine\SandwichRule;

$weekend = fn (string $d): bool => in_array($d, ['2026-09-05', '2026-09-06'], true);

it('pulls a weekend between Friday and Monday leave into leave', function () use ($weekend) {
    expect((new SandwichRule)->sandwichedDates(['2026-09-04', '2026-09-07'], $weekend))
        ->toBe(['2026-09-05', '2026-09-06']);
});

it('does not sandwich when a working day sits in between', function () use ($weekend) {
    expect((new SandwichRule)->sandwichedDates(['2026-09-03', '2026-09-07'], $weekend))->toBe([]);
});

it('does not sandwich leave on only one side', function () use ($weekend) {
    expect((new SandwichRule)->sandwichedDates(['2026-09-04'], $weekend))->toBe([]);
});

it('handles unsorted and duplicate input', function () use ($weekend) {
    expect((new SandwichRule)->sandwichedDates(['2026-09-07', '2026-09-04', '2026-09-04'], $weekend))
        ->toBe(['2026-09-05', '2026-09-06']);
});
