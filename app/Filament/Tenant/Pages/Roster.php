<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Enums\EmployeeStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Weekly roster planner (spec §9 phase 4): employees × days → shift. A cell is stored as a one-day
 * ShiftAssignment (effective_from = effective_to = date), which beats longer assignments for that date.
 * Empty cell = the employee's normal shift.
 */
class Roster extends Page
{
    protected string $view = 'filament.tenant.pages.roster';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 5;

    public string $weekStart = '';

    public ?int $branchId = null;

    /** @var array<int, array<string, int|string|null>> employee_id => [date => shift_id] */
    public array $grid = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('shifts.manage');
    }

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now(tenant('timezone'))->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d');
        $this->load();
    }

    public function updatedBranchId(): void
    {
        $this->load();
    }

    public function shiftWeek(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($weeks)->format('Y-m-d');
        $this->load();
    }

    /** @return list<string> */
    public function getDates(): array
    {
        return array_map(fn ($d) => $d->format('Y-m-d'), iterator_to_array(CarbonPeriod::create($this->weekStart, 7)));
    }

    /** @return \Illuminate\Support\Collection<int, Employee> */
    public function getEmployees(): \Illuminate\Support\Collection
    {
        return Employee::query()->where('status', '!=', EmployeeStatus::Left)
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))->orderBy('code')->get();
    }

    /** @return array<int, string> */
    public function getShiftOptions(): array
    {
        return Shift::query()->orderBy('start_time')->get()->mapWithKeys(fn (Shift $s) => [$s->id => $s->code])->all();
    }

    /** @return array<int, string> */
    public function getBranchOptions(): array
    {
        return Branch::orderBy('name')->pluck('name', 'id')->all();
    }

    public function load(): void
    {
        $dates = $this->getDates();
        $cells = $this->dayAssignments($dates[0], end($dates))->groupBy('employee_id');
        $this->grid = [];
        foreach ($this->getEmployees() as $e) {
            foreach ($dates as $d) {
                $this->grid[$e->id][$d] = $cells->get($e->id)?->firstWhere(fn ($a) => $a->effective_from->format('Y-m-d') === $d)?->shift_id;
            }
        }
    }

    public function save(): void
    {
        $dates = $this->getDates();
        DB::transaction(function () use ($dates) {
            $existing = $this->dayAssignments($dates[0], end($dates))->keyBy(fn ($a) => $a->employee_id . '|' . $a->effective_from->format('Y-m-d'));
            foreach ($this->grid as $employeeId => $days) {
                foreach ($days as $date => $shiftId) {
                    $key = "{$employeeId}|{$date}";
                    $current = $existing->get($key);
                    if (blank($shiftId)) {
                        $current?->delete();
                    } elseif ($current === null) {
                        ShiftAssignment::create(['employee_id' => $employeeId, 'shift_id' => (int) $shiftId, 'effective_from' => $date, 'effective_to' => $date]);
                    } elseif ($current->shift_id !== (int) $shiftId) {
                        $current->update(['shift_id' => (int) $shiftId]);
                    }
                }
            }
        });
        Notification::make()->success()->title(__('Roster published'))->send();
        $this->load();
    }

    public function copyPreviousWeek(): void
    {
        $prev = CarbonImmutable::parse($this->weekStart)->subWeek();
        foreach ($this->dayAssignments($prev->format('Y-m-d'), $prev->addDays(6)->format('Y-m-d')) as $a) {
            if (isset($this->grid[$a->employee_id])) {
                $this->grid[$a->employee_id][$a->effective_from->addWeek()->format('Y-m-d')] = $a->shift_id;
            }
        }
        Notification::make()->info()->title(__('Copied — review and publish'))->send();
    }

    private function dayAssignments(string $from, string $to): \Illuminate\Support\Collection
    {
        return ShiftAssignment::query()->whereColumn('effective_from', 'effective_to')
            ->whereBetween('effective_from', [$from, $to])->get();
    }
}
