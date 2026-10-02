<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Tenant\Pages\AskHazriPage;
use App\Filament\Tenant\Widgets\BranchComparison;
use App\Filament\Tenant\Widgets\TodayStats;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Reports\BranchSummaryReport;
use App\Reports\DailyMusterReport;
use App\Services\Attendance\AttendanceImpact;
use App\Services\Settings\TenantSettings;
use App\Support\BranchAccess;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/* A shop owner with several shops: shop managers see only their own shop. */

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'feature.multi_branch'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->shop1 = Branch::create(['name' => 'Shop One', 'code' => 'SH1']);
    $this->shop2 = Branch::create(['name' => 'Shop Two', 'code' => 'SH2']);
    $this->ali = Employee::create(['code' => 'MB1', 'first_name' => 'Aliyan', 'joined_on' => '2025-01-01', 'branch_id' => $this->shop1->id]);
    $this->bilal = Employee::create(['code' => 'MB2', 'first_name' => 'Bilawal', 'joined_on' => '2025-01-01', 'branch_id' => $this->shop2->id]);
    $this->manager = User::create(['name' => 'Shop One manager', 'email' => 'shop1@pest.test', 'password' => 'secret123']);
    $this->manager->assignRole(Role::BranchManager->value);
    $this->manager->branches()->attach($this->shop1->id);
    $this->base = 'http://pest.' . config('hazri.central_domain');
});

afterEach(fn () => BranchAccess::clear());

it('shows a shop manager only their shop\'s employees in the admin panel', function () {
    $this->actingAs($this->manager)->get("{$this->base}/admin/employees")
        ->assertOk()->assertSee('Aliyan')->assertDontSee('Bilawal');
});

it('keeps another shop\'s records out of a shop manager\'s reports and lookups', function () {
    $this->actingAs($this->manager);
    foreach ([$this->ali, $this->bilal] as $e) {
        AttendanceDay::create(['employee_id' => $e->id, 'date' => '2026-09-01', 'status' => AttendanceStatus::Present, 'flags' => []]);
    }
    BranchAccess::restrictTo(BranchAccess::forUser($this->manager));

    expect(collect((new DailyMusterReport)->rows(['date' => '2026-09-01']))->pluck('code')->all())->toBe(['MB1'])
        ->and(AttendanceDay::count())->toBe(1)
        ->and(Employee::find($this->bilal->id))->toBeNull(); // so no manual entry or leave for the other shop either
});

it('lets owners, admins and everyone see all shops, and ignores branches when the feature is off', function () {
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $owner->branches()->attach($this->shop1->id);

    expect(BranchAccess::forUser($owner))->toBeNull()
        ->and(BranchAccess::forUser($this->manager))->toBe([$this->shop1->id]);

    Setting::updateOrCreate(['key' => 'feature.multi_branch'], ['value' => false]);
    app(TenantSettings::class)->flush();
    expect(BranchAccess::forUser($this->manager))->toBeNull();
});

it('still recomputes every shop when a shop manager triggers a company-wide change', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-02 12:00', 'Asia/Karachi'));
    BranchAccess::restrictTo([$this->shop1->id]);

    AttendanceImpact::dispatch(null, '2026-09-01', '2026-09-01'); // e.g. a company holiday was saved

    BranchAccess::clear();
    expect(AttendanceDay::where('employee_id', $this->bilal->id)->whereDate('date', '2026-09-01')->exists())->toBeTrue();
});

it('hides the AI assistant from shop managers because it reads all company data', function () {
    Setting::updateOrCreate(['key' => 'feature.ai_reports'], ['value' => true]);
    app(TenantSettings::class)->flush();
    BranchAccess::restrictTo([$this->shop1->id]);
    $this->actingAs($this->manager);
    $this->manager->givePermissionTo('reports.view');

    expect(AskHazriPage::canAccess())->toBeFalse();
});

it('compares shops on the dashboard and in the branch summary', function () {
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    AttendanceDay::create(['employee_id' => $this->ali->id, 'date' => '2026-09-01', 'status' => AttendanceStatus::Present, 'flags' => []]);
    AttendanceDay::create(['employee_id' => $this->bilal->id, 'date' => '2026-09-01', 'status' => AttendanceStatus::Absent, 'flags' => []]);

    // Dashboard widgets load lazily, so render the comparison widget itself.
    $this->actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    expect(BranchComparison::canView())->toBeTrue();
    Livewire::test(BranchComparison::class)->assertSee(['Branches today', 'Shop One', 'Shop Two']);

    $rows = collect((new BranchSummaryReport)->rows(['month' => '2026-09']))->keyBy('branch');
    expect($rows['Shop One'])->toMatchArray(['staff' => 1, 'present' => 1, 'absent' => 0, 'rate' => 100.0])
        ->and($rows['Shop Two'])->toMatchArray(['staff' => 1, 'present' => 0, 'absent' => 1, 'rate' => 0.0]);
});

it('filters the dashboard counters by the chosen shop', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 12:00', 'Asia/Karachi'));
    AttendanceDay::create(['employee_id' => $this->ali->id, 'date' => '2026-09-01', 'status' => AttendanceStatus::Present, 'flags' => []]);
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(TodayStats::class, ['pageFilters' => ['branch_id' => $this->shop2->id]])
        ->assertSee('of 1 active employees')->assertDontSee('of 2 active employees');
});
