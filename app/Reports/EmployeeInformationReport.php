<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Employee;

/** R20 — Employee information: the employee master as a printable list. */
final class EmployeeInformationReport extends Report
{
    public function title(): string
    {
        return __('Employee information');
    }

    public function filterSchema(): array
    {
        return $this->orgFilters();
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return [
            'code' => 'Id', 'name' => 'Employee name', 'father' => 'Father / husband', 'cnic' => 'CNIC', 'address' => 'Address',
            'dob' => 'D.O.B', 'joined' => 'Joining date', 'grade' => 'Grade', 'department' => 'Department',
            'designation' => 'Designation', 'category' => 'Category', 'status' => 'Status',
        ];
    }

    public function rows(array $filters): array
    {
        return $this->employees($filters)->with(['grade', 'department', 'designation'])->get()
            ->map(fn (Employee $e) => [
                'code' => $e->code,
                'name' => $e->fullName(),
                'father' => $e->father_name,
                'cnic' => $e->cnic ? substr($e->cnic, 0, 5) . '-' . substr($e->cnic, 5, 7) . '-' . substr($e->cnic, 12) : null,
                'address' => implode(', ', array_filter([$e->address, $e->city])) ?: null,
                'dob' => $e->dob?->format('d-m-Y'),
                'joined' => $e->joined_on?->format('d-m-Y'),
                'grade' => $e->grade?->name,
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
                'category' => $e->employment_type?->name,
                'status' => $e->status?->name,
            ])->values()->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Employees' => count($rows)];
    }
}
