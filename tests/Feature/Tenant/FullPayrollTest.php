<?php

declare(strict_types=1);

use App\Actions\Payroll\FinalSettlementActions;
use App\Actions\Payroll\PayrollActions;
use App\Actions\Periods\PeriodActions;
use App\Enums\AttendanceStatus;
use App\Enums\PeriodStatus;
use App\Filament\Tenant\Resources\PayItems\Pages\ManagePayItems;
use App\Filament\Tenant\Resources\Periods\Pages\ListPeriods;
use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PayItem;
use App\Models\PfLedgerEntry;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\Payroll\GratuityCalculator;
use App\Services\Payroll\IncomeTax;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/* Payroll Full: income tax, EOBI / social security, provident fund, pay items, gratuity, final settlement. */

function payrollFeatures(array $flags): void
{
    foreach ($flags as $flag => $on) {
        Setting::updateOrCreate(['key' => "feature.{$flag}"], ['value' => $on]);
    }
    app(TenantSettings::class)->flush();
}

/** Every day of the month present except Sundays (weekly off). */
function fullMonth(Employee $e, int $year, int $month, int $lastDay = 31): void
{
    foreach (range(1, min($lastDay, CarbonImmutable::create($year, $month, 1)->daysInMonth)) as $d) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        AttendanceDay::create(['employee_id' => $e->id, 'date' => $date, 'flags' => [],
            'status' => date('w', strtotime($date)) === '0' ? AttendanceStatus::WeeklyOff : AttendanceStatus::Present]);
    }
}

beforeEach(function () {
    payrollFeatures(['payroll_lite' => true, 'payroll_tax' => true, 'payroll_social' => true, 'payroll_pf' => true,
        'payroll_gratuity' => true, 'payroll_adjustments' => true, 'payroll_settlement' => true]);
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $this->emp = Employee::create(['code' => 'FP1', 'first_name' => 'Faisal', 'joined_on' => '2025-01-01',
        'eobi_registered' => true, 'social_security_registered' => true, 'pf_member' => true]);
    SalaryStructure::create(['employee_id' => $this->emp->id, 'effective_from' => '2025-01-01', 'basic' => 100000,
        'allowances' => [['name' => 'House rent', 'amount' => 40000, 'taxable' => true], ['name' => 'Medical', 'amount' => 10000, 'taxable' => false]]]);
    $this->periods = app(PeriodActions::class);
    $this->payroll = app(PayrollActions::class);
    $this->runFor = function (int $year, int $month) {
        $this->travelTo(now()->setDate($year, $month, 1)->addMonth()->setDay(5));
        $period = $this->periods->period($year, $month);
        $this->periods->lock($period, $this->owner);

        return $this->payroll->generate($period->fresh(), $this->owner);
    };
});

it('works out salary income tax from the slabs, with the high-income surcharge', function () {
    $tax = app(IncomeTax::class);

    expect($tax->annualTax(600000, 2025))->toBe(0)
        ->and($tax->annualTax(1000000, 2025))->toBe(4000)              // 1% above 600k
        ->and($tax->annualTax(3000000, 2025))->toBe(300000)            // 116k + 23% above 2.2m
        ->and($tax->annualTax(12000000, 2025))->toBe(3685290)          // (616k + 35% above 4.1m) × 1.09
        ->and($tax->taxYearFor('2026-06-30'))->toBe(2025)
        ->and($tax->monthsLeft('2026-08-31'))->toBe(11)
        ->and($tax->monthsLeft('2026-06-30'))->toBe(1);
});

it('uses the 2026-27 rates from July 2026, without the surcharge', function () {
    $tax = app(IncomeTax::class);

    expect($tax->annualTax(1500000, 2026))->toBe(39000)              // 6,000 + 11% above 1.2m
        ->and($tax->annualTax(3000000, 2026))->toBe(276000)          // 116k + 20% above 2.2m (was 23%)
        ->and($tax->annualTax(6000000, 2026))->toBe(1104000)         // 976k + 32% above 5.6m (new band)
        ->and($tax->annualTax(12000000, 2026))->toBe(3174000)        // 1,424k + 35% above 7m, no 9% surcharge
        ->and($tax->annualTax(12000000, 2025))->toBe(3685290);       // the surcharge still applies to 2025-26
});

