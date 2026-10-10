<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DevicePunchSourceType;
use App\Enums\EmploymentType;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Contractor;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Kiosk;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AnomalyDetector;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Settings\TenantSettings;
use App\Support\DemoMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Collection;

/**
 * Fills the current company with a realistic demo: 20 staff in two branches, ~6 weeks of punches, and something on
 * every page (requests waiting for approval, last month locked and paid, visitors, kiosk photos, ...). Dates are
 * relative to today, so the demo always looks current. Run inside the tenant (hazri:demo:seed).
 */
final class DemoCompanySeeder
{
    use DemoSteps;

    private const FIRST = ['Ali', 'Sana', 'Bilal', 'Ayesha', 'Usman', 'Fatima', 'Hamza', 'Zainab', 'Imran', 'Hira',
        'Kamran', 'Mehwish', 'Faisal', 'Nida', 'Asad', 'Rabia', 'Tariq', 'Sadia', 'Waqas', 'Amna'];

    private const LAST = ['Khan', 'Ahmed', 'Malik', 'Butt', 'Sheikh', 'Qureshi', 'Raza', 'Iqbal', 'Hussain', 'Chaudhry'];

    public function __construct(
        private readonly DemoActivitySeeder $activity,
        private readonly DemoSelfServiceSeeder $selfService,
        private readonly DemoCalendarSeeder $calendar,
        private readonly DemoPayrollSeeder $payroll,
        private readonly DemoDay $day,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveYear $leaveYear,
        private readonly AttendanceRecomputer $recomputer,
    ) {}

    /** @param Closure(string): void $say progress output */
    public function run(Closure $say): void
    {
        $this->say = $say;
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $slug = (string) tenant('slug');
        app(TenantSettings::class)->flush();
        Setting::updateOrCreate(['key' => 'overtime_enabled'], ['value' => true]);
        if (DemoMode::isDemoSlug($slug)) {
            // Anyone can ask Ask Haziri questions in the public demo: keep the AI bill small.
            Setting::updateOrCreate(['key' => 'ai_monthly_token_budget'], ['value' => (int) config('hazri.demo.ai_token_budget')]);
        }

        $employees = $this->people($slug);
        $this->logins($employees, $slug);
        $this->salaries($employees);
        $this->devices();

        $today = CarbonImmutable::now($tz);
        $start = $today->subMonthNoOverflow()->startOfMonth();
        $say('Rosters, holidays…');
        $this->calendar->run($employees, $today, $say); // before the punches: they follow each person's shift
        $say('Punches…');
        foreach (CarbonPeriod::create($start, $today->subDays(2)) as $day) {
            $date = CarbonImmutable::parse($day->format('Y-m-d'), $tz);
            $this->day->play($employees, $date->format('Y-m-d'), $date->addDays(2), selfService: false);
        }
        $this->day->catchUp($today); // yesterday and today so far, with kiosk and phone punches
        $this->leaveSetup($employees);

        $say('Requests, corrections, visitors…');
        $this->activity->run($employees, $today, $say);
        $this->selfService->run($employees, $today, $say);

        $say('Recomputing attendance…');
        $now = CarbonImmutable::now('UTC');
        foreach (CarbonPeriod::create($start, $today) as $day) {
            foreach ($employees as $e) {
                $this->recomputer->recompute($e->id, $day->format('Y-m-d'), $now);
            }
        }

        $say('Payroll…');
        $this->payroll->run($employees, $start, $today, $say);
        $this->attempt('anomaly scan', fn () => app(AnomalyDetector::class)->run($today));
    }

    /** @return Collection<int, Employee> 20 staff; the first of each department is its manager */
    private function people(string $slug): Collection
    {
        $lhr = Branch::firstOrCreate(['code' => 'LHE'], ['name' => 'Lahore HQ', 'lat' => 31.5204, 'lng' => 74.3587, 'geofence_radius_m' => 200]);
        $khi = Branch::firstOrCreate(['code' => 'KHI'], ['name' => 'Karachi', 'lat' => 24.8607, 'lng' => 67.0011]);
        $depts = collect(['SAL' => 'Sales', 'ACC' => 'Accounts', 'OPS' => 'Operations', 'HRD' => 'HR'])
            ->map(fn ($name, $code) => Department::firstOrCreate(['code' => $code], ['name' => $name]))->values();
        $designations = collect(['Manager', 'Officer', 'Executive', 'Assistant'])->map(fn ($n) => Designation::firstOrCreate(['name' => $n]));
        $officer = Grade::firstOrCreate(['code' => 'OFF'], ['name' => 'Officer']);
        $worker = Grade::firstOrCreate(['code' => 'WRK'], ['name' => 'Worker']);
        $contractor = Contractor::firstOrCreate(['code' => 'ANM'], ['name' => 'Al-Noor Manpower', 'phone' => '0321-5550101']);

        $employees = collect();
        foreach (self::FIRST as $i => $first) {
            $dept = $depts[$i % 4];
            $employees->push(Employee::firstOrCreate(['code' => sprintf('E%03d', $i + 1)], [
                'first_name' => $first, 'last_name' => self::LAST[$i % 10], 'device_pin' => (string) (101 + $i),
                'phone' => sprintf('0300%07d', 1000000 + $i), 'email' => strtolower($first) . "@{$slug}.test",
                'branch_id' => $i < 15 ? $lhr->id : $khi->id, 'department_id' => $dept->id,
                'designation_id' => $designations[$i < 4 ? 0 : 1 + $i % 3]->id,
                'employment_type' => match (true) {
                    $i === 19 => EmploymentType::DailyWage, $i >= 17 => EmploymentType::Contract, default => EmploymentType::Permanent
                },
                // Asad is a new joiner (part of last month), so the grid and payroll show a "not joined yet" stretch.
                'joined_on' => $i === 14 ? CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->format('Y-m-d') : '2025-01-15',
                'overtime_eligible' => $i % 3 === 0, 'gender' => $i % 2 ? 'female' : 'male',
                'dob' => sprintf('199%d-%02d-%02d', $i % 10, 1 + $i % 12, 1 + $i % 27),
                'cnic' => sprintf('35202%07d%d', 1234500 + $i * 7, $i % 2 ? 2 : 1),
                'father_name' => self::FIRST[($i + 7) % count(self::FIRST)] . ' ' . self::LAST[$i % 10],
                'address' => sprintf('House %d, Street %d', 10 + $i, 1 + $i % 9), 'city' => $i < 15 ? 'Lahore' : 'Karachi',
                'payroll_id' => (string) (320001 + $i), 'grade_id' => $i < 12 ? $officer->id : $worker->id,
                'contractor_id' => $i >= 17 && $i < 19 ? $contractor->id : null,
                'work_mode' => $i === 7 ? 'field' : 'office',
            ]));
        }
        foreach ($employees->slice(4) as $i => $e) {
            $e->forceFill(['manager_employee_id' => $employees[$i % 4]->id])->saveQuietly();
        }

        return $employees;
    }

