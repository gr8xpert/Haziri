<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Advance;
use App\Models\SalaryStructure;

/**
 * Payroll-lite (spec §9 phase 3): structure + locked period numbers → earnings, deductions, net. Integer rupees.
 *
 * Monthly staff:  gross = basic + allowances; attendance deduction = deduction days × (gross ÷ divisor).
 * Daily wage:     gross = per_day_rate × payable days; no separate attendance deduction (already in payable days).
 * Overtime:       hours × overtime_rate_normal, holiday/weekly-off hours × overtime_rate_holiday (falls back to normal).
 * Divisor:        setting payroll_day_divisor = "calendar" (days in month, default) or a fixed number like 30 / 26.
 */
final class PayslipCalculator
{
    /**
     * @param  array<string, mixed>  $summary  PeriodSummary::toArray() for the employee
     * @param  list<Advance>  $advances  active advances
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
            $days = $divisor === 'calendar' ? $daysInMonth : max(1, (int) $divisor);
            $perDay = (int) round($monthly / $days);
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
}
