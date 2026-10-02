<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Filament\Tenant\Pages\Dashboard;
use App\Models\AttendanceDay;
use App\Models\Device;
use App\Models\Employee;
use App\Models\UnmappedPin;
use App\Support\BranchAccess;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard counters for today (spec §9 phase 1). */
class TodayStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('attendance.view_all');
    }

    protected function getStats(): array
    {
        $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');
        $branch = Dashboard::selectedBranch($this->pageFilters);
        $branches = $branch !== null ? [$branch] : BranchAccess::ids(); // null = all branches
        $inBranch = fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('branch_id', $branches));
        $days = AttendanceDay::query()->whereDate('date', $today)->when($branch !== null, $inBranch)->get();
        $active = Employee::query()->where('status', '!=', EmployeeStatus::Left)->where('joined_on', '<=', $today)
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch))->count();

        $present = $days->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh])->count();
        $late = $days->filter(fn (AttendanceDay $d) => in_array('late', $d->flags ?? [], true))->count();
        $onLeave = $days->whereIn('status', [AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid, AttendanceStatus::HalfDayLeave])->count();
        $off = $days->whereIn('status', [AttendanceStatus::Holiday, AttendanceStatus::WeeklyOff])->count();
        $notIn = max(0, $active - $present - $onLeave - $off);

        $unmapped = UnmappedPin::whereNull('resolved_employee_id')
            ->when($branches !== null, fn ($q) => $q->whereHas('device', fn ($d) => $d->whereIn('branch_id', $branches)))->count();
        $offline = Device::where('status', 'offline')->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->count();

        return [
            Stat::make(__('Present'), $present)->icon(Heroicon::OutlinedCheckCircle)
                ->description(__('of :n active employees', ['n' => $active]))->color('success'),
            Stat::make(__('Late'), $late)->icon(Heroicon::OutlinedClock)
                ->description($late ? __('Arrived after grace time') : __('Everyone on time'))->color($late ? 'warning' : 'gray'),
            Stat::make(__('Not in yet / absent'), $notIn)->icon(Heroicon::OutlinedUserMinus)
                ->description($notIn ? __('No punch recorded today') : __('Everyone accounted for'))->color($notIn ? 'danger' : 'gray'),
            Stat::make(__('On leave'), $onLeave)->icon(Heroicon::OutlinedCalendarDays)
                ->description(__('Approved leave today'))->color('gray'),
            Stat::make(__('Unmapped PINs'), $unmapped)->icon(Heroicon::OutlinedFingerPrint)
                ->description($unmapped ? __('Map them so punches count') : __('All mapped'))->color($unmapped ? 'warning' : 'gray'),
            Stat::make(__('Devices offline'), $offline)->icon(Heroicon::OutlinedSignalSlash)
                ->description($offline ? __('Check power and network') : __('All devices online'))->color($offline ? 'danger' : 'gray'),
        ];
    }
}
