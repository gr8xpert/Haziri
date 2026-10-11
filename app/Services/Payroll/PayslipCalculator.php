<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Advance;
use App\Models\SalaryStructure;

/**
 * Payroll-lite (spec §9 phase 3): structure + locked period numbers → earnings, deductions, net. Integer rupees.
 *
 * Monthly staff:  gross = basic + allowances; attendance deduction = deduction days × (gross ÷ divisor). Someone who
 *                 joined or left during the month gets per-day rate × days employed (at most the full salary).
 * Daily wage:     gross = per_day_rate × payable days; no separate attendance deduction (already in payable days).
 * Overtime:       hours × overtime_rate_normal, holiday/weekly-off hours × overtime_rate_holiday (falls back to normal).
 * Divisor:        setting payroll_day_divisor = "calendar" (days in month, default) or a fixed number like 30 / 26.
 */
final class PayslipCalculator
{
    /**
     * @param  array<string, mixed>  $summary  PeriodSummary::toArray() for the employee
     * @param  list<Advance>  $advances  active advances
     * @param  int  $daysNotEmployed  days of the pay month before joining or after leaving
     * @return array{lines: list<array{label: string, type: string, amount: int, code: string, taxable?: bool}>, gross: int, deductions: int, net: int, per_day: int}
     */
    public function calculate(
        SalaryStructure $structure,
        array $summary,
        bool $isDailyWage,
        int $daysInMonth,
        string | int $divisor,
        int $overtimeNormalMin,
        int $overtimeHolidayMin,
        array $advances,
        int $daysNotEmployed = 0,
    ): array {
        $lines = [];
        // code: what the line is, for tax / statutory rules and reports. taxable: false for exempt allowances.
        $earn = function (string $label, int $amount, string $code, bool $taxable = true) use (&$lines) {
            if ($amount !== 0) {
                $lines[] = ['label' => $label, 'type' => 'earning', 'amount' => $amount, 'code' => $code, 'taxable' => $taxable];
            }
        };
        $deduct = function (string $label, int $amount, string $code) use (&$lines) {
            if ($amount !== 0) {
                $lines[] = ['label' => $label, 'type' => 'deduction', 'amount' => $amount, 'code' => $code];
            }
        };

        if ($isDailyWage) {
            $perDay = (int) $structure->per_day_rate;
            $earn(sprintf('Wages (%s days × Rs %s)', $summary['payableDays'], number_format($perDay)), (int) round($perDay * (float) $summary['payableDays']), 'wages');
        } else {
            $earn('Basic salary', (int) $structure->basic, 'basic');
            foreach ($structure->allowances ?? [] as $a) {
                $earn((string) $a['name'], (int) $a['amount'], 'allowance', (bool) ($a['taxable'] ?? true));
            }
            $monthly = (int) $structure->basic + $structure->allowancesTotal();
            $perDay = self::perDayRate($monthly, $daysInMonth, $divisor);
            if ($daysNotEmployed > 0) {
                $employedPay = min($monthly, $perDay * max(0, $daysInMonth - $daysNotEmployed));
                $deduct(sprintf('Not employed (%d days before joining or after leaving)', $daysNotEmployed), $monthly - $employedPay, 'not_employed');
            }
            $deductionDays = (float) $summary['deductionDays'];
            if ($deductionDays > 0) {
                $deduct(sprintf('Attendance deduction (%s days × Rs %s)', rtrim(rtrim(number_format($deductionDays, 2), '0'), '.'), number_format($perDay)),
                    (int) round($deductionDays * $perDay), 'attendance');
            }
        }

        $normalRate = (int) $structure->overtime_rate_normal;
        $holidayRate = (int) ($structure->overtime_rate_holiday ?: $normalRate);
        $earn(sprintf('Overtime (%.1f h)', $overtimeNormalMin / 60), (int) round($overtimeNormalMin / 60 * $normalRate), 'overtime');
        $earn(sprintf('Holiday overtime (%.1f h)', $overtimeHolidayMin / 60), (int) round($overtimeHolidayMin / 60 * $holidayRate), 'overtime');

        foreach ($structure->deductions_fixed ?? [] as $d) {
            $deduct((string) $d['name'], (int) $d['amount'], 'fixed');
        }
        foreach ($advances as $advance) {
            $deduct("Advance recovery (issued {$advance->issued_on->format('d M Y')})", $advance->instalment(), 'advance');
        }

        $gross = array_sum(array_map(fn ($l) => $l['type'] === 'earning' ? $l['amount'] : 0, $lines));
        $deductions = array_sum(array_map(fn ($l) => $l['type'] === 'deduction' ? $l['amount'] : 0, $lines));

        return ['lines' => $lines, 'gross' => $gross, 'deductions' => $deductions, 'net' => $gross - $deductions, 'per_day' => $perDay];
    }

    /** Monthly salary ÷ the Payroll rules divisor ("calendar" = days in the pay month). Used by payroll and final settlements. */
    public static function perDayRate(int $monthly, int $daysInMonth, string | int $divisor): int
    {
        $days = $divisor === 'calendar' ? $daysInMonth : max(1, (int) $divisor);

        return (int) round($monthly / $days);
    }
}
