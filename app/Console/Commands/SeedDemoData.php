<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payroll\PayrollActions;
use App\Actions\Periods\PeriodActions;
use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Enums\DevicePunchSourceType;
use App\Enums\EmploymentType;
use App\Enums\LeaveDayPart;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\UnmappedPin;
use App\Models\User;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Local demo only: fills a company with ~6 weeks of realistic attendance so every screen has something to show. */
#[Signature('hazri:demo:seed {slug=demo}')]
#[Description('Fill a company with demo employees, punches, leave, payroll (local testing only)')]
final class SeedDemoData extends Command
{
    private const FIRST = ['Ali', 'Sana', 'Bilal', 'Ayesha', 'Usman', 'Fatima', 'Hamza', 'Zainab', 'Imran', 'Hira',
        'Kamran', 'Mehwish', 'Faisal', 'Nida', 'Asad', 'Rabia', 'Tariq', 'Sadia', 'Waqas', 'Amna'];

    private const LAST = ['Khan', 'Ahmed', 'Malik', 'Butt', 'Sheikh', 'Qureshi', 'Raza', 'Iqbal', 'Hussain', 'Chaudhry'];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->firstOrFail();
        config(['queue.default' => 'sync']);
        mt_srand(42);

        app(SetTenantFeatureFlags::class)($tenant, array_fill_keys(config('hazri.feature_flags'), true));
        $tenant->update(['employee_limit' => null]);

        $tenant->run(fn () => $this->seed());

        $this->info('Demo data ready.');