it('warns on the payroll run when the rates for its tax year are missing', function () {
    TaxSlab::where('tax_year', 2026)->delete();
    fullMonth($this->emp, 2026, 8);

    $run = ($this->runFor)(2026, 8);

    expect($run->totals['tax_rates_missing'] ?? null)->toBe(2026)
        ->and(app(IncomeTax::class)->slabYear(2026))->toBe(2025); // last year's rates are used meanwhile
});

it('adds bonuses, provident fund, EOBI, social security and income tax to the payslip', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    PayItem::create(['employee_id' => $this->emp->id, 'year' => 2026, 'month' => 8, 'type' => 'earning', 'label' => 'Independence Day bonus', 'amount' => 10000]);

    $run = ($this->runFor)(2026, 8);
    $slip = $run->payslips()->where('employee_id', $this->emp->id)->firstOrFail();
    $by = collect($slip->lines)->keyBy('code');

    // Taxable 150,000 (medical exempt) × 11 months left → 1,650,000 → 6,000 + 11% above 1.2m = 55,500 ÷ 11
    expect($by['pay_item']['amount'])->toBe(10000)
        ->and($by['pf_ee']['amount'])->toBe(8330)->and($by['pf_er']['amount'])->toBe(8330)
        ->and($by['eobi_ee']['amount'])->toBe(370)->and($by['eobi_er']['amount'])->toBe(1850)
        ->and($by['ss_er']['amount'])->toBe(2220)
        ->and($by['tax']['amount'])->toBe(5045)
        ->and($slip->gross)->toBe(160000)
        ->and($slip->deductions)->toBe(8330 + 370 + 5045)                // company shares are not deducted
        ->and($slip->net)->toBe(160000 - 13745)
        ->and($slip->meta['taxable'])->toBe(150000);

    $this->payroll->finalise($run->fresh(), $this->owner);
    expect(PfLedgerEntry::balance($this->emp->id))->toBe(['employee' => 8330, 'employer' => 8330, 'total' => 16660]);
});

it('spreads the remaining tax over the rest of the tax year using earlier months', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    fullMonth($this->emp, 2026, 9);
    PayItem::create(['employee_id' => $this->emp->id, 'year' => 2026, 'month' => 8, 'type' => 'earning', 'label' => 'Bonus', 'amount' => 10000]);
    $this->payroll->finalise(($this->runFor)(2026, 8)->fresh(), $this->owner);

    $september = ($this->runFor)(2026, 9)->payslips()->where('employee_id', $this->emp->id)->firstOrFail();

    // 150,000 already + 140,000 × 10 months = 1,550,000 → 44,500 a year; 5,045 paid → 39,455 ÷ 10
    expect(collect($september->lines)->firstWhere('code', 'tax')['amount'])->toBe(3946)
        ->and($september->meta['ytd_tax'])->toBe(5045 + 3946);
});

it('counts a one-off bonus in the yearly income once and taxes it in the bonus month', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    fullMonth($this->emp, 2026, 9);
    PayItem::create(['employee_id' => $this->emp->id, 'year' => 2026, 'month' => 8, 'type' => 'earning', 'label' => 'Bonus', 'amount' => 300000]);

    $august = ($this->runFor)(2026, 8);
    $slip = $august->payslips()->where('employee_id', $this->emp->id)->firstOrFail();

    // Regular 140,000 × 11 = 1,540,000 → 43,400 a year (3,945 a month); with the bonus once 1,840,000 → 76,400: 33,000 more now
    expect($slip->meta['projected_income'])->toBe(1840000)
        ->and($slip->meta['tax'])->toBe(3945 + 33000);

    $this->payroll->finalise($august->fresh(), $this->owner);
    $september = ($this->runFor)(2026, 9)->payslips()->where('employee_id', $this->emp->id)->firstOrFail();

    // 440,000 already + 140,000 × 10 = 1,840,000 → 76,400; 36,945 paid → 39,455 ÷ 10
    expect($september->meta['tax'])->toBe(3946);
});

