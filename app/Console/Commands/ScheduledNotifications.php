<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Announcements\BroadcastAnnouncement;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\UnmappedPin;
use App\Services\Attendance\ShiftResolver;
use App\Services\Attendance\WorkingDayCalendar;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\Recipients;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Time-based notifications for the current tenant (spec §11), run every 15 min via tenants:run.
 * Each alert carries a dedupe key, so running more often never double-sends.
 */
#[Signature('hazri:notify:scheduled {--now= : Override "now" (tenant local, for testing)}')]
#[Description('Send due time-based notifications (summary, absent, missing punch-out, HR reminders)')]
final class ScheduledNotifications extends Command
{
    public function handle(Notifier $notifier, TenantSettings $settings, ShiftResolver $shifts, WorkingDayCalendar $calendar, BroadcastAnnouncement $broadcast): int
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $now = $this->option('now') ? CarbonImmutable::parse($this->option('now'), $tz) : CarbonImmutable::now($tz);
        $today = $now->format('Y-m-d');
        $at = fn (string $key, string $default) => $now->format('H:i') >= (string) $settings->get($key, $default);
        $sent = 0;

        // Absent without leave → manager, at `absent_alert_at` (default 11:00).
        if ($at('absent_alert_at', '11:00')) {
            $sent += $this->absentAlerts($notifier, $calendar, $today, $now);
        }

        // Missing punch-out → employee, 2 h after their shift end.
        foreach (AttendanceDay::query()->with('employee')->whereDate('date', $today)->whereJsonContains('flags', 'missing_out')->get() as $day) {
            $shift = $shifts->resolve($day->employee, $today, $tz);
            if ($shift && $now->format('H:i') >= CarbonImmutable::parse("{$today} {$shift->endTime}", $tz)->addHours(2)->format('H:i') && ! $shift->crossesMidnight) {
                $sent += $notifier->send('missing_punch_out', [Recipient::fromEmployee($day->employee)],
                    ['name' => $day->employee->first_name, 'date' => $now->format('d M')], null, "missing_out:{$today}");
            }
        }

        // Daily summary → HR / admins, at `daily_summary_at` (default 18:00).
        if ($at('daily_summary_at', '18:00')) {
            $days = AttendanceDay::query()->whereDate('date', $today)->get();
            $sent += $notifier->send('daily_summary', Recipients::withRoles(Role::Owner, Role::Admin, Role::Hr), [
                'date' => $now->format('d M Y'),
                'present' => $days->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh])->count(),
                'late' => $days->filter(fn ($d) => in_array('late', $d->flags ?? [], true))->count(),
                'absent' => $days->where('status', AttendanceStatus::Absent)->count() + $days->whereNull('status')->count(),
                'on_leave' => $days->whereIn('status', [AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid, AttendanceStatus::HalfDayLeave])->count(),
                'missing_out' => $days->filter(fn ($d) => in_array('missing_out', $d->flags ?? [], true))->count(),
            ], url('/admin/reports/daily-muster'), "daily_summary:{$today}");
        }

        // Morning HR reminders (09:00): unmapped PINs, unconfirmed holidays, probation / contract endings, birthdays.
        if ($now->format('H:i') >= '09:00') {
            $sent += $this->morningReminders($notifier, $now);
        }

        // Announcements scheduled for later → their audience, once publish_at has passed.
        $sent += $broadcast->due();

        $this->info("{$sent} notification(s) queued.");

        return self::SUCCESS;
    }

    private function absentAlerts(Notifier $notifier, WorkingDayCalendar $calendar, string $today, CarbonImmutable $now): int
    {
        $present = AttendanceDay::query()->whereDate('date', $today)
            ->where(fn ($q) => $q->whereNotNull('first_in')->orWhereNotIn('status', [AttendanceStatus::Absent->value])->whereNotNull('status'))
            ->pluck('employee_id')->flip();

        $missing = Employee::query()->where('status', EmployeeStatus::Active)->where('joined_on', '<=', $today)
            ->whereNotNull('manager_employee_id')->get()
            ->filter(fn (Employee $e) => ! isset($present[$e->id]) && ! $calendar->isNonWorkingDay($e, $today));

        $sent = 0;
        foreach ($missing->groupBy('manager_employee_id') as $managerId => $team) {
            $sent += $notifier->send('employee_absent_no_leave', Recipients::managerOf($team->first()), [
                'count' => $team->count(),
                'names' => $team->map(fn (Employee $e) => $e->fullName())->implode(', '),
                'time' => $now->format('H:i'),
            ], null, "absent:{$today}");
        }

        return $sent;
    }

    private function morningReminders(Notifier $notifier, CarbonImmutable $now): int
    {
        $today = $now->format('Y-m-d');
        $hr = Recipients::withRoles(Role::Owner, Role::Admin, Role::Hr);
        $sent = 0;

        $unmapped = UnmappedPin::whereNull('resolved_employee_id')->count();
        if ($unmapped > 0) {
            $sent += $notifier->send('unmapped_pins_pending', $hr, ['count' => $unmapped], url('/admin/unmapped-pins'), "unmapped:{$today}");
        }

        // Provisional holidays (moon sighting) still unconfirmed 3 days before: remind HR to confirm or move them.
        $soon = Holiday::query()->where('needs_confirmation', true)
            ->whereBetween('date', [$today, $now->addDays(3)->format('Y-m-d')])->get();
        foreach ($soon as $h) {
            $sent += $notifier->send('holiday_unconfirmed', $hr, ['holiday' => $h->name, 'date' => $h->date->format('d M Y')],
                url('/admin/holidays'), "holiday_confirm:{$h->id}");
        }

        foreach (Employee::query()->where('status', '!=', EmployeeStatus::Left)->get() as $e) {
            if ($e->probation_ends_on?->format('Y-m-d') === $now->addDays(7)->format('Y-m-d')) {
                $sent += $notifier->send('probation_ending', $hr->concat(Recipients::managerOf($e)),
                    ['employee' => $e->fullName(), 'date' => $e->probation_ends_on->format('d M Y')], null, "probation:{$e->id}");
            }
            if ($e->contract_ends_on?->format('Y-m-d') === $now->addDays(30)->format('Y-m-d')) {
                $sent += $notifier->send('contract_ending', $hr,
                    ['employee' => $e->fullName(), 'date' => $e->contract_ends_on->format('d M Y')], null, "contract:{$e->id}");
            }
            if ($e->dob?->format('m-d') === $now->format('m-d')) {
                $sent += $notifier->send('birthday_today', $hr, ['employee' => $e->fullName()], null, "birthday:{$e->id}:{$now->year}");
            }
        }

        return $sent;
    }
}
