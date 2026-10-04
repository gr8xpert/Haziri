<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Enums\EmploymentType;
use App\Enums\WorkMode;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Setting;
use App\Services\Ai\AiGateway;
use App\Services\Settings\TenantSettings;
use App\Support\Excel\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Excel/CSV employee import (spec §9 phase 1): template or the client's own sheet → match columns → preview
 * (per-row errors) → import. Rows are matched on `code`: existing employees are updated, new ones created.
 */
final class EmployeeImporter
{
    public const HEADERS = [
        'code', 'first_name', 'last_name', 'cnic', 'phone', 'email', 'gender', 'dob',
        'branch_code', 'department_code', 'designation', 'employment_type', 'joined_on',
        'device_pin', 'card_no', 'work_mode', 'overtime_eligible',
        'father_name', 'address', 'city', 'grade', 'payroll_id', 'home_phone', 'emergency_contact',
    ];

    /** Fields a column can be matched to. `full_name` is split into first / last name. */
    public const FIELDS = [
        'code' => 'Employee code', 'first_name' => 'First name', 'last_name' => 'Last name',
        'full_name' => 'Full name (split into first / last)', 'joined_on' => 'Joining date', 'device_pin' => 'Machine PIN',
        'card_no' => 'Card number', 'cnic' => 'CNIC', 'phone' => 'Cell phone', 'email' => 'Email', 'gender' => 'Gender',
        'dob' => 'Date of birth', 'branch_code' => 'Branch (code or name)', 'department_code' => 'Department (code or name)',
        'designation' => 'Designation', 'grade' => 'Grade', 'employment_type' => 'Employment type', 'work_mode' => 'Work mode',
        'overtime_eligible' => 'Overtime eligible (yes / no)', 'father_name' => "Father's / husband's name", 'address' => 'Address',
        'city' => 'City', 'payroll_id' => 'Payroll ID', 'home_phone' => 'Home phone', 'emergency_contact' => 'Emergency contact',
    ];

    /** Fields every row needs (full_name can stand in for first_name). */
    public const REQUIRED = ['code', 'first_name', 'joined_on'];

    /** Optional free-text columns copied as-is (older templates without them still import). */
    private const OPTIONAL_TEXT = ['father_name', 'address', 'city', 'payroll_id', 'home_phone', 'emergency_contact'];

    /**
     * Column names seen in clients' own sheets, compared lower-case without spaces or punctuation. "F.Name" is left out
     * on purpose: in Pakistan it can mean first name or father's name.
     */
    private const SYNONYMS = [
        'code' => ['empno', 'empcode', 'employeeno', 'employeenumber', 'employeecode', 'empid', 'employeeid', 'staffno', 'staffid', 'staffcode', 'personnelno', 'pno', 'tokenno', 'ticketno'],
        'first_name' => ['firstname', 'givenname'],
        'last_name' => ['lastname', 'surname', 'familyname'],
        'full_name' => ['name', 'fullname', 'employeename', 'empname', 'staffname', 'nameofemployee'],
        'joined_on' => ['joinedon', 'joiningdate', 'dateofjoining', 'doj', 'joindate', 'hiredate', 'dateofappointment', 'appointmentdate'],
        'device_pin' => ['devicepin', 'pin', 'machinepin', 'machineid', 'userid', 'enrollid', 'enrollmentid', 'biometricid', 'fingerprintid', 'attendanceid', 'acno'],
        'card_no' => ['cardno', 'cardnumber', 'rfid', 'badgeno'],
        'cnic' => ['cnic', 'cnicno', 'nic', 'nicno', 'idcard', 'idcardno', 'nationalid'],
        'phone' => ['phone', 'mobile', 'cell', 'cellno', 'mobileno', 'phoneno', 'contactno', 'cellphone'],
        'email' => ['email', 'emailaddress'],
        'gender' => ['gender', 'sex'],
        'dob' => ['dob', 'dateofbirth', 'birthdate'],
        'branch_code' => ['branchcode', 'branch', 'location', 'site'],
        'department_code' => ['departmentcode', 'department', 'dept', 'deptcode', 'deptname', 'section'],
        'designation' => ['designation', 'jobtitle', 'position', 'post'],
        'grade' => ['grade', 'scale', 'bps', 'payscale'],
        'employment_type' => ['employmenttype', 'emptype', 'jobtype'],
        'work_mode' => ['workmode'],
        'overtime_eligible' => ['overtimeeligible', 'overtime', 'ot', 'oteligible'],
        'father_name' => ['fathername', 'fathersname', 'fatherhusbandname', 'fathershusbandsname', 'sodo', 'guardian'],
        'address' => ['address', 'homeaddress', 'presentaddress', 'permanentaddress', 'residentialaddress'],
        'city' => ['city', 'town'],
        'payroll_id' => ['payrollid', 'payrollno', 'salaryid'],
        'home_phone' => ['homephone', 'landline', 'telephone', 'residencephone'],
        'emergency_contact' => ['emergencycontact', 'emergency', 'nextofkin'],
    ];

