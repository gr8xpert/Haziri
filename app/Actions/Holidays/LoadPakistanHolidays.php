<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\Enums\HolidayType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a year's gazetted Pakistan holidays into the current tenant DB. Skips dates that already have
 * a holiday, so it's safe to run again and never overwrites HR edits. Returns the number inserted.
 */
final class LoadPakistanHolidays
{
    public function __invoke(int $year): int
    {
        $rows = [];
        foreach (config('holidays_pk.fixed') as $monthDay => $name) {
            $rows["{$year}-{$monthDay}"] = [$name, false];
        }
        foreach (config("holidays_pk.islamic.{$year}", []) as $date => $name) {
            $rows[$date] = [$name, true];
        }

        $existing = DB::table('holidays')->whereYear('date', $year)->whereNull('branch_id')->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();

        $now = CarbonImmutable::now();
        $inserted = 0;
        foreach ($rows as $date => [$name, $provisional]) {
            if (in_array($date, $existing, true)) {
                continue;
            }
            DB::table('holidays')->insert([
                'date' => $date,
                'name' => $name,
                'type' => HolidayType::Gazetted->value,
                'is_paid' => true,
                'needs_confirmation' => $provisional,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        return $inserted;
    }
}
