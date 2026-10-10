<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Payroll\FinalSettlementActions;
use App\Actions\Payroll\PayrollActions;
use App\Actions\Periods\PeriodActions;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\PeriodStatus;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Models\Advance;
use App\Models\ApiToken;
use App\Models\Device;
use App\Models\Employee;
use App\Models\FinalSettlement;
use App\Models\PayItem;
use App\Models\Punch;
use App\Models\SalaryStructure;
use App\Models\SeparationReason;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Attendance\AttendanceRecomputer;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Last month locked and paid: EOBI / social security / PF members, a bonus and a fine, an advance being repaid,
 * payslips made final (PF ledger, payslip PDFs), a leaver's final settlement, plus an API token and a webhook.
 */
final class DemoPayrollSeeder
{
    use DemoSteps;

    public function __construct(
        private readonly PeriodActions $periods,
        private readonly PayrollActions $payroll,
        private readonly FinalSettlementActions $settlements,
        private readonly AttendanceRecomputer $recomputer,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Closure(string): void  $say
     */
    public function run(Collection $employees, CarbonImmutable $monthStart, CarbonImmutable $today, Closure $say): void
    {
        $this->say = $say;
        $owner = User::role(Role::Owner->value)->firstOrFail();
        $this->register($employees);
        $this->payItems($employees, $monthStart, $owner);
        $leaver = $this->leaver($monthStart);

        $period = $this->periods->period((int) $monthStart->format('Y'), (int) $monthStart->format('m'));
        if ($period->status !== PeriodStatus::Locked) {
            $this->periods->lock($period, $owner);
        }
        $run = $this->attempt('payroll run', fn () => $this->payroll->generate($period->fresh(), $owner));
        if ($run !== null && $run->status === 'draft') {
            $this->attempt('making payroll final', fn () => $this->payroll->finalise($run, $owner));
        }
        if (! FinalSettlement::where('employee_id', $leaver->id)->exists()) {
            $this->attempt('final settlement', fn () => $this->settlements->calculate($leaver, $leaver->left_on->format('Y-m-d'), $owner));
        }

        $this->integrations($owner);
    }

    /**
     * Statutory membership (set quietly: changing these doesn't change attendance): permanent staff are EOBI-
     * registered, officers are in the provident fund, workers have social security.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function register(Collection $employees): void
    {
        foreach ($employees as $i => $e) {
            $permanent = $e->employment_type === EmploymentType::Permanent;
            $e->forceFill([
                'eobi_registered' => $permanent, 'eobi_no' => $permanent ? sprintf('EOBI-%07d', 4400100 + $i) : null,
                'pf_member' => $permanent && $i < 8,
                'social_security_registered' => $i >= 12 && $i < 19, 'social_security_no' => $i >= 12 && $i < 19 ? sprintf('PESSI-%06d', 772000 + $i) : null,
                'ntn' => $i < 4 ? sprintf('%07d-%d', 3520100 + $i, $i) : null,
            ])->saveQuietly();
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function payItems(Collection $employees, CarbonImmutable $monthStart, User $owner): void
    {
        $month = ['year' => (int) $monthStart->format('Y'), 'month' => (int) $monthStart->format('m')];
        PayItem::firstOrCreate($month + ['employee_id' => $employees[0]->id, 'label' => 'Sales target bonus'],
            ['type' => 'earning', 'amount' => 15000, 'taxable' => true, 'created_by' => $owner->id]);
        PayItem::firstOrCreate($month + ['employee_id' => $employees[9]->id, 'label' => 'Arrears: increment from last month'],
            ['type' => 'earning', 'amount' => 4000, 'taxable' => true, 'created_by' => $owner->id]);
        PayItem::firstOrCreate($month + ['employee_id' => $employees[7]->id, 'label' => 'Office mobile bill above limit'],
            ['type' => 'deduction', 'amount' => 1200, 'taxable' => false, 'created_by' => $owner->id]);
        Advance::firstOrCreate(['employee_id' => $employees[10]->id, 'note' => 'Advance for sister\'s wedding'], [
            'amount' => 60000, 'issued_on' => $monthStart->subMonthNoOverflow()->addDays(4)->format('Y-m-d'), 'instalments' => 6, 'remaining' => 50000, 'status' => 'active',
        ]);
    }

    /** Javed resigned in the middle of last month: his attendance until then and a draft final settlement. */
    private function leaver(CarbonImmutable $monthStart): Employee
    {
        $lastDay = self::workday($monthStart->addDays(17), -1);
        $first = Employee::where('code', 'E001')->firstOrFail();
        $javed = Employee::firstOrCreate(['code' => 'E021'], [
            'first_name' => 'Javed', 'last_name' => 'Akhtar', 'device_pin' => '121', 'phone' => '03001000020',
            'email' => 'javed@' . tenant('slug') . '.test', 'branch_id' => $first->branch_id, 'department_id' => $first->department_id,
            'designation_id' => $first->designation_id, 'grade_id' => $first->grade_id, 'employment_type' => EmploymentType::Permanent,
            'joined_on' => '2023-03-01', 'gender' => 'male', 'payroll_id' => '320021', 'father_name' => 'Akhtar Ali', 'city' => 'Lahore',
        ]);
        SalaryStructure::firstOrCreate(['employee_id' => $javed->id, 'effective_from' => '2023-03-01'], [
            'basic' => 70000, 'allowances' => [['name' => 'House rent', 'amount' => 15000]], 'bank_name' => 'Meezan Bank', 'bank_account' => 'PK12MEZN0000000000210021',
        ]);

        $adms = Device::where('serial_number', self::serial('ADMS-01'))->firstOrFail();
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        foreach (CarbonPeriod::create($monthStart, $lastDay) as $day) {
            if ($day->dayOfWeek === 0) {
                continue;
            }
            foreach (['08:5' . ($day->day % 10), '18:0' . ($day->day % 10)] as $time) {
                $local = CarbonImmutable::parse($day->format('Y-m-d') . " {$time}", $tz);
                Punch::firstOrCreate(['dedupe_hash' => sha1("{$adms->serial_number}|121|{$local->utc()->format('Y-m-d H:i:s')}")], [
                    'employee_id' => $javed->id, 'device_pin' => '121', 'device_id' => $adms->id, 'punched_at' => $local->utc()->format('Y-m-d H:i:s'),
                    'local_date' => $day->format('Y-m-d'), 'source' => PunchSource::Adms, 'status' => PunchStatus::Valid, 'verify_type' => 'fp',
                ]);
            }
        }
        $now = CarbonImmutable::now('UTC');
        foreach (CarbonPeriod::create($monthStart, $monthStart->endOfMonth()) as $day) {
            $this->recomputer->recompute($javed->id, $day->format('Y-m-d'), $now);
        }
        $javed->forceFill(['status' => EmployeeStatus::Left, 'left_on' => $lastDay->format('Y-m-d'),
            'separation_reason_id' => SeparationReason::query()->value('id')])->saveQuietly();

        return $javed->fresh();
    }

    /** A payroll-software API token and a webhook with two past deliveries (made up: nothing is called). */
    private function integrations(User $owner): void
    {
        if (! ApiToken::query()->exists()) {
            ApiToken::issue('Payroll software (Sage)', ['read'], $owner->id);
        }
        $hook = WebhookEndpoint::firstOrCreate(['url' => 'https://erp.example.com/hooks/haziri'], [
            'secret' => Str::random(40), 'events' => ['punch.created', 'leave.decided', 'payroll.generated'], 'is_active' => true,
        ]);
        if (! WebhookDelivery::where('webhook_endpoint_id', $hook->id)->exists()) {
            foreach ([['leave.decided', 200], ['payroll.generated', 200]] as [$event, $status]) {
                WebhookDelivery::create(['webhook_endpoint_id' => $hook->id, 'event' => $event, 'payload' => ['demo' => true],
                    'response_status' => $status, 'response_body' => 'OK', 'attempts' => 1, 'status' => 'delivered']);
            }
        }
    }
}
