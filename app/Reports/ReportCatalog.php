<?php

declare(strict_types=1);

namespace App\Reports;

use App\Filament\Tenant\Pages\Reports;

/**
 * The reports HR sees, in five groups with a one-line "what it shows / when to use it". The Reports page lists
 * them; each report page shows its line at the top. Pages not listed here were merged into another report and only
 * redirect (old links keep working).
 */
final class ReportCatalog
{
    /** @return array<string, array<class-string, string>> group => [page class => description] */
    public static function groups(): array
    {
        return [
            __('Daily') => [
                Reports\DailyMuster::class => __('Everyone on one day: in, out, late minutes and status. Use it each morning or to check a past day.'),
                Reports\YesterdayToday::class => __('Yesterday\'s last out next to today\'s first in, by department: who stayed late last night and who is in this morning.'),
                Reports\MissingPunches::class => __('Days in a date range with a missing in or out punch, to fix before the month is locked.'),
                Reports\LateRegister::class => __('Every late arrival or early leaving in a date range, with a running count per person.'),
                Reports\AbsentRegister::class => __('Absences in a date range, with how many days in a row.'),
            ],
            __('Monthly') => [
                Reports\MonthlyAttendance::class => __('One detailed page per employee for a month (the layout HR prints and signs). PDF for one person, everyone, or separate files.'),
                Reports\MonthlySheet::class => __('All staff on a few pages: one line per person with a code for each day and the totals.'),
                Reports\MonthSummary::class => __('Totals per employee, department or branch for a month: present, late, absent, leave, overtime, deductions, payable days.'),
                Reports\Overtime::class => __('Overtime per employee and day in a date range.'),
                Reports\PunctualityScorecard::class => __('A 0–100 punctuality score and rank per employee for a date range, with the change from the period before.'),
            ],
            __('Leave') => [
                Reports\LeaveRegister::class => __('Every leave request in a date range with type, days and status.'),
                Reports\LeaveBalanceSheet::class => __('Each employee\'s leave balance for a leave year: opening, earned, used, remaining.'),
                Reports\MonthlyLeaveSummary::class => __('One leave type for a month: balance brought forward, earned, taken, charged for lates / half days, without pay, closing balance.'),
            ],
            __('People & audit') => [
                Reports\EmployeeInformation::class => __('The employee list with personal, job and contact details, for printing or Excel.'),
                Reports\ManualEntryAudit::class => __('Every time entered or removed by hand: who did it, when and why.'),
                Reports\Exemptions::class => __('Days excused company-wide (strike, rain) and who they covered.'),
                Reports\VisitorLog::class => __('Visitors in a date range: who they met, in and out times.'),
                Reports\DeviceSync::class => __('Punches per day from each attendance machine and the days with none, to spot machines that stopped sending.'),
            ],
            __('Payroll') => [
                Reports\PayrollInput::class => __('The attendance numbers a payroll run uses, per employee, for a locked month.'),
                Reports\SalarySheet::class => __('Salary per employee for a payroll run: earnings, deductions, net.'),
                Reports\TaxRegister::class => __('Income tax deducted per employee.'),
                Reports\StatutoryChallan::class => __('EOBI and social security amounts to deposit.'),
                Reports\PfStatement::class => __('Provident fund contributions and balances.'),
                Reports\GratuityLiability::class => __('Gratuity owed to each employee if they left on the chosen date (for provisioning).'),
            ],
        ];
    }

    public static function description(string $page): ?string
    {
        foreach (self::groups() as $reports) {
            if (isset($reports[$page])) {
                return $reports[$page];
            }
        }

        return null;
    }
}