    /** Setting holding the column choices HR confirmed last time (normalised header => field). */
    private const REMEMBERED = 'employee_import_columns';

    public function __construct(private readonly TenantSettings $settings) {}

    /**
     * The template: an example row with drop-downs, an Instructions sheet and the company's own codes.
     */
    public function writeTemplate(string $path): void
    {
        $column = fn (string $field) => array_search($field, self::HEADERS, true);
        $allowed = $this->allowedValues();

        Spreadsheet::writeWorkbook($path, [
            [
                'name' => 'Employees',
                'headers' => self::HEADERS,
                'rows' => [[
                    'E001', 'Ali', 'Khan', '35202-1234567-1', '03001234567', 'ali@example.com', 'male', '14/05/1990',
                    'HO', 'ACC', 'Accountant', 'permanent', '15/01/2024', '47', '', 'office', 'no',
                    'Abdul Hafeez', 'House 10, Sector 14-B, North Karachi', 'Karachi', 'Officer', '320001', '021-6655635', 'Brother 0300-1234567',
                ]],
                'widths' => array_fill(0, count(self::HEADERS), 16.0),
                'lists' => [
                    $column('gender') => $allowed['gender'],
                    $column('employment_type') => $allowed['employment_type'],
                    $column('work_mode') => $allowed['work_mode'],
                    $column('overtime_eligible') => $allowed['overtime_eligible'],
                ],
            ],
            [
                'name' => 'Instructions',
                'headers' => ['Column', 'Required', 'What to enter', 'Allowed values'],
                'rows' => $this->instructionRows($allowed),
                'widths' => [22.0, 10.0, 70.0, 40.0],
            ],
            [
                'name' => 'Codes',
                'headers' => ['Branch code', 'Branch name', '', 'Department code', 'Department name', '', 'Designations', '', 'Grades'],
                'rows' => $this->codeRows(),
                'widths' => [14.0, 28.0, 3.0, 16.0, 28.0, 3.0, 28.0, 3.0, 20.0],
            ],
        ]);
    }

    /**
     * Guess which field each column of the client's sheet is: what HR chose for that heading last time, then common
     * names. Each field is used once (the first column wins).
     *
     * @param  list<string>  $headers  as written in the sheet
     * @return array<string, ?string> lower-cased header => field (null = skip the column)
     */
    public function guessMapping(array $headers): array
    {
        $remembered = (array) $this->settings->get(self::REMEMBERED, []);
        $lookup = [];
        foreach (self::SYNONYMS as $field => $names) {
            foreach ([self::normalise($field), self::normalise(self::FIELDS[$field]), ...$names] as $name) {
                $lookup[$name] ??= $field;
            }
        }

        $mapping = [];
        $used = [];
        foreach ($headers as $header) {
            $key = strtolower($header);
            if ($key === '' || array_key_exists($key, $mapping)) {
                continue;
            }
            $normal = self::normalise($header);
            $field = array_key_exists($normal, $remembered) ? $remembered[$normal] : ($lookup[$normal] ?? null);
            $field = is_string($field) && isset(self::FIELDS[$field]) && ! isset($used[$field]) ? $field : null;
            $mapping[$key] = $field;
            if ($field !== null) {
                $used[$field] = true;
            }
        }

        return $mapping;
    }

