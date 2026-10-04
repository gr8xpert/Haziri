<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceExemption;
use App\Models\AttendanceMarker;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Leave\LeaveMonthSummary;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * One employee's pay month, day by day, in the layout HR prints (client's legacy "Monthly Attendance Report"):
 * scheduled day and times, actual in / out (manual entries marked), status in words, duration, overtime, remarks
 * (holiday name, leave, manual-entry reason…), totals and the leave balance movement. Shared by the screen, the
 * single PDF and the bulk PDF.
 */
final class MonthlyAttendanceStatement
{
    public function __construct(
        private readonly PayCycle $cycle,
        private readonly LeaveMonthSummary $leave,
    ) {}

    /**
     * @return array{employee: array<string, ?string>, period: array{label: string, from: string, to: string}, rows: list<array<string, mixed>>,
     *               totals: array<string, int|string>, leave: list<array<string, mixed>>}
     */
    public function build(Employee $employee, int $year, int $month): array
    {
        return $this->buildMany(collect([$employee]), $year, $month)[0];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>> one statement per employee, in the given order
     */
    public function buildMany(Collection $employees, int $year, int $month): array
    {
        [$start, $end] = $this->cycle->range($year, $month);
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $ids = $employees->pluck('id');
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $employees = (new EloquentCollection($employees->all()))->loadMissing(['designation', 'department', 'grade', 'branch']);

        $days = AttendanceDay::query()->whereIn('employee_id', $ids)->whereBetween('date', [$from, $to])->get()
            ->groupBy('employee_id')->map(fn ($list) => $list->keyBy(fn (AttendanceDay $d) => $d->date->format('Y-m-d')));
        $shifts = Shift::withTrashed()->get()->keyBy('id');
        $overrides = ShiftOverride::query()->whereIn('id', $days->flatten()->pluck('override_id')->filter()->unique())->get()->keyBy('id');
        $holidays = Holiday::query()->whereIn('id', $days->flatten()->pluck('holiday_id')->filter()->unique())->pluck('name', 'id');
        $leaves = LeaveRequest::query()->with('leaveType')->whereIn('id', $days->flatten()->pluck('leave_request_id')->filter()->unique())->get()->keyBy('id');
        $manual = Punch::query()->whereIn('employee_id', $ids)->where('is_manual', true)->whereBetween('local_date', [$from, $to])->get()
            ->groupBy(fn (Punch $p) => $p->employee_id . '|' . $p->local_date->format('Y-m-d'));
        $markers = AttendanceMarker::query()->whereIn('employee_id', $ids)->whereBetween('date', [$from, $to])->get()
            ->keyBy(fn ($m) => $m->employee_id . '|' . $m->date->format('Y-m-d'));
        $exemptions = AttendanceExemption::query()->whereBetween('date', [$from, $to])->get();
        $leaveTypes = LeaveType::query()->where('is_active', true)->where('is_paid', true)->orderBy('id')->get();
        $leaveByType = $leaveTypes->mapWithKeys(fn (LeaveType $t) => [$t->id => $this->leave->forEmployees($employees, $t, $start, $end)]);

        $statements = [];
        foreach ($employees as $e) {
            $own = $days->get($e->id, collect());
            $rows = [];
            $totals = ['on_time' => 0, 'late' => 0, 'half' => 0, 'leave' => 0, 'absent' => 0, 'off' => 0, 'holiday' => 0, 'other' => 0,
                'span' => 0, 'worked' => 0, 'overtime' => 0];
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $key = $date->format('Y-m-d');
                $d = $own->get($key);
                $punches = $manual->get("{$e->id}|{$key}", collect());
                [$status, $tone, $bucket] = self::status($d, $leaves);
                $totals[$bucket] = ($totals[$bucket] ?? 0) + ($bucket !== 'none' ? 1 : 0);
                $span = $d?->first_in && $d?->last_out ? intdiv($d->last_out->getTimestamp() - $d->first_in->getTimestamp(), 60) : 0;
                $totals['span'] += $span;
                $totals['worked'] += (int) ($d->worked_min ?? 0);
                $totals['overtime'] += (int) ($d->overtime_min ?? 0);
                $shift = $d?->shift_id ? $shifts->get($d->shift_id) : null;
                $override = $d?->override_id ? $overrides->get($d->override_id) : null;
                $isOff = $d?->status === AttendanceStatus::WeeklyOff;
                $schedIn = $isOff || $shift === null ? null : substr((string) ($override?->start_time ?? $shift->start_time), 0, 5);
                $schedOut = $isOff || $shift === null ? null : substr((string) ($override?->end_time ?? $shift->end_time), 0, 5);

                $rows[] = [
                    'date' => $date->format('d-m-y'),
                    'weekday' => $date->format('l'),
                    'day_type' => $isOff ? 'Off Day' : (self::isHalfDay($d, $shift) ? 'Half Day' : ($d === null && $shift === null ? '' : 'Full Day')),
                    'sched_in' => $schedIn,
                    'sched_out' => $schedOut,
                    'in' => $d?->first_in?->setTimezone($tz)->format('H:i'),
                    'out' => $d?->last_out?->setTimezone($tz)->format('H:i'),
                    'in_manual' => $d?->first_in !== null && $punches->contains(fn (Punch $p) => $p->punched_at->equalTo($d->first_in)),
                    'out_manual' => $d?->last_out !== null && $punches->contains(fn (Punch $p) => $p->punched_at->equalTo($d->last_out)),
                    'status' => $status,
                    'tone' => $tone,
                    'duration' => self::hm($span),
                    'overtime' => self::hm((int) ($d->overtime_min ?? 0)),
                    'remarks' => self::remarks($e, $key, $d, $holidays, $leaves, $punches, $markers, $exemptions),
                ];
            }

            $statements[] = [
                'employee' => [
                    'code' => $e->code, 'name' => $e->fullName(), 'designation' => $e->designation?->name, 'department' => $e->department?->name,
                    'grade' => $e->grade?->name, 'branch' => $e->branch?->name, 'joined' => $e->joined_on?->format('d-M-Y'), 'payroll_id' => $e->payroll_id,
                ],
                'period' => ['label' => $this->cycle->label($year, $month), 'from' => $start->format('d-M-Y'), 'to' => $end->format('d-M-Y')],
                'rows' => $rows,
                'totals' => $totals + ['span_hm' => self::hm($totals['span']), 'worked_hm' => self::hm($totals['worked']), 'overtime_hm' => self::hm($totals['overtime'])],
                'leave' => $leaveTypes->map(fn (LeaveType $t) => ['type' => $t->name] + $leaveByType[$t->id][$e->id])
                    ->filter(fn (array $l) => $l['previous'] != 0 || $l['allotted'] != 0 || $l['availed'] != 0 || $l['adjusted'] != 0 || $l['credit'] != 0)
                    ->values()->all(),
            ];
        }

        return $statements;
    }

