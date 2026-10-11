<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Rosters\GenerateMonthlyRoster;
use App\Enums\EmployeeStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Filament\Tenant\Resources\Shifts\ShiftResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Weekly roster planner (spec §9 phase 4): employees × days → shift. A cell is stored as a one-day
 * ShiftAssignment (effective_from = effective_to = date), which beats longer assignments for that date.
 * Empty cell = the employee's normal shift.
 */
class Roster extends Page
{
    protected string $view = 'filament.tenant.pages.roster';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 5;

    public string $weekStart = '';

    public ?int $branchId = null;

    /** @var array<int, array<string, int|string|null>> employee_id => [date => shift_id] */
    public array $grid = [];

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('rosters') && (bool) auth()->user()?->can('shifts.manage');
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

    /** @return Collection<int, Employee> */
    public function getEmployees(): Collection
    {
        return Employee::query()->where('status', '!=', EmployeeStatus::Left)
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))->orderBy('code')->get();
    }

    /** @return array<int, string> */
    public function getShiftOptions(): array
    {
        return Shift::query()->orderBy('start_time')->get()->mapWithKeys(fn (Shift $s) => [$s->id => $s->displayName()])->all();
    }

    /**
     * What a blank cell means for each employee and day: their standing shift (assignment or monthly roster),
     * else the company default.
     *
     * @return array<int, array<string, string>> employee_id => [date => shift name]
     */
    public function getUsualShifts(): array
    {
        $dates = $this->getDates();
        $employees = $this->getEmployees();
        $shifts = Shift::query()->get()->keyBy('id');
        $default = $shifts->firstWhere('is_default', true);
        $standing = ShiftAssignment::query()->whereIn('employee_id', $employees->pluck('id'))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereColumn('effective_to', '!=', 'effective_from'))
            ->where('effective_from', '<=', end($dates))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $dates[0]))
            ->orderByDesc('effective_from')->get()->groupBy('employee_id');

        $usual = [];
        foreach ($employees as $e) {
            foreach ($dates as $d) {
                $a = $standing->get($e->id)?->first(fn (ShiftAssignment $x) => $x->effective_from->format('Y-m-d') <= $d
                    && ($x->effective_to === null || $x->effective_to->format('Y-m-d') >= $d));
                $usual[$e->id][$d] = ($a ? $shifts->get($a->shift_id) : $default)?->displayName() ?? __('none');
            }
        }

        return $usual;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newShift')->label(__('New shift'))->icon(Heroicon::OutlinedPlus)->color('gray')
                ->modalDescription(__('A quick shift. Grace minutes, overtime and other rules can be set later under Shifts & Holidays → Shifts.'))
                ->schema(ShiftResource::quickCreateSchema())
                ->action(function (array $data) {
                    $shift = ShiftResource::quickCreate($data);
                    Notification::make()->success()->title(__('Shift :name added', ['name' => $shift->displayName()]))->send();
                }),
            Action::make('generateMonth')->label(__('Generate monthly roster'))->icon(Heroicon::OutlinedCalendarDays)
                ->modalDescription(__('Puts the chosen employees on one shift for the whole month. Day cells below still win. Shifts are set up under Shifts & Holidays → Shifts, or add one with "+" next to Shift.'))
                ->schema([
                    TextInput::make('month')->type('month')->required()->default(function () {
                        [$y, $m] = app(PayCycle::class)->periodFor(CarbonImmutable::now(tenant('timezone'))->format('Y-m-d'));

                        return sprintf('%04d-%02d', $y, $m);
                    }),
                    Select::make('shift_id')->label(__('Shift'))->required()
                        ->options(fn () => Shift::query()->orderBy('start_time')->get()->mapWithKeys(fn (Shift $s) => [$s->id => $s->displayName()]))
                        ->createOptionForm(ShiftResource::quickCreateSchema())
                        ->createOptionUsing(fn (array $data) => ShiftResource::quickCreate($data)->id),
                    Select::make('scope')->label(__('Roster for'))->required()->default('all')->live()
                        ->options(['all' => __('All employees'), 'branch' => __('A branch'), 'department' => __('A department'), 'shift' => __('Everyone now on a shift')]),
                    Select::make('scope_id')->label(__('Which one'))
                        ->options(fn (Get $get) => match ($get('scope')) {
                            'branch' => Branch::pluck('name', 'id'),
                            'department' => Department::pluck('name', 'id'),
                            'shift' => Shift::pluck('name', 'id'),
                            default => [],
                        })
                        ->hidden(fn (Get $get) => $get('scope') === 'all')
                        ->required(fn (Get $get) => $get('scope') !== 'all'),
                ])
                ->action(function (array $data, GenerateMonthlyRoster $generate) {
                    [$y, $m] = array_map('intval', explode('-', (string) $data['month']));
                    $n = $generate($y, $m, Shift::findOrFail($data['shift_id']), (string) $data['scope'],
                        isset($data['scope_id']) ? (int) $data['scope_id'] : null, auth()->user());
                    Notification::make()->success()->title(__(':n employees rostered for :month', ['n' => $n, 'month' => app(PayCycle::class)->label($y, $m)]))->send();
                    $this->load();
                }),
        ];
    }

    private function dayAssignments(string $from, string $to): Collection
    {
        return ShiftAssignment::query()->whereColumn('effective_from', 'effective_to')
            ->whereBetween('effective_from', [$from, $to])->get();
    }
}
