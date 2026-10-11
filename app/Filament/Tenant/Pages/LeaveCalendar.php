<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\HolidayType;
use App\Enums\RequestStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Models\AttendanceMarker;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** "Who's off this week" (spec §9 phase 2): approved leave, OD/WFH and holidays per day. */
class LeaveCalendar extends Page
{
    protected string $view = 'filament.tenant.pages.leave-calendar';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Who\'s off';

    public string $weekStart = '';

    public ?int $branchId = null;

    public ?int $departmentId = null;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('leave') && (bool) auth()->user()?->can('leave.request');
    }

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now(tenant('timezone'))->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d');
    }

    public function shift(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($weeks)->format('Y-m-d');
    }

    /** @return array<int, string> */
    public function getBranchOptions(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getDepartmentOptions(): array
    {
        return Department::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Managers without HR rights only see their own team (and themselves); the filters narrow it further. */
    public function seesWholeCompany(): bool
    {
        return (bool) auth()->user()?->can('leave.approve_final');
    }

    /** @return array<string, list<array{name: string, what: string, tone: string}>> date => entries */
    public function getDays(): array
    {
        $from = CarbonImmutable::parse($this->weekStart);
        $to = $from->addDays(6);
        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $days[$d->format('Y-m-d')] = [];
        }
        $employees = $this->employees()->get()->keyBy('id');

        $leaves = LeaveRequest::query()->with('leaveType')->where('status', RequestStatus::Approved)
            ->whereIn('employee_id', $employees->keys())
            ->where('from_date', '<=', $to->format('Y-m-d'))->where('to_date', '>=', $from->format('Y-m-d'))->get();
        foreach ($leaves as $l) {
            foreach (CarbonPeriod::create(max($l->from_date->format('Y-m-d'), $from->format('Y-m-d')), min($l->to_date->format('Y-m-d'), $to->format('Y-m-d'))) as $d) {
                $days[$d->format('Y-m-d')][] = ['name' => $employees->get($l->employee_id)?->fullName() ?? '?',
                    'what' => ($l->leaveType?->name ?? __('Leave')) . ($l->day_part->isHalf() ? ' (' . __('half day') . ')' : ''), 'tone' => 'warning'];
            }
        }
        $markers = AttendanceMarker::query()->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get();
        foreach ($markers as $m) {
            $days[$m->date->format('Y-m-d')][] = ['name' => $employees->get($m->employee_id)?->fullName() ?? '?',
                'what' => $m->status->getLabel(), 'tone' => 'info'];
        }
        $holidays = Holiday::query()->with('branch')->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->when($this->branchId, fn ($q, $id) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id)))->get();
        foreach ($holidays as $h) {
            if ($h->type === HolidayType::Optional) {
                // An optional holiday is only a day off for the people taking it.
                foreach ($h->optIns()->whereIn('employee_id', $employees->keys())->pluck('employee_id') as $id) {
                    $days[$h->date->format('Y-m-d')][] = ['name' => $employees->get($id)?->fullName() ?? '?',
                        'what' => __('Optional holiday: :name', ['name' => $h->name]), 'tone' => 'success'];
                }

                continue;
            }
            $days[$h->date->format('Y-m-d')][] = ['name' => $h->name,
                'what' => $h->branch ? __('Holiday, :branch only', ['branch' => $h->branch->name]) : __('Holiday'), 'tone' => 'success'];
        }

        foreach ($this->weeklyOffs($employees, $from, $to) as $date => $offIds) {
            if ($offIds === []) {
                continue;
            }
            if (count($offIds) === $employees->filter(fn (Employee $e) => $this->isEmployed($e, $date))->count()) {
                $days[$date][] = ['name' => __('Everyone'), 'what' => AttendanceStatus::WeeklyOff->getLabel(), 'tone' => 'gray'];

                continue;
            }
            foreach ($offIds as $id) {
                $days[$date][] = ['name' => $employees->get($id)?->fullName() ?? '?', 'what' => AttendanceStatus::WeeklyOff->getLabel(), 'tone' => 'gray'];
            }
        }

        return $days;
    }

    /**
     * Who has their weekly off on each date, from their shift on that date (latest assignment, else the default shift)
     * and the shift's or branch's off days. Loaded in a few queries rather than resolving every employee-day.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, list<int>> date => employee ids
     */
    private function weeklyOffs(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $assignments = ShiftAssignment::query()->with('shift')->whereIn('employee_id', $employees->keys())
            ->where('effective_from', '<=', $to->format('Y-m-d'))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->format('Y-m-d')))
            ->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('employee_id');
        $default = Shift::query()->where('is_default', true)->first();
        $branchOffs = Branch::query()->pluck('weekly_off_days', 'id');

        $offs = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $date = $d->format('Y-m-d');
            $offs[$date] = [];
            foreach ($employees as $e) {
                if (! $this->isEmployed($e, $date)) {
                    continue;
                }
                $shift = ($assignments->get($e->id) ?? collect())->first(fn (ShiftAssignment $a) => $a->effective_from->format('Y-m-d') <= $date
                    && ($a->effective_to === null || $a->effective_to->format('Y-m-d') >= $date))?->shift ?? $default;
                if ($shift === null) {
                    continue;
                }
                $fallback = $e->branch_id !== null ? ($branchOffs->get($e->branch_id) ?? [0]) : [0];
                if (in_array($d->dayOfWeek, array_map('intval', $shift->weekly_off_days ?? $fallback), true)) {
                    $offs[$date][] = $e->id;
                }
            }
        }

        return $offs;
    }

    private function isEmployed(Employee $employee, string $date): bool
    {
        return ($employee->joined_on === null || $employee->joined_on->format('Y-m-d') <= $date)
            && ($employee->left_on === null || $employee->left_on->format('Y-m-d') >= $date);
    }

    /** People in view who have not left before this week. */
    private function employees(): Builder
    {
        $user = auth()->user();
        $weekStart = CarbonImmutable::parse($this->weekStart)->format('Y-m-d');

        return Employee::query()
            ->where(fn (Builder $q) => $q->where('status', '!=', EmployeeStatus::Left)->orWhere('left_on', '>=', $weekStart))
            ->when($this->branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($this->departmentId, fn ($q, $id) => $q->where('department_id', $id))
            ->when(! $this->seesWholeCompany(), fn ($q) => $q->where(fn ($w) => $w->where('manager_employee_id', $user?->employee_id ?? 0)
                ->orWhereKey($user?->employee_id ?? 0)));
    }
}