    /**
     * @param  Collection<int, LeaveRequest>  $leaves
     * @return array{0: string, 1: string, 2: string} label, tone (success|warning|danger|info|gray|primary), totals bucket
     */
    private static function status(?AttendanceDay $d, Collection $leaves): array
    {
        $flags = $d->flags ?? [];
        $leaveType = $d?->leave_request_id ? $leaves->get($d->leave_request_id)?->leaveType?->name : null;

        return match ($d?->status) {
            AttendanceStatus::Present => in_array('missing_out', $flags, true) && ! in_array('auto_closed', $flags, true)
                ? ['Present (No out)', 'warning', in_array('late', $flags, true) ? 'late' : 'on_time']
                : (in_array('late', $flags, true) ? ['Present (Late)', 'warning', 'late'] : ['Present (On Time)', 'success', 'on_time']),
            AttendanceStatus::HalfDay => ['Half Day', 'warning', 'half'],
            AttendanceStatus::HalfDayLeave => ['Half-day Leave', 'primary', 'half'],
            AttendanceStatus::LeavePaid => [$leaveType ? "Leave ({$leaveType})" : 'Leave', 'primary', 'leave'],
            AttendanceStatus::LeaveUnpaid => ['Leave Without Pay', 'primary', 'leave'],
            AttendanceStatus::Absent => ['Absent', 'danger', 'absent'],
            AttendanceStatus::WeeklyOff => ['Off Day', 'gray', 'off'],
            AttendanceStatus::Holiday => ['Holiday', 'info', 'holiday'],
            AttendanceStatus::OfficialDuty => ['Official Duty', 'success', 'other'],
            AttendanceStatus::Wfh => ['Work from Home', 'success', 'other'],
            AttendanceStatus::Exempted => ['Exempted', 'info', 'other'],
            AttendanceStatus::NotJoined => ['Not joined yet', 'gray', 'none'],
            AttendanceStatus::Left => ['Left', 'gray', 'none'],
            default => ['—', 'gray', 'none'],
        };
    }

    private static function isHalfDay(?AttendanceDay $d, ?Shift $shift): bool
    {
        if ($d === null || $shift === null || $d->expected_min <= 0) {
            return false;
        }
        $start = CarbonImmutable::parse((string) $shift->start_time);
        $end = CarbonImmutable::parse((string) $shift->end_time);
        $full = intdiv((($end->getTimestamp() - $start->getTimestamp()) + 86400) % 86400, 60) - (int) $shift->break_min;

        return $full > 0 && $d->expected_min < $full * 0.75;
    }

    /**
     * Why the day looks the way it does: holiday name, leave, OD / WFH note, exemption, manual-entry reasons, status override.
     *
     * @param  Collection<int, Punch>  $punches  manual punches of the day
     */
    private static function remarks(Employee $e, string $date, ?AttendanceDay $d, Collection $holidays, Collection $leaves, Collection $punches,
        Collection $markers, Collection $exemptions): ?string
    {
        $notes = [];
        if (in_array('worked_on_leave', $d->flags ?? [], true)) {
            $notes[] = __('Worked on a leave day: cancel the leave?');
        }
        if ($d?->holiday_id) {
            $notes[] = $holidays->get($d->holiday_id) . (in_array('unpaid_holiday', $d->flags ?? [], true) ? ' (' . __('unpaid') . ')' : '');
        }
        if ($d?->leave_request_id && ($reason = $leaves->get($d->leave_request_id)?->reason)) {
            $notes[] = $reason;
        }
        if ($marker = $markers->get("{$e->id}|{$date}")) {
            $notes[] = $marker->reason;
        }
        if (in_array('exempted', $d->flags ?? [], true)) {
            $notes[] = $exemptions->first(fn ($x) => $x->date->format('Y-m-d') === $date)?->reason;
        }
        foreach ($punches->pluck('reason')->filter()->unique() as $reason) {
            $notes[] = $reason;
        }
        if (! empty($d?->manual_override['reason'])) {
            $notes[] = $d->manual_override['reason'];
        }

        return implode(' · ', array_values(array_unique(array_filter($notes)))) ?: null;
    }

    private static function hm(int $minutes): string
    {
        return $minutes <= 0 ? '' : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
