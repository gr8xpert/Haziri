<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Enums\EmploymentType;
use App\Enums\WorkMode;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Support\Excel\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Excel/CSV employee import (spec §9 phase 1): template → upload → preview (per-row errors) → import.
 * Rows are matched on `code`: existing employees are updated, new ones created.
 */
final class EmployeeImporter
{
    public const HEADERS = [
        'code', 'first_name', 'last_name', 'cnic', 'phone', 'email', 'gender', 'dob',
        'branch_code', 'department_code', 'designation', 'employment_type', 'joined_on',
        'device_pin', 'card_no', 'work_mode', 'overtime_eligible',
    ];

    public function writeTemplate(string $path): void
    {
        Spreadsheet::write($path, self::HEADERS, [[
            'E001', 'Ali', 'Khan', '3520212345671', '03001234567', 'ali@example.com', 'male', '1990-05-14',
            'HO', 'ACC', 'Accountant', 'permanent', '2024-01-15', '47', '', 'office', 'no',
        ]]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: array<int, list<string>>, creates: int, updates: int}
     */
    public function preview(string $path): array
    {
        $raw = Spreadsheet::readAssoc($path);
        $branches = Branch::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id]);
        $departments = Department::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id]);
        $existing = Employee::withTrashed()->pluck('id', 'code');

        $rows = [];
        $errors = [];
        $seenCodes = [];
        $seenPins = [];
        foreach ($raw as $i => $r) {
            $line = $i + 2; // header is line 1
            $r = array_map(fn ($v) => $v === '' ? null : $v, $r);
            $r['cnic'] = isset($r['cnic']) ? preg_replace('/\D/', '', (string) $r['cnic']) : null;

            $validator = Validator::make($r, [
                'code' => ['required', 'max:30'],
                'first_name' => ['required', 'max:100'],
                'last_name' => ['nullable', 'max:100'],
                'cnic' => ['nullable', 'digits:13'],
                'email' => ['nullable', 'email'],
                'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
                'dob' => ['nullable', 'date'],
                'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
                'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
                'joined_on' => ['required', 'date'],
                'device_pin' => ['nullable', 'max:20'],
            ]);
            $rowErrors = $validator->errors()->all();

            $branchId = null;
            if (! empty($r['branch_code'])) {
                $branchId = $branches[strtolower($r['branch_code'])] ?? null;
                if ($branchId === null) {
                    $rowErrors[] = "Unknown branch code '{$r['branch_code']}'.";
                }
            }
            $departmentId = null;
            if (! empty($r['department_code'])) {
                $departmentId = $departments[strtolower($r['department_code'])] ?? null;
                if ($departmentId === null) {
                    $rowErrors[] = "Unknown department code '{$r['department_code']}'.";
                }
            }
            if (isset($r['code']) && isset($seenCodes[$r['code']])) {
                $rowErrors[] = "Code {$r['code']} appears twice in the file.";
            }
            if (! empty($r['device_pin'])) {
                if (isset($seenPins[$r['device_pin']])) {
                    $rowErrors[] = "PIN {$r['device_pin']} appears twice in the file.";
                }
                $owner = Employee::withTrashed()->where('device_pin', $r['device_pin'])->value('code');
                if ($owner !== null && $owner !== $r['code']) {
                    $rowErrors[] = "PIN {$r['device_pin']} already belongs to employee {$owner}.";
                }
                $seenPins[$r['device_pin']] = true;
            }
            $seenCodes[$r['code'] ?? ''] = true;

            if ($rowErrors) {
                $errors[$line] = $rowErrors;

                continue;
            }

            $rows[] = [
                'code' => $r['code'],
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'cnic' => $r['cnic'] ?: null,
                'phone' => $r['phone'],
                'email' => $r['email'],
                'gender' => $r['gender'],
                'dob' => $r['dob'] ? CarbonImmutable::parse($r['dob'])->format('Y-m-d') : null,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'designation' => $r['designation'],
                'employment_type' => $r['employment_type'] ?? EmploymentType::Permanent->value,
                'joined_on' => CarbonImmutable::parse($r['joined_on'])->format('Y-m-d'),
                'device_pin' => $r['device_pin'],
                'card_no' => $r['card_no'],
                'work_mode' => $r['work_mode'] ?? WorkMode::Office->value,
                'overtime_eligible' => in_array(strtolower((string) $r['overtime_eligible']), ['1', 'yes', 'y', 'true'], true),
                '_exists' => isset($existing[$r['code']]),
            ];
        }

        $updates = count(array_filter($rows, fn ($r) => $r['_exists']));

        return ['rows' => $rows, 'errors' => $errors, 'creates' => count($rows) - $updates, 'updates' => $updates];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  valid rows from preview()
     */
    public function import(array $rows): int
    {
        return DB::transaction(function () use ($rows) {
            $designations = [];
            foreach ($rows as $row) {
                $designationId = null;
                if ($row['designation']) {
                    $designationId = $designations[$row['designation']]
                        ??= Designation::firstOrCreate(['name' => $row['designation']])->id;
                }
                $attrs = collect($row)->except(['designation', '_exists', 'code'])->all() + ['designation_id' => $designationId];
                Employee::withTrashed()->updateOrCreate(['code' => $row['code']], $attrs);
            }

            return count($rows);
        });
    }
}
