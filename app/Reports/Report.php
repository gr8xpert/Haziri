<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Employee;
use App\Models\User;
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
            Select::make('branch_id')->label(__('Branch'))->options(fn () => \App\Models\Branch::pluck('name', 'id'))->placeholder(__('All')),
            Select::make('department_id')->label(__('Department'))->options(fn () => \App\Models\Department::pluck('name', 'id'))->placeholder(__('All')),
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
            ->when($user && ! $user->can('attendance.view_all'), fn ($q) => $q->where('manager_employee_id', $user->employee_id ?? 0))
            ->orderBy('code');
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
