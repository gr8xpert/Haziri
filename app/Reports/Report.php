<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceFlag;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PunchSource;
use App\Models\Contractor;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\ShiftResolver;
use App\Support\BranchAccess;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A tabular report (spec §10). Rendered on screen by App\Filament\Tenant\Pages\Reports\ReportPage
 * and exported to XLSX / PDF by App\Reports\ReportExporter — no report builds its own output.
 */
abstract class Report
{
    abstract public function title(): string;

    /** @return list<Component> */
    abstract public function filterSchema(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string> key => header
     */
    abstract public function columns(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    abstract public function rows(array $filters): array;

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [];
    }

    /** @param array<string, mixed> $filters */
    public function subtitle(array $filters): string
    {
        return '';
    }

    /** What the export file is named after, besides the title (the subtitle unless that is a sentence). @param array<string, mixed> $filters */
    public function fileLabel(array $filters): string
    {
        return $this->subtitle($filters);
    }

    /** Are the filters complete enough to run? By default: every filter marked required() has a value. */
    public function ready(array $filters): bool
    {
        foreach ($this->filterSchema() as $component) {
            if ($component instanceof Field && $component->isRequired() && blank($filters[$component->getName()] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function paper(): string
    {
        return 'portrait';
    }

    /**
     * Summary lines shown under the table (e.g. totals).
     *
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, scalar|null>>  $rows
     * @return array<string, scalar>
     */
    public function summary(array $filters, array $rows): array
    {
        return [];
    }

    /** @return list<Component> */
    protected function orgFilters(): array
    {
        return [
            Select::make('branch_id')->label(__('Branch'))->options(fn () => BranchAccess::branchOptions())->placeholder(__('All')),
            Select::make('department_id')->label(__('Department'))->options(fn () => BranchAccess::departmentOptions())->placeholder(__('All')),
            Select::make('designation_id')->label(__('Designation'))->options(fn () => Designation::orderBy('name')->pluck('name', 'id'))
                ->searchable()->placeholder(__('All')),
            Select::make('shift_id')->label(__('Shift'))->options(fn () => Shift::orderBy('start_time')->pluck('name', 'id'))->placeholder(__('All')),
            Select::make('employment_type')->label(__('Category'))->options(EmploymentType::class)->placeholder(__('All')),
            Select::make('contractor_id')->label(__('Contractor'))->options(fn () => Contractor::orderBy('name')->pluck('name', 'id'))
                ->placeholder(__('All'))->visible(fn () => Contractor::query()->exists()),
            Select::make('employee_status')->label(__('Employee status'))->options(EmployeeStatus::class)->placeholder(__('All')),
            Select::make('employee_id')->label(__('Employee'))->placeholder(__('All'))->searchable()
                ->getSearchResultsUsing(fn (string $search) => Employee::query()
                    ->where(fn ($q) => $q->where('code', 'like', "{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orderBy('code')->limit(30)->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]))
                ->getOptionLabelUsing(fn ($value) => ($e = Employee::find($value)) ? "{$e->code} — {$e->fullName()}" : null),
        ];
    }

    /**
     * Employees the current user may see, narrowed by the branch/department filters.
     * Managers without attendance.view_all only see their direct reports (spec §8).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    protected function employees(array $filters): Builder
    {
        /** @var User|null $user */
        $user = auth()->user();

        return Employee::query()
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['designation_id'] ?? null, fn ($q, $v) => $q->where('designation_id', $v))
            ->when($filters['employment_type'] ?? null, fn ($q, $v) => $q->where('employment_type', $v instanceof EmploymentType ? $v->value : $v))
            ->when($filters['contractor_id'] ?? null, fn ($q, $v) => $q->where('contractor_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->when($filters['employee_status'] ?? null, fn ($q, $v) => $q->where('status', $v instanceof EmployeeStatus ? $v->value : $v))
            ->when($filters['shift_id'] ?? null, fn ($q, $v) => $q->whereIn('id', $this->employeesOnShift((int) $v, $filters)))
            ->when($user && ! $user->can('attendance.view_all'), fn ($q) => $q->where('manager_employee_id', $user->employee_id ?? 0))
            ->orderBy('code');
    }

    /**
     * Bounds of the pay-cycle month named by a 'Y-m' filter (e.g. 2026-06 → 21 May … 20 Jun with a 21st cycle).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function cycleRange(string $month): array
    {
        [$y, $m] = array_map('intval', explode('-', $month));

        return app(PayCycle::class)->range($y, $m);
    }

    protected function cycleLabel(string $month): string
    {
        [$y, $m] = array_map('intval', explode('-', $month));

        return app(PayCycle::class)->label($y, $m);
    }

    /**
     * The employee's name, marked when they are paid per day worked: their "payable days" are the days they came, so
     * a 25 next to colleagues' 29 is right, not a mistake.
     */
    protected static function nameWithPayBasis(Employee $e, ?string $name = null): string
    {
        return ($name ?? $e->fullName()) . ($e->employment_type === EmploymentType::DailyWage ? ' · ' . __('daily wage') : '');
    }

    /**
     * A legend line when the report has daily-wage staff.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    protected static function dailyWageNote(array $rows): array
    {
        $marker = ' · ' . __('daily wage');

        return collect($rows)->contains(fn (array $r) => str_ends_with((string) ($r['name'] ?? ''), $marker))
            ? [__('Daily wage') => __('paid only for the days worked')] : [];
    }

    /** A date as reports show it everywhere: "15 Sep 2026". */
    protected static function day(mixed $date): string
    {
        return blank($date) ? '' : CarbonImmutable::parse($date)->format('d M Y');
    }

    /**
     * Day flags as words ("Late, Overtime"), never codes like early_out.
     *
     * @param  list<string>|null  $flags
     * @param  list<string>  $except
     */
    protected static function flagLabels(?array $flags, array $except = []): string
    {
        return collect($flags ?? [])->reject(fn (string $f) => in_array($f, $except, true))
            ->map(fn (string $f) => AttendanceFlag::tryFrom($f)?->getLabel() ?? Str::headline($f))->implode(', ');
    }

    /** Where the day's punches came from, as words ("Machine (ADMS), Kiosk"). @param array<string, mixed>|null $summary */
    protected static function sourceLabels(?array $summary): string
    {
        return collect(array_keys($summary ?? []))
            ->map(fn (string $s) => PunchSource::tryFrom($s)?->getLabel() ?? Str::headline($s))->implode(', ');
    }

    /** 'Y-m' of the pay-cycle month containing today. */
    protected function currentCycleMonth(): string
    {
        [$y, $m] = app(PayCycle::class)->periodFor(CarbonImmutable::now($this->tz())->format('Y-m-d'));

        return sprintf('%04d-%02d', $y, $m);
    }

    /** @return list<int> employees whose shift today is $shiftId */
    /**
     * Employees on $shiftId on any day the report covers: From–To, the Date, or the pay-cycle Month; today otherwise.
     *
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    protected function employeesOnShift(int $shiftId, array $filters): array
    {
        $today = CarbonImmutable::now($this->tz())->format('Y-m-d');
        [$from, $to] = match (true) {
            ! empty($filters['from']) && ! empty($filters['to']) => [(string) $filters['from'], (string) $filters['to']],
            ! empty($filters['date']) => [(string) $filters['date'], (string) $filters['date']],
            (bool) preg_match('/^\d{4}-\d{2}$/', (string) ($filters['month'] ?? '')) => array_map(
                fn (CarbonImmutable $d) => $d->format('Y-m-d'), $this->cycleRange((string) $filters['month'])),
            default => [$today, $today],
        };

        return app(ShiftResolver::class)->employeeIdsOnShiftBetween($shiftId, substr($from, 0, 10), substr($to, 0, 10));
    }

    protected function tz(): string
    {
        return tenant('timezone') ?? config('hazri.default_timezone');
    }

    protected static function hm(int $minutes): string
    {
        return $minutes <= 0 ? '' : sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