it('pays someone who joined or left during the month only for the days employed', function () {
    payrollFeatures(['payroll_tax' => false, 'payroll_social' => false, 'payroll_pf' => false]);
    $joiner = Employee::create(['code' => 'FP2', 'first_name' => 'Nadia', 'joined_on' => '2026-08-17']);
    $leaver = Employee::create(['code' => 'FP3', 'first_name' => 'Omar', 'joined_on' => '2025-01-01']);
    $leaver->forceFill(['left_on' => '2026-08-10'])->saveQuietly();
    foreach ([$joiner, $leaver] as $e) {
        SalaryStructure::create(['employee_id' => $e->id, 'effective_from' => '2025-01-01', 'basic' => 31000]);
    }
    fullMonth($this->emp, 2026, 8);
    fullMonth($leaver, 2026, 8, 10);
    foreach (range(17, 31) as $d) {
        AttendanceDay::create(['employee_id' => $joiner->id, 'date' => sprintf('2026-08-%02d', $d), 'flags' => [], 'status' => AttendanceStatus::Present]);
    }

    $slips = ($this->runFor)(2026, 8)->payslips()->get()->keyBy('employee_id');

    // 31,000 ÷ 31 = 1,000 a day: joined on the 17th → 15 days; left on the 10th → 10 days
    expect($slips[$joiner->id]->net)->toBe(15000)
        ->and(collect($slips[$joiner->id]->lines)->firstWhere('code', 'not_employed')['amount'])->toBe(16000)
        ->and($slips[$leaver->id]->net)->toBe(10000)
        ->and($slips[$this->emp->id]->net)->toBe(150000);
});

it('leaves statutory lines off when those features are switched off', function () {
    payrollFeatures(['payroll_tax' => false, 'payroll_social' => false, 'payroll_pf' => false]);
    fullMonth($this->emp, 2026, 8);

    $codes = collect(($this->runFor)(2026, 8)->payslips()->firstOrFail()->lines)->pluck('code');

    expect($codes)->not->toContain('tax')->not->toContain('pf_ee')->not->toContain('eobi_ee')->not->toContain('ss_er');
});

it('counts gratuity in years of service, rounding a long part year up', function () {
    expect(app(GratuityCalculator::class)->accrued($this->emp, '2026-10-15'))   // 1 year 9 months → 2 years
        ->toBe(['years' => 2, 'monthly_basis' => 100000, 'amount' => 200000])
        ->and(app(GratuityCalculator::class)->accrued($this->emp, '2025-05-31')['amount'])->toBe(0); // under a year
});

it('settles a leaving employee in full and final', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 10, 15);
    LeaveType::where('code', 'CL')->update(['encashable' => true]); // 10 days unused
    PfLedgerEntry::create(['employee_id' => $this->emp->id, 'entry_date' => '2026-01-31', 'type' => 'opening', 'employee_amount' => 50000, 'employer_amount' => 50000]);
    Advance::create(['employee_id' => $this->emp->id, 'amount' => 9000, 'instalments' => 3, 'remaining' => 9000, 'issued_on' => '2026-09-01', 'status' => 'active']);
    $this->emp->forceFill(['left_on' => '2026-10-15'])->saveQuietly(); // no recompute of the hand-made attendance rows
    $actions = app(FinalSettlementActions::class);

    $settlement = $actions->calculate($this->emp, '2026-10-15', $this->owner);
    $settlement = $actions->updateManualLines($settlement, [['label' => 'Notice period not served', 'type' => 'deduction', 'amount' => 20000]]);
    $by = collect($settlement->lines)->keyBy('code');

    expect($by['salary']['amount'])->toBe(15 * 4839)            // 150,000 ÷ 31 days, 15 days worked
        ->and($by['leave_encashment']['amount'])->toBe(10 * 4839) // same per-day rate: 10 days × 4,839
        ->and($by['gratuity']['amount'])->toBe(200000)
        ->and($by['pf']['amount'])->toBe(100000)
        ->and($by['advance']['amount'])->toBe(9000)
        ->and($settlement->net)->toBe(72585 + 48390 + 200000 + 100000 - 9000 - 20000);

    $actions->finalise($settlement, $this->owner);
    expect($settlement->fresh()->status)->toBe('final')
        ->and(Advance::where('employee_id', $this->emp->id)->value('status'))->toBe('settled')
        ->and(PfLedgerEntry::balance($this->emp->id)['total'])->toBe(0)
        ->and(Storage::disk('local')->exists($settlement->fresh()->pdf_path))->toBeTrue();
});

