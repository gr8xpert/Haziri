<?php

declare(strict_types=1);

use App\Support\Duration;

it('writes durations in hours and minutes once they reach an hour', function (int $minutes, string $text) {
    expect(Duration::human($minutes))->toBe($text);
})->with([
    [1, '1 minute'],
    [45, '45 minutes'],
    [60, '1 hour'],
    [90, '1 hour 30 minutes'],
    [272, '4 hours 32 minutes'],
    [403, '6 hours 43 minutes'],
    [-120, '2 hours'],
]);