        return self::SUCCESS;
    }

    private function seed(): void
    {
        $tz = tenant('timezone') ?? 'Asia/Karachi';
        app(TenantSettings::class)->flush();
        Setting::updateOrCreate(['key' => 'overtime_enabled'], ['value' => true]);

        $lhr = Branch::firstOrCreate(['code' => 'LHE'], ['name' => 'Lahore HQ', 'lat' => 31.5204, 'lng' => 74.3587, 'geofence_radius_m' => 200]);
        $khi = Branch::firstOrCreate(['code' => 'KHI'], ['name' => 'Karachi', 'lat' => 24.8607, 'lng' => 67.0011]);
        $depts = collect(['SAL' => 'Sales', 'ACC' => 'Accounts', 'OPS' => 'Operations', 'HRD' => 'HR'])
            ->map(fn ($name, $code) => Department::firstOrCreate(['code' => $code], ['name' => $name]))->values();
        $designations = collect(['Manager', 'Officer', 'Executive', 'Assistant'])->map(fn ($n) => Designation::firstOrCreate(['name' => $n]));

        // 20 employees; the first of each department is its manager.
        $employees = collect();
        foreach (self::FIRST as $i => $first) {
            $dept = $depts[$i % 4];
            $employees->push(Employee::firstOrCreate(['code' => sprintf('E%03d', $i + 1)], [
                'first_name' => $first, 'last_name' => self::LAST[$i % 10], 'device_pin' => (string) (101 + $i),
                'phone' => sprintf('0300%07d', 1000000 + $i), 'email' => strtolower($first) . '@demo.test',
                'branch_id' => $i < 15 ? $lhr->id : $khi->id, 'department_id' => $dept->id,
                'designation_id' => $designations[$i < 4 ? 0 : 1 + $i % 3]->id,
                'employment_type' => $i === 19 ? EmploymentType::DailyWage : EmploymentType::Permanent,
                'joined_on' => '2025-01-15', 'overtime_eligible' => $i % 3 === 0, 'gender' => $i % 2 ? 'female' : 'male',
                'dob' => sprintf('199%d-%02d-%02d', $i % 10, 1 + $i % 12, 1 + $i % 27),
            ]));
        }
        foreach ($employees->slice(4) as $i => $e) {
            $e->update(['manager_employee_id' => $employees[$i % 4]->id]);
        }

        $login = function (string $email, string $name, Role $role, ?Employee $e = null) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'employee_id' => $e?->id, 'phone' => $e?->phone]);
            $u->syncRoles([$role->value]);
        };
        $login('hr@demo.test', 'Hina HR', Role::Hr);
        $login('reception@demo.test', 'Reception', Role::Receptionist);
        $login('manager@demo.test', $employees[0]->fullName(), Role::Manager, $employees[0]);
        $login('employee@demo.test', $employees[4]->fullName(), Role::Employee, $employees[4]);

        foreach ($employees as $i => $e) {
            SalaryStructure::firstOrCreate(['employee_id' => $e->id, 'effective_from' => '2025-01-01'], $i === 19
                ? ['basic' => 0, 'per_day_rate' => 1800]
                : ['basic' => 60000 + 5000 * ($i % 5), 'allowances' => [['name' => 'House rent', 'amount' => 15000], ['name' => 'Conveyance', 'amount' => 5000]],
                    'deductions_fixed' => [['name' => 'EOBI', 'amount' => 370]], 'overtime_rate_normal' => 500, 'bank_name' => 'HBL',
                    'bank_account' => 'PK36HABB00000' . str_pad((string) $i, 11, '0', STR_PAD_LEFT)]);
        }

        $adms = Device::firstOrCreate(['serial_number' => 'DEMO-ADMS-01'], ['name' => 'Lahore reception K40', 'type' => DevicePunchSourceType::Adms,
            'branch_id' => $lhr->id, 'status' => 'online', 'last_seen_at' => now()]);
        Device::firstOrCreate(['serial_number' => 'DEMO-AGENT-01'], ['name' => 'Karachi office (agent)', 'type' => DevicePunchSourceType::Agent,
            'branch_id' => $khi->id, 'status' => 'offline', 'last_seen_at' => now()->subHours(3)]);
        $kioskDevice = Device::firstOrCreate(['serial_number' => 'KIOSK-DEMO'], ['name' => 'Reception tablet', 'type' => DevicePunchSourceType::Kiosk, 'branch_id' => $lhr->id]);
        $kiosk = Kiosk::firstOrCreate(['device_id' => $kioskDevice->id], ['token' => Kiosk::newToken(), 'allowed_modes' => ['pin', 'qr'], 'require_photo' => false]);

        // ~6 weeks of punches. Per day: mostly on time, some late, a few absent, a few missing punch-outs.
        $today = CarbonImmutable::now($tz);
        $start = $today->subMonthNoOverflow()->startOfMonth();
        foreach (CarbonPeriod::create($start, $today->subDay()) as $day) {
            if ($day->dayOfWeek === 0) {
                continue;
            }
            foreach ($employees as $i => $e) {
                $roll = mt_rand(1, 100);
                if ($roll <= 4) {
                    continue; // absent
                }
                $in = $day->copy()->setTimezone($tz)->setTime(8, 45)->addMinutes(mt_rand(0, 25) + ($roll > 85 ? mt_rand(20, 150) : 0));
                $out = $day->copy()->setTimezone($tz)->setTime(18, 0)->addMinutes(mt_rand(-20, 90));
                $this->punch($e, $adms, CarbonImmutable::parse($in->format('Y-m-d H:i:s'), $tz));
                if ($roll > 7) {
                    $this->punch($e, $adms, CarbonImmutable::parse($out->format('Y-m-d H:i:s'), $tz));
                }
            }
        }
        // Today: half the staff already in.
        foreach ($employees->take(12) as $e) {
            $this->punch($e, $adms, CarbonImmutable::parse($today->format('Y-m-d') . ' 09:0' . mt_rand(0, 9), $tz));
        }

        UnmappedPin::firstOrCreate(['device_id' => $adms->id, 'device_pin' => '999'], ['first_seen_at' => now(), 'count' => 3, 'device_user_name' => 'Guard Akram']);

        $cl = LeaveType::where('code', 'CL')->first();
        $sl = LeaveType::where('code', 'SL')->first();
        $balances = app(LeaveBalanceService::class);
        foreach ($employees as $e) {
            foreach ([$cl, $sl] as $t) {
                $balances->balance($e, $t, $today->year);
            }
        }
        $leaveDay = $today->subMonthNoOverflow()->startOfMonth()->addDays(9);
        LeaveRequest::firstOrCreate(['employee_id' => $employees[5]->id, 'from_date' => $leaveDay->format('Y-m-d')], [
            'leave_type_id' => $cl->id, 'to_date' => $leaveDay->format('Y-m-d'), 'days' => 1, 'status' => RequestStatus::Approved,
            'day_part' => LeaveDayPart::Full, 'reason' => 'Family event']);
        $next = $today->addDays(3);
        LeaveRequest::firstOrCreate(['employee_id' => $employees[4]->id, 'from_date' => $next->format('Y-m-d')], [
            'leave_type_id' => $sl->id, 'to_date' => $next->format('Y-m-d'), 'days' => 1, 'status' => RequestStatus::Pending,
            'day_part' => LeaveDayPart::Full, 'reason' => 'Doctor appointment',
            'approver_chain' => [['step' => 'manager', 'user_id' => User::where('email', 'manager@demo.test')->value('id'), 'decided_by' => null, 'decision' => null, 'at' => null, 'note' => null],
                ['step' => 'hr', 'user_id' => null, 'decided_by' => null, 'decision' => null, 'at' => null, 'note' => null]], 'current_step' => 0]);
        AttendanceCorrection::firstOrCreate(['employee_id' => $employees[6]->id, 'date' => $today->subDays(2)->format('Y-m-d')], [
            'type' => 'add_punch', 'payload' => ['time' => '18:10', 'direction' => 'out'], 'reason' => 'Forgot to punch out', 'status' => RequestStatus::Pending,
            'approver_chain' => [['step' => 'hr', 'user_id' => null, 'decided_by' => null, 'decision' => null, 'at' => null, 'note' => null]], 'current_step' => 0]);
        Announcement::firstOrCreate(['title' => 'Office timings during Rabi ul Awal'], ['body' => 'Office will close at 4 pm on 12 Rabi ul Awal.', 'requires_ack' => true, 'notify' => false]);

        $this->info('Recomputing attendance…');
        $recomputer = app(AttendanceRecomputer::class);
        $now = CarbonImmutable::now('UTC');
        foreach (CarbonPeriod::create($start, $today) as $day) {
            foreach ($employees as $e) {
                $recomputer->recompute($e->id, $day->format('Y-m-d'), $now);
            }
        }

        // Lock last month and calculate a draft payroll from it.
        $owner = User::role(Role::Owner->value)->first();
        $period = app(PeriodActions::class)->period((int) $start->format('Y'), (int) $start->format('m'));
        if ($period->status->value !== 'locked') {
            app(PeriodActions::class)->lock($period, $owner);
        }
        app(PayrollActions::class)->generate($period->fresh(), $owner);

        $this->line('Kiosk: http://' . tenant('slug') . '.' . config('hazri.central_domain') . ":8000/kiosk/{$kiosk->token}");
    }

    private function punch(Employee $e, Device $device, CarbonImmutable $local): void
    {
        $utc = $local->utc();
        Punch::query()->firstOrCreate(['dedupe_hash' => sha1("{$device->serial_number}|{$e->device_pin}|{$utc->format('Y-m-d H:i:s')}")], [
            'employee_id' => $e->id, 'device_pin' => $e->device_pin, 'device_id' => $device->id, 'punched_at' => $utc->format('Y-m-d H:i:s'),
            'local_date' => $local->format('Y-m-d'), 'source' => PunchSource::Agent, 'status' => PunchStatus::Valid, 'verify_type' => 'fp',
        ]);
    }
}