    /**
     * Owner, HR, a manager, reception and one employee (for /me). In the public demo all share one known password,
     * shown on its login page.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function logins(Collection $employees, string $slug): void
    {
        $password = DemoMode::isDemoSlug($slug) ? (string) config('hazri.demo.password') : 'password';
        $login = function (string $who, string $name, Role $role, ?Employee $e = null) use ($slug, $password) {
            $u = User::firstOrCreate(['email' => DemoMode::email($slug, $who)], ['name' => $name, 'password' => $password, 'employee_id' => $e?->id, 'phone' => $e?->phone]);
            $u->syncRoles([$role->value]);
        };
        $login('hr', 'Hina HR', Role::Hr);
        $login('reception', 'Reception', Role::Receptionist);
        $login('manager', $employees[0]->fullName(), Role::Manager, $employees[0]);
        $login('employee', $employees[4]->fullName(), Role::Employee, $employees[4]);
        if (DemoMode::isDemoSlug($slug)) {
            // The owner came from provisioning with a random password (quietly: demo logins are otherwise locked).
            User::role(Role::Owner->value)->get()->each(fn (User $u) => $u->forceFill(['password' => $password])->saveQuietly());
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function salaries(Collection $employees): void
    {
        foreach ($employees as $i => $e) {
            // No hand-typed EOBI line: EOBI, PF and tax are calculated by payroll (DemoPayrollSeeder registers staff).
            SalaryStructure::firstOrCreate(['employee_id' => $e->id, 'effective_from' => '2025-01-01'], $i === 19
                ? ['basic' => 0, 'per_day_rate' => 1800]
                : ['basic' => 60000 + 5000 * ($i % 5), 'allowances' => [['name' => 'House rent', 'amount' => 15000], ['name' => 'Conveyance', 'amount' => 5000]],
                    'overtime_rate_normal' => 500, 'bank_name' => 'HBL', 'bank_account' => 'PK36HABB00000' . str_pad((string) $i, 11, '0', STR_PAD_LEFT)]);
        }
    }

    private function devices(): void
    {
        $lhr = Branch::where('code', 'LHE')->firstOrFail();
        $khi = Branch::where('code', 'KHI')->firstOrFail();
        Device::firstOrCreate(['serial_number' => self::serial('ADMS-01')], ['name' => 'Lahore reception K40', 'type' => DevicePunchSourceType::Adms,
            'branch_id' => $lhr->id, 'status' => 'online', 'last_seen_at' => now()]);
        Device::firstOrCreate(['serial_number' => self::serial('AGENT-01')], ['name' => 'Karachi office (agent)', 'type' => DevicePunchSourceType::Agent,
            'branch_id' => $khi->id, 'status' => 'offline', 'last_seen_at' => now()->subHours(3), 'ip' => '192.168.1.201']);
        $kioskDevice = Device::firstOrCreate(['serial_number' => self::serial('KIOSK')], ['name' => 'Reception tablet', 'type' => DevicePunchSourceType::Kiosk, 'branch_id' => $lhr->id]);
        Kiosk::firstOrCreate(['device_id' => $kioskDevice->id], ['token' => Kiosk::newToken(), 'allowed_modes' => ['pin', 'qr', 'face'], 'require_photo' => true]);
    }

    /**
     * Officers get 12 casual days instead of the type's 10. Set before any balance is opened, so the balances and the
     * leave reports agree from the start.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function leaveSetup(Collection $employees): void
    {
        $officer = Grade::where('code', 'OFF')->firstOrFail();
        $cl = LeaveType::where('code', 'CL')->firstOrFail();
        LeaveEntitlement::firstOrCreate(['leave_type_id' => $cl->id, 'grade_id' => $officer->id, 'employment_type' => null], ['days_per_year' => 12]);
        $year = $this->leaveYear->current();
        foreach ($employees as $e) {
            foreach (LeaveType::where('is_active', true)->get() as $type) {
                $this->balances->balance($e, $type, $year);
            }
        }
    }
}
