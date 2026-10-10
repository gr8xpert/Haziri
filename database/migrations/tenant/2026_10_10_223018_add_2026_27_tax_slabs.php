<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salaried individuals, tax year 2026-27 (Finance Act 2026, from 1 July 2026): eight bands, and the 9% surcharge on
 * income over Rs 10 million is abolished (IncomeTax applies it up to tax year 2025 only). Pre-filled for
 * convenience; companies that already entered 2026-27 rates keep theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tax_slabs')->where('tax_year', 2026)->exists()) {
            return;
        }
        $now = now();
        foreach ([[0, 600000, 0, 0], [600000, 1200000, 0, 1], [1200000, 2200000, 6000, 11], [2200000, 3200000, 116000, 20],
            [3200000, 4100000, 316000, 25], [4100000, 5600000, 541000, 29], [5600000, 7000000, 976000, 32],
            [7000000, null, 1424000, 35]] as [$from, $to, $fixed, $rate]) {
            DB::table('tax_slabs')->insert(['tax_year' => 2026, 'from_amount' => $from, 'to_amount' => $to, 'fixed_tax' => $fixed,
                'rate_percent' => $rate, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('tax_slabs')->where('tax_year', 2026)->delete();
    }
};