it('values settlement salary and leave encashment with the per-day rule from Payroll rules', function () {
    fullMonth($this->emp, 2026, 10, 15);
    LeaveType::where('code', 'CL')->update(['encashable' => true]); // 10 days unused
    Setting::updateOrCreate(['key' => 'payroll_day_divisor'], ['value' => '30']);
    app(TenantSettings::class)->flush();
    $this->emp->forceFill(['left_on' => '2026-10-15'])->saveQuietly();

    $by = collect(app(FinalSettlementActions::class)->calculate($this->emp, '2026-10-15', $this->owner)->lines)->keyBy('code');

    expect($by['salary']['amount'])->toBe(15 * 5000)              // 150,000 ÷ 30
        ->and($by['leave_encashment']['amount'])->toBe(10 * 5000);
});

it('does not pay a leaver twice when the settlement is made before the payroll run', function () {
    fullMonth($this->emp, 2026, 10, 15);
    $this->emp->forceFill(['left_on' => '2026-10-15'])->saveQuietly();
    $settlement = app(FinalSettlementActions::class)->calculate($this->emp, '2026-10-15', $this->owner);

    $run = ($this->runFor)(2026, 10);

    expect(collect($settlement->lines)->pluck('code'))->toContain('salary')
        ->and($run->payslips()->where('employee_id', $this->emp->id)->exists())->toBeFalse()
        ->and($run->totals['paid_in_settlement'])->toBe(1);
});

it('does not pay a leaver twice when the payroll run is made before the settlement', function () {
    fullMonth($this->emp, 2026, 10, 15);
    $this->emp->forceFill(['left_on' => '2026-10-15'])->saveQuietly();

    $run = ($this->runFor)(2026, 10);
    $settlement = app(FinalSettlementActions::class)->calculate($this->emp, '2026-10-15', $this->owner);

    expect($run->payslips()->where('employee_id', $this->emp->id)->exists())->toBeTrue()
        ->and(collect($settlement->lines)->pluck('code'))->not->toContain('salary');
});

it('records a bonus for the chosen pay month from the screen', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);

    Livewire::test(ManagePayItems::class)
        ->callAction('create', data: ['employee_id' => $this->emp->id, 'pay_month' => '2026-12', 'type' => 'earning', 'label' => 'Year-end bonus', 'amount' => 25000, 'taxable' => true])
        ->assertHasNoActionErrors();

    expect(PayItem::where('label', 'Year-end bonus')->first()?->only(['year', 'month', 'amount', 'created_by']))
        ->toBe(['year' => 2026, 'month' => 12, 'amount' => 25000, 'created_by' => $this->owner->id]);
});

it('refuses a bonus for a month whose payroll is final', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    $item = PayItem::create(['employee_id' => $this->emp->id, 'year' => 2026, 'month' => 8, 'type' => 'earning', 'label' => 'Bonus', 'amount' => 1000]);
    $this->payroll->finalise(($this->runFor)(2026, 8)->fresh(), $this->owner);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);

    Livewire::test(ManagePayItems::class)
        ->callAction('create', data: ['employee_id' => $this->emp->id, 'pay_month' => '2026-08', 'type' => 'earning', 'label' => 'Late bonus', 'amount' => 5000, 'taxable' => true])
        ->assertHasActionErrors(['pay_month'])
        ->assertTableActionHidden('edit', $item)
        ->assertTableActionHidden('delete', $item);

    expect(PayItem::where('label', 'Late bonus')->exists())->toBeFalse();
});

it('asks for confirmation before unlocking a month whose payroll is final', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    $run = ($this->runFor)(2026, 8);
    $this->payroll->finalise($run->fresh(), $this->owner);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);

    Livewire::test(ListPeriods::class)
        ->callTableAction('unlock', $run->period, data: ['reason' => 'Late correction'])
        ->assertHasTableActionErrors(['final_payroll_understood']);
    expect($run->period->fresh()->status)->toBe(PeriodStatus::Locked);

    Livewire::test(ListPeriods::class)
        ->callTableAction('unlock', $run->period, data: ['reason' => 'Late correction', 'final_payroll_understood' => true])
        ->assertHasNoTableActionErrors();
    expect($run->period->fresh()->status)->toBe(PeriodStatus::Review);
});

it('prints a bank letter for a payroll run', function () {
    Storage::fake('local');
    fullMonth($this->emp, 2026, 8);
    SalaryStructure::where('employee_id', $this->emp->id)->update(['bank_name' => 'HBL']);

    $pdf = $this->payroll->bankLetter(($this->runFor)(2026, 8), 'HBL Main Boulevard');

    expect(substr($pdf, 0, 4))->toBe('%PDF');
});