    /**
     * Optional: ask the AI which field each still-unmatched heading is. Only the headings are sent, never employee data.
     *
     * @param  list<string>  $headers
     * @return array<string, string> lower-cased header => field, for the headings it could place
     */
    public function suggestWithAi(array $headers, AiGateway $ai): array
    {
        if ($headers === [] || ! $ai->available()) {
            return [];
        }
        $result = $ai->ask('import', 'match ' . count($headers) . ' import columns',
            'Match each spreadsheet column heading from a Pakistani company\'s employee list to one field, or "" if none fits. '
            . 'Fields: ' . json_encode(self::FIELDS, JSON_UNESCAPED_UNICODE),
            [['type' => 'text', 'text' => json_encode(array_values($headers), JSON_UNESCAPED_UNICODE)]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['header', 'field'],
                'properties' => ['header' => ['type' => 'string'], 'field' => ['type' => 'string', 'enum' => ['', ...array_keys(self::FIELDS)]]],
            ]]]]);

        $out = [];
        foreach ($result->ok ? ($result->data['items'] ?? []) : [] as $item) {
            if (in_array($item['header'] ?? null, $headers, true) && isset(self::FIELDS[$item['field'] ?? ''])) {
                $out[strtolower($item['header'])] = $item['field'];
            }
        }

        return $out;
    }

    /**
     * Remember the columns HR confirmed, so the next upload of the same sheet layout is matched straight away.
     *
     * @param  list<string>  $headers
     * @param  array<string, ?string>  $mapping  lower-cased header => field
     */
    public function rememberMapping(array $headers, array $mapping): void
    {
        $remembered = (array) $this->settings->get(self::REMEMBERED, []);
        foreach ($headers as $header) {
            if (array_key_exists(strtolower($header), $mapping)) {
                $remembered[self::normalise($header)] = $mapping[strtolower($header)];
            }
        }
        Setting::query()->updateOrCreate(['key' => self::REMEMBERED], ['value' => $remembered]);
        $this->settings->flush();
    }

    /**
     * @param  array<string, ?string>|null  $mapping  lower-cased header => field; null = guess
     * @return array{headers: list<string>, sample: array<string, string>, mapping: array<string, ?string>, rows: list<array<string, mixed>>, errors: array<int, list<string>>, creates: int, updates: int, new_departments: list<string>}
     */
    public function preview(string $path, ?array $mapping = null, bool $createMissing = false): array
    {
        ['headers' => $headers, 'rows' => $raw] = Spreadsheet::readWithHeaders($path);
        $mapping ??= $this->guessMapping($headers);

        $branches = Branch::query()->get(['id', 'code', 'name']);
        $departments = Department::query()->get(['id', 'code', 'name']);
        $find = fn ($models, string $value) => $models->first(fn ($m) => strcasecmp((string) $m->code, $value) === 0 || strcasecmp((string) $m->name, $value) === 0)?->id;
        $existing = Employee::withTrashed()->pluck('id', 'code');

        $rows = [];
        $errors = [];
        $seenCodes = [];
        $seenPins = [];
        $newDepartments = [];
        foreach ($raw as $i => $line) {
            $lineNo = $i + 2; // header is line 1
            $r = array_fill_keys(array_keys(self::FIELDS), null);
            foreach ($mapping as $column => $field) {
                if ($field !== null && isset(self::FIELDS[$field]) && ($line[$column] ?? '') !== '') {
                    $r[$field] = trim((string) $line[$column]);
                }
            }
            if ($r['first_name'] === null && $r['full_name'] !== null) {
                [$r['first_name'], $r['last_name']] = self::splitName($r['full_name']);
            }
            $rowErrors = [];

            $r['cnic'] = $r['cnic'] !== null ? preg_replace('/\D/', '', $r['cnic']) : null;
            $r['gender'] = $r['gender'] !== null ? self::gender($r['gender']) ?? $r['gender'] : null;
            $r['employment_type'] = $r['employment_type'] !== null ? self::enumValue(EmploymentType::class, $r['employment_type']) ?? $r['employment_type'] : null;
            $r['work_mode'] = $r['work_mode'] !== null ? self::enumValue(WorkMode::class, $r['work_mode']) ?? $r['work_mode'] : null;
            foreach (['joined_on' => 'joining date', 'dob' => 'date of birth'] as $field => $label) {
                if ($r[$field] !== null) {
                    $date = self::date($r[$field]);
                    if ($date === null) {
                        $rowErrors[] = "Could not read the {$label} '{$r[$field]}' (use 15/01/2024).";
                    }
                    $r[$field] = $date;
                }
            }

            $validator = Validator::make($r, [
                'code' => ['required', 'max:30'],
                'first_name' => ['required', 'max:100'],
                'last_name' => ['nullable', 'max:100'],
                'cnic' => ['nullable', 'digits:13'],
                'email' => ['nullable', 'email'],
                'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
                'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
                'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
                'joined_on' => [$rowErrors === [] ? 'required' : 'nullable'],
                'device_pin' => ['nullable', 'max:20'],
                'father_name' => ['nullable', 'max:255'],
                'address' => ['nullable', 'max:1000'],
                'city' => ['nullable', 'max:100'],
                'grade' => ['nullable', 'max:255'],
                'payroll_id' => ['nullable', 'max:30'],
                'home_phone' => ['nullable', 'max:20'],
                'emergency_contact' => ['nullable', 'max:255'],
            ]);
            $rowErrors = [...$rowErrors, ...$validator->errors()->all()];

            $branchId = null;
            if ($r['branch_code'] !== null) {
                $branchId = $find($branches, $r['branch_code']);
                if ($branchId === null) {
                    $rowErrors[] = "Unknown branch '{$r['branch_code']}'. Add it under Organisation → Branches first.";
                }
            }
            $departmentId = null;
            $departmentNew = null;
            if ($r['department_code'] !== null) {
                $departmentId = $find($departments, $r['department_code']);
                if ($departmentId === null && $createMissing) {
                    $departmentNew = $r['department_code'];
                    $newDepartments[strtolower($departmentNew)] = $departmentNew;
                } elseif ($departmentId === null) {
                    $rowErrors[] = "Unknown department '{$r['department_code']}'. Tick \"Create missing departments\" to add it.";
                }
            }
            if ($r['code'] !== null && isset($seenCodes[$r['code']])) {
                $rowErrors[] = "Code {$r['code']} appears twice in the file.";
            }
            if ($r['device_pin'] !== null) {
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
                $errors[$lineNo] = $rowErrors;

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
                'dob' => $r['dob'],
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'department_new' => $departmentNew,
                'designation' => $r['designation'],
                'employment_type' => $r['employment_type'] ?? EmploymentType::Permanent->value,
                'joined_on' => $r['joined_on'],
                'device_pin' => $r['device_pin'],
                'card_no' => $r['card_no'],
                'work_mode' => $r['work_mode'] ?? WorkMode::Office->value,
                'overtime_eligible' => self::yes($r['overtime_eligible']),
                'grade' => $r['grade'],
                '_exists' => isset($existing[$r['code']]),
            ] + array_map(fn (string $k) => $r[$k], array_combine(self::OPTIONAL_TEXT, self::OPTIONAL_TEXT));
        }

        $updates = count(array_filter($rows, fn ($r) => $r['_exists']));

        return ['headers' => $headers, 'sample' => $raw[0] ?? [], 'mapping' => $mapping, 'rows' => $rows, 'errors' => $errors,
            'creates' => count($rows) - $updates, 'updates' => $updates, 'new_departments' => array_values($newDepartments)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  valid rows from preview()
     */
    public function import(array $rows): int
    {
        return DB::transaction(function () use ($rows) {
            $designations = [];
            $grades = [];
            $departments = [];
            foreach ($rows as $row) {
                $designationId = null;
                if ($row['designation']) {
                    $designationId = $designations[$row['designation']]
                        ??= Designation::firstOrCreate(['name' => $row['designation']])->id;
                }
                $gradeId = null;
                if (! empty($row['grade'])) {
                    $gradeId = $grades[$row['grade']] ??= (Grade::query()->where('name', $row['grade'])->orWhere('code', $row['grade'])->value('id')
                        ?? Grade::create(['code' => Str::limit(Str::upper(Str::slug($row['grade'], '')), 20, ''), 'name' => $row['grade']])->id);
                }
                $departmentId = $row['department_id'] ?? null;
                if ($departmentId === null && ! empty($row['department_new'])) {
                    $departmentId = $departments[strtolower($row['department_new'])] ??= self::createDepartment($row['department_new']);
                }
                // Blank optional columns never wipe what is already on file.
                $optional = array_filter(array_intersect_key($row, array_flip(self::OPTIONAL_TEXT)), fn ($v) => $v !== null);
                $attrs = collect($row)->except(['designation', 'grade', 'department_new', '_exists', 'code', ...self::OPTIONAL_TEXT])->all()
                    + $optional + ['designation_id' => $designationId] + ($gradeId ? ['grade_id' => $gradeId] : []);
                $attrs['department_id'] = $departmentId;
                Employee::withTrashed()->updateOrCreate(['code' => $row['code']], $attrs);
            }

            return count($rows);
        });
    }

    /** A department found only by name in the file: create it with a code made from the name. */
    private static function createDepartment(string $name): int
    {
        $existing = Department::withTrashed()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing->id;
        }
        $base = Str::limit(Str::upper(Str::slug($name, '')), 16, '') ?: 'DEPT';
        $code = $base;
        for ($n = 2; Department::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = $base . $n;
        }

        return Department::create(['name' => $name, 'code' => $code])->id;
    }

    /** "Muhammad Ali Khan" → ["Muhammad Ali", "Khan"]; a single word is the first name. */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return [$parts[0] ?? null, null];
        }
        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    /** Dates as Pakistanis write them: 15/01/2024, 15-01-2024, 15.01.24, 15 Jan 2024, 2024-01-15, or an Excel day number. */
    private static function date(string $value): ?string
    {
        $value = trim($value);
        try {
            if (preg_match('/^\d{4,5}$/', $value)) {
                return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d'); // Excel serial
            }
            if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $value, $m)) {
                $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] - ((int) $m[3] > (int) date('y') ? 100 : 0) : (int) $m[3];

                return checkdate((int) $m[2], (int) $m[1], $year) ? sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]) : null;
            }
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
                return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
            }

            return preg_match('/[a-z]/i', $value) ? CarbonImmutable::parse($value)->format('Y-m-d') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function gender(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'm', 'male', 'man' => 'male',
            'f', 'female', 'woman' => 'female',
            'o', 'other' => 'other',
            default => null,
        };
    }

    private static function yes(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'x', '✓', 'haan'], true);
    }

    /**
     * An enum value from its value or its label ("Daily wage" → daily_wage).
     *
     * @param  class-string<EmploymentType|WorkMode>  $enum
     */
    private static function enumValue(string $enum, string $value): ?string
    {
        foreach ($enum::cases() as $case) {
            if (in_array(self::normalise($value), [self::normalise($case->value), self::normalise($case->getLabel())], true)) {
                return $case->value;
            }
        }

        return null;
    }

    private static function normalise(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($text)) ?? '';
    }

    /** @return array<string, list<string>> */
    private function allowedValues(): array
    {
        return [
            'gender' => ['male', 'female', 'other'],
            'employment_type' => array_map(fn (EmploymentType $t) => $t->value, EmploymentType::cases()),
            'work_mode' => array_map(fn (WorkMode $m) => $m->value, WorkMode::cases()),
            'overtime_eligible' => ['yes', 'no'],
        ];
    }

    /**
     * @param  array<string, list<string>>  $allowed
     * @return list<list<string>>
     */
    private function instructionRows(array $allowed): array
    {
        $help = [
            'code' => 'Unique employee number. A code already in Haziri updates that employee.',
            'first_name' => 'First name. Or give one "Name" column in your own sheet: it is split into first and last name.',
            'joined_on' => 'Joining date, day first: 15/01/2024 (15-01-2024 and 2024-01-15 also work).',
            'device_pin' => 'The user ID enrolled on the fingerprint / face machine.',
            'cnic' => '13 digits, with or without dashes.',
            'gender' => 'male / female / other (M and F also work).',
            'dob' => 'Date of birth, day first: 14/05/1990.',
            'branch_code' => 'Branch code or name, see the Codes sheet.',
            'department_code' => 'Department code or name, see the Codes sheet. New names can be created during import.',
            'designation' => 'Job title. New ones are created automatically.',
            'grade' => 'Grade name or code. New ones are created automatically.',
            'employment_type' => 'Permanent unless filled in.',
            'work_mode' => 'office unless filled in. Field staff are never limited by the office area.',
            'overtime_eligible' => 'yes / no.',
        ];
        $allowedFor = ['gender' => 'gender', 'employment_type' => 'employment_type', 'work_mode' => 'work_mode', 'overtime_eligible' => 'overtime_eligible'];

        return array_map(fn (string $column) => [
            $column,
            in_array($column, self::REQUIRED, true) ? 'Yes' : '',
            $help[$column] ?? self::FIELDS[$column] ?? '',
            isset($allowedFor[$column]) ? implode(', ', $allowed[$allowedFor[$column]]) : '',
        ], self::HEADERS);
    }

    /** @return list<list<string>> the company's branch / department codes, designations and grades side by side */
    private function codeRows(): array
    {
        $branches = Branch::query()->orderBy('code')->get(['code', 'name']);
        $departments = Department::query()->orderBy('code')->get(['code', 'name']);
        $designations = Designation::query()->orderBy('name')->pluck('name');
        $grades = Grade::query()->orderBy('name')->pluck('name');
        $height = max($branches->count(), $departments->count(), $designations->count(), $grades->count());

        $rows = [];
        for ($i = 0; $i < $height; $i++) {
            $rows[] = [
                $branches[$i]->code ?? '', $branches[$i]->name ?? '', '',
                $departments[$i]->code ?? '', $departments[$i]->name ?? '', '',
                $designations[$i] ?? '', '', $grades[$i] ?? '',
            ];
        }

        return $rows;
    }
}
