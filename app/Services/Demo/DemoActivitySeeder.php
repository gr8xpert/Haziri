<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Corrections\DecideCorrection;
use App\Actions\Corrections\SubmitCorrection;
use App\Actions\Leave\DecideLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Actions\Punches\CreateManualPunches;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Enums\LeaveDayPart;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeLeaveRule;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * Requests that go through the real approval steps (so balances, attendance and notifications follow): leave taken,
 * waiting and refused; corrections and OD / WFH; hand-entered times; a special leave rule and a balance adjustment.
 */
final class DemoActivitySeeder
{
    use DemoSteps;

    public function __construct(
        private readonly SubmitLeaveRequest $submitLeave,
        private readonly DecideLeaveRequest $decideLeave,
        private readonly SubmitCorrection $submitCorrection,
        private readonly DecideCorrection $decideCorrection,
        private readonly CreateManualPunches $manualPunches,
        private readonly LeaveYear $leaveYear,
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Closure(string): void  $say
     */
    public function run(Collection $employees, CarbonImmutable $today, Closure $say): void
    {
        $this->say = $say;
        $owner = User::role(Role::Owner->value)->firstOrFail();
        $this->leave($employees, $today, $owner);
        $this->corrections($employees, $today, $owner);
        $this->manualEntries($employees, $today, $owner);
    }

    /** @param Collection<int, Employee> $employees */
    private function leave(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        $type = fn (string $code) => LeaveType::where('code', $code)->firstOrFail();
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();
        $year = $this->leaveYear->current();

        // [employee, type, from, days, reason, outcome: approve | reject | null (waiting)]
        $requests = [
            [5, 'CL', self::workday($lastMonth->addDays(9)), 1, 'Family event', true],
            [2, 'SL', self::workday($lastMonth->addDays(19)), 2, 'Fever', true],
            [10, 'AL', self::workday($today->subDays(12)), 3, 'Trip to Murree with family', true],
            [7, 'CL', self::workday($today->subDays(6)), 1, 'Personal work', false],
            [4, 'SL', self::workday($today->addDays(3)), 1, 'Doctor appointment', null],
            [12, 'CL', self::workday($today->addDay()), 1, 'Brother\'s nikah', null],
            [8, 'AL', self::workday($today->addDays(8)), 4, 'Umrah', null],
        ];
        foreach ($requests as [$i, $code, $from, $days, $reason, $approve]) {
            $employee = $employees[$i];
            if (LeaveRequest::where('employee_id', $employee->id)->whereDate('from_date', $from->format('Y-m-d'))->exists()) {
                continue;
            }
            $this->attempt("{$code} leave for {$employee->fullName()}", function () use ($employee, $type, $code, $from, $days, $reason, $approve, $owner) {
                $to = $from;
                for ($d = 1; $d < $days; $d++) {
                    $to = self::workday($to->addDay());
                }
                $request = ($this->submitLeave)($employee, $type($code), $from->format('Y-m-d'), $to->format('Y-m-d'), $owner, LeaveDayPart::Full, $reason);
                // Each approval step in turn (manager, then HR), decided by the owner.
                for ($step = 0; $approve !== null && $step < 3 && $request->status === RequestStatus::Pending; $step++) {
                    $request = ($this->decideLeave)($request, $owner, $approve, $approve ? null : __('Month-end closing: please take it next week.'));
                }
            });
        }

        // Hamza was promised 20 annual days in his offer letter; Kamran gets 2 casual days back for working Sundays.
        $al = $type('AL');
        EmployeeLeaveRule::firstOrCreate(['employee_id' => $employees[6]->id, 'leave_type_id' => $al->id],
            ['days_per_year' => 20, 'carry_forward_max' => 10, 'reason' => 'Agreed in his offer letter', 'created_by' => $owner->id]);
        $hamza = LeaveBalance::where(['employee_id' => $employees[6]->id, 'leave_type_id' => $al->id, 'year' => $year])->first();
        $hamza?->update(['accrued' => $this->balances->accrued($employees[6], $al, $year)]);

        $cl = $type('CL');
        if (! LeaveAdjustment::where('employee_id', $employees[10]->id)->exists()) {
            LeaveAdjustment::create(['employee_id' => $employees[10]->id, 'leave_type_id' => $cl->id, 'year' => $year, 'days' => 2,
                'reason' => 'Worked two Sundays during the audit', 'created_by' => $owner->id]);
            LeaveBalance::where(['employee_id' => $employees[10]->id, 'leave_type_id' => $cl->id, 'year' => $year])->increment('adjusted', 2);
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function corrections(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        // [employee, date, type, payload, reason, outcome]
        $corrections = [
            [6, self::workday($today->subDays(2), -1), CorrectionType::AddPunch, ['time' => '18:10', 'direction' => 'out'], 'Forgot to punch out', null],
            [9, self::workday($today->subDay(), -1), CorrectionType::MarkOd, [], 'Client visit at Packages Ltd all day', null],
            [3, self::workday($today->addDays(2)), CorrectionType::MarkWfh, [], 'Waiting at home for the plumber', null],
            [11, self::workday($today->subDays(8), -1), CorrectionType::MarkWfh, [], 'Car broke down, worked from home', true],
            [14, self::workday($today->subDays(5), -1), CorrectionType::AddPunch, ['time' => '09:02', 'direction' => 'in'], 'Machine did not read my finger', true],
            [13, self::workday($today->subDays(4), -1), CorrectionType::ChangeStatus, ['status' => AttendanceStatus::Present->value], 'I was at the Karachi office that day', false],
        ];
        foreach ($corrections as [$i, $date, $type, $payload, $reason, $approve]) {
            $employee = $employees[$i];
            if (AttendanceCorrection::where('employee_id', $employee->id)->whereDate('date', $date->format('Y-m-d'))->exists()) {
                continue;
            }
            $this->attempt("correction for {$employee->fullName()}", function () use ($employee, $date, $type, $payload, $reason, $approve, $owner) {
                $correction = ($this->submitCorrection)($employee, $date->format('Y-m-d'), $type, $payload, $reason, $owner);
                for ($step = 0; $approve !== null && $step < 3 && $correction->status === RequestStatus::Pending; $step++) {
                    $correction = ($this->decideCorrection)($correction, $owner, $approve, $approve ? null : __('The Karachi machine shows no punch that day.'));
                }
            });
        }
    }

    /**
     * The Lahore machine was down one morning: reception typed in two people's times.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function manualEntries(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        $day = self::workday($today->subDays(3), -1)->format('Y-m-d');
        foreach ([[1, '08:55', '18:05'], [16, '09:01', null]] as [$i, $in, $out]) {
            $this->attempt("manual entry for {$employees[$i]->fullName()}", fn () => ($this->manualPunches)(
                $employees[$i], $day, $in, $out, 'Machine was down', $owner, 'Times from the gate register'));
        }
    }
}
