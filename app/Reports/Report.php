<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Branch;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;

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

    /** Are the filters complete enough to run? (e.g. an employee has been picked) */
    public function ready(array $filters): bool
    {
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
            Select::make('branch_id')->label(__('Branch'))->options(fn () => Branch::pluck('name', 'id'))->placeholder(__('All')),
            Select::make('department_id')->label(__('Department'))->options(fn () => Department::pluck('name', 'id'))->placeholder(__('All')),
            Select::make('designation_id')->label(__('Designation'))->options(fn () => Designation::orderBy('name')->pluck('name', 'id'))
                ->searchable()->placeholder(__('All')),
            Select::make('shift_id')->label(__('Shift'))->options(fn () => Shift::orderBy('start_time')->pluck('name', 'id'))->placeholder(__('All')),
            Select::make('employment_type')->label(__('Category'))->options(EmploymentType::class)->placeholder(__('All')),
            Select::make('contractor_id')->label(__('Contractor'))->options(fn () => Contractor::orderBy('name')->pluck('name', 'id'))
                ->placeholder(__('All'))->visible(fn () => Contractor::query()->exists()),
            Select::make('employee_status')->label(__('Employee status'))->options(EmployeeStatus::class)->placeholder(__('All')),
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
            ->when($filters['employee_status'] ?? null, fn ($q, $v) => $q->where('status', $v instanceof EmployeeStatus ? $v->value : $v))
            ->when($filters['shift_id'] ?? null, fn ($q, $v) => $q->whereIn('id', $this->employeesOnShift((int) $v)))
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

    /** 'Y-m' of the pay-cycle month containing today. */
    protected function currentCycleMonth(): string
    {
        [$y, $m] = app(PayCycle::class)->periodFor(CarbonImmutable::now($this->tz())->format('Y-m-d'));

        return sprintf('%04d-%02d', $y, $m);
    }

    /** @return list<int> employees whose shift today is $shiftId */
    protected function employeesOnShift(int $shiftId): array
    {
        return app(ShiftResolver::class)->employeeIdsOnShift($shiftId, CarbonImmutable::now($this->tz())->format('Y-m-d'));
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
