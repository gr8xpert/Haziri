<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceDay;
use App\Models\Device;
use App\Models\Employee;
use App\Models\UnmappedPin;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard counters for today (spec §9 phase 1). */
class TodayStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('attendance.view_all');
    }

    protected function getStats(): array
    {
        $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');
        $days = AttendanceDay::query()->whereDate('date', $today)->get();
        $active = Employee::query()->where('status', '!=', EmployeeStatus::Left)->where('joined_on', '<=', $today)->count();

        $present = $days->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh])->count();
        $late = $days->filter(fn (AttendanceDay $d) => in_array('late', $d->flags ?? [], true))->count();
        $onLeave = $days->whereIn('status', [AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid, AttendanceStatus::HalfDayLeave])->count();
        $off = $days->whereIn('status', [AttendanceStatus::Holiday, AttendanceStatus::WeeklyOff])->count();
        $notIn = max(0, $active - $present - $onLeave - $off);

        $unmapped = UnmappedPin::whereNull('resolved_employee_id')->count();
        $offline = Device::where('status', 'offline')->count();

        return [
            Stat::make(__('Present'), $present)->description(__(':n active employees', ['n' => $active]))->color('success'),
            Stat::make(__('Late'), $late)->color($late ? 'warning' : 'gray'),
            Stat::make(__('Not in yet / absent'), $notIn)->color($notIn ? 'danger' : 'gray'),
            Stat::make(__('On leave'), $onLeave),
            Stat::make(__('Unmapped PINs'), $unmapped)->color($unmapped ? 'warning' : 'gray')
                ->description($unmapped ? __('Map them so punches count') : __('All mapped')),
            Stat::make(__('Devices offline'), $offline)->color($offline ? 'danger' : 'gray'),
        ];
    }
}
