<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\AttendanceFlag;
use App\Enums\Role;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * "Tell managers about late arrivals" (setting notify_employee_late): when a recent day first turns late,
 * the employee's manager is told (HR, admins and the owner when there is no manager). Once per employee per day.
 */
final class LateArrivalAlert
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly TenantSettings $settings,
    ) {}

    public function __invoke(Employee $employee, AttendanceDay $day, bool $wasLate, CarbonImmutable $now): int
    {
        if ($wasLate || ! $this->settings->get('notify_employee_late', false) || ! in_array(AttendanceFlag::Late->value, $day->flags ?? [], true)) {
            return 0;
        }

        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $date = $day->date->format('Y-m-d');
        if ($date < $now->setTimezone($tz)->subDay()->format('Y-m-d')) {
            return 0; // recomputing old days (imports, corrections) must not send stale alerts
        }

        $recipients = Recipients::managerOf($employee);
        if ($recipients->isEmpty()) {
            $recipients = Recipients::withRoles(Role::Owner, Role::Admin, Role::Hr);
        }

        return $this->notifier->send('employee_late', $recipients, [
            'employee' => $employee->fullName(),
            'time' => $day->first_in?->setTimezone($tz)->format('H:i') ?? '—',
            'minutes' => (int) $day->late_min,
        ], url('/admin/reports/daily-muster'), "late:{$employee->id}:{$date}");
    }
}
