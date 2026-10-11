<?php

declare(strict_types=1);

use App\Actions\Punches\CreateManualPunches;
use App\Actions\Rosters\ApproveShiftSwap;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\ShiftSwapStatus;
use App\Filament\Tenant\Pages\SwitchCompany;
use App\Filament\Tenant\Resources\ShiftSwaps\Pages\ManageShiftSwaps;
use App\Http\Controllers\SwitchCompanyController;
use App\Models\AttendanceDay;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSwitchLink;
use App\Models\Central\TenantUserLink;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Analytics\PunctualityScore;
use App\Services\Attendance\ShiftResolver;
use App\Services\Settings\TenantSettings;
use App\Support\Security\PublicUrl;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@pest.test')->first();
    $this->base = 'http://pest.' . config('hazri.central_domain');
    $this->evening = Shift::create(['name' => 'Evening', 'code' => 'EVE', 'start_time' => '14:00', 'end_time' => '22:00', 'break_min' => 30]);
});

it('detects the nearest shift from the first punch when enabled', function () {
    Setting::updateOrCreate(['key' => 'auto_shift_detection'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $e = Employee::create(['code' => 'AUTO', 'first_name' => 'Auto', 'joined_on' => '2025-01-01']);

    app(CreateManualPunches::class)($e, '2026-09-08', '14:05', '22:00', 'test', $this->owner);

    $day = AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-09-08')->first();
    expect($day->shift_id)->toBe($this->evening->id)->and($day->status)->toBe(AttendanceStatus::Present)->and($day->flags)->not->toContain('late');
});

it('swaps two employees\' shifts for one day', function () {
    $a = Employee::create(['code' => 'SWA', 'first_name' => 'A', 'joined_on' => '2025-01-01']);
    $b = Employee::create(['code' => 'SWB', 'first_name' => 'B', 'joined_on' => '2025-01-01']);
    ShiftAssignment::create(['employee_id' => $b->id, 'shift_id' => $this->evening->id, 'effective_from' => '2026-01-01']);
    $swap = ShiftSwapRequest::create(['requester_employee_id' => $a->id, 'partner_employee_id' => $b->id, 'date' => '2026-10-20']);

    app(ApproveShiftSwap::class)($swap, $this->owner, true);

    $resolver = app(ShiftResolver::class);
    expect($resolver->resolve($a, '2026-10-20', 'Asia/Karachi')->shiftId)->toBe($this->evening->id)
        ->and($resolver->resolve($b, '2026-10-20', 'Asia/Karachi')->shiftId)->toBe(Shift::where('is_default', true)->value('id'))
        ->and($resolver->resolve($a, '2026-10-21', 'Asia/Karachi')->shiftId)->not->toBe($this->evening->id);
});

it('refuses a swap between two people on the same shift and describes both shifts', function () {
    $a = Employee::create(['code' => 'SWC', 'first_name' => 'Cee', 'joined_on' => '2025-01-01']);
    $b = Employee::create(['code' => 'SWD', 'first_name' => 'Dee', 'joined_on' => '2025-01-01']);
    $swap = ShiftSwapRequest::create(['requester_employee_id' => $a->id, 'partner_employee_id' => $b->id, 'date' => '2026-10-20']);

    expect(fn () => app(ApproveShiftSwap::class)($swap, $this->owner, true))
        ->toThrow(ValidationException::class, 'Both are on the same shift that day, so there is nothing to swap.');

    ShiftAssignment::create(['employee_id' => $b->id, 'shift_id' => $this->evening->id, 'effective_from' => '2026-01-01']);
    expect(app(ApproveShiftSwap::class)->describe($a, $b, '2026-10-20'))->toContain('Cee')->toContain($this->evening->displayName())
        ->and($swap->fresh()->status)->toBe(ShiftSwapStatus::Pending)
        ->and(ShiftSwapStatus::Pending->getLabel())->toBe('Waiting for approval');
});

it('lets a manager see and decide only the shift swaps of their own team', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $boss = Employee::create(['code' => 'SWM', 'first_name' => 'Boss', 'joined_on' => '2025-01-01']);
    $mine = Employee::create(['code' => 'SWE', 'first_name' => 'Mine', 'joined_on' => '2025-01-01', 'manager_employee_id' => $boss->id]);
    $other = Employee::create(['code' => 'SWF', 'first_name' => 'Other', 'joined_on' => '2025-01-01']);
    $pal = Employee::create(['code' => 'SWG', 'first_name' => 'Pal', 'joined_on' => '2025-01-01']);
    ShiftAssignment::create(['employee_id' => $pal->id, 'shift_id' => $this->evening->id, 'effective_from' => '2026-01-01']);
    $teamSwap = ShiftSwapRequest::create(['requester_employee_id' => $mine->id, 'partner_employee_id' => $pal->id, 'date' => '2026-10-20']);
    $otherSwap = ShiftSwapRequest::create(['requester_employee_id' => $other->id, 'partner_employee_id' => $pal->id, 'date' => '2026-10-21']);
    $manager = User::create(['name' => 'Boss', 'email' => 'swapboss@pest.test', 'password' => 'x', 'employee_id' => $boss->id]);
    $manager->assignRole(Role::Manager->value);
    $this->actingAs($manager);

    Livewire::test(ManageShiftSwaps::class)
        ->assertCanSeeTableRecords([$teamSwap])
        ->assertCanNotSeeTableRecords([$otherSwap])
        ->assertActionHidden(CreateAction::class)
        ->callTableAction('approve', $teamSwap);

    expect($teamSwap->fresh()->status)->toBe(ShiftSwapStatus::Approved)
        ->and(fn () => app(ApproveShiftSwap::class)($otherSwap, $manager, true))
        ->toThrow(ValidationException::class, 'You cannot approve shift swaps.');
});

it('asks before approving a shift swap and needs a reason to reject one', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs($this->owner);
    $a = Employee::create(['code' => 'SWR', 'first_name' => 'Ar', 'joined_on' => '2025-01-01']);
    $b = Employee::create(['code' => 'SWS', 'first_name' => 'Bs', 'joined_on' => '2025-01-01']);
    ShiftAssignment::create(['employee_id' => $b->id, 'shift_id' => $this->evening->id, 'effective_from' => '2026-01-01']);
    $swap = ShiftSwapRequest::create(['requester_employee_id' => $a->id, 'partner_employee_id' => $b->id, 'date' => '2026-10-20']);

    $page = Livewire::test(ManageShiftSwaps::class)->mountTableAction('approve', $swap);
    expect($page->instance()->getMountedAction()->isConfirmationRequired())->toBeTrue()
        ->and($page->instance()->getMountedAction()->getModalHeading())->toBe('Approve this shift swap?')
        ->and($swap->fresh()->status)->toBe(ShiftSwapStatus::Pending);

    Livewire::test(ManageShiftSwaps::class)
        ->callTableAction('reject', $swap, data: ['note' => ''])
        ->assertHasTableActionErrors(['note' => 'required']);
    Livewire::test(ManageShiftSwaps::class)
        ->callTableAction('reject', $swap, data: ['note' => 'Short-staffed that night'])
        ->assertHasNoTableActionErrors();

    expect($swap->fresh())->status->toBe(ShiftSwapStatus::Rejected)->decision_note->toBe('Short-staffed that night')
        ->and(fn () => app(ApproveShiftSwap::class)(ShiftSwapRequest::create(['requester_employee_id' => $a->id, 'partner_employee_id' => $b->id, 'date' => '2026-10-22']), $this->owner, false))
        ->toThrow(ValidationException::class, 'Give a reason for rejecting the swap.');
});

it('scores punctuality', function () {
    $days = collect([
        new AttendanceDay(['status' => AttendanceStatus::Present, 'flags' => []]),
        new AttendanceDay(['status' => AttendanceStatus::Present, 'flags' => ['late']]),
        new AttendanceDay(['status' => AttendanceStatus::Absent, 'flags' => []]),
        new AttendanceDay(['status' => AttendanceStatus::WeeklyOff, 'flags' => []]),
    ]);

    expect(PunctualityScore::of($days))->toMatchArray(['score' => 50, 'working' => 3, 'late' => 1, 'absent' => 1]);
});

it('switches to another linked company only for linked emails', function () {
    $this->actingAs($this->owner)->get("{$this->base}/switch/not-a-linked-tenant")->assertForbidden();

    expect(TenantUserLink::where('email', 'owner@pest.test')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

it('rejects SSRF targets for tenant-configured URLs', function (string $url, bool $ok) {
    expect(PublicUrl::check($url) === null)->toBe($ok);
})->with([
    ['http://169.254.169.254/latest/meta-data', false],
    ['http://127.0.0.1:8080/', false],
    ['http://10.0.0.5/send', false],
    ['http://100.64.1.1/', false],
    ['ftp://example.com/', false],
    ['https://user:pass@8.8.8.8/', false],
    ['https://8.8.8.8/sms', true],
    ['http://[::ffff:127.0.0.1]/', false],        // IPv4-mapped IPv6
    ['http://[::ffff:a9fe:a9fe]/', false],         // mapped 169.254.169.254, hex form
    ['http://[::127.0.0.1]/', false],              // IPv4-compatible
    ['http://[64:ff9b::a00:1]/', false],           // NAT64 of 10.0.0.1
    ['http://[2002:7f00:1::]/', false],            // 6to4 of 127.0.0.1
    ['http://[::]/', false],
    ['http://[2606:4700:4700::1111]/', true],      // public IPv6
]);

it('never reads files outside the visitor photo folder for badges', function () {
    $v = Visitor::create(['name' => 'X', 'checked_in_at' => now(), 'badge_no' => 'V001', 'photo_path' => '../../.env']);

    $html = $this->actingAs($this->owner)->get("{$this->base}/visitors/{$v->id}/badge")->assertOk()->getContent();

    expect($html)->not->toContain('data:image');
});

it('renders the roster, analytics and scorecard pages', function () {
    $this->actingAs($this->owner);
    foreach (['/admin/roster', '/admin/analytics', '/admin/reports/punctuality'] as $path) {
        $this->get($this->base . $path)->assertOk();
    }
});

/** A company row without a database: enough for switch-pair checks that never open the target. */
function companyWithoutDatabase(string $slug): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create(['id' => (string) Str::uuid(), 'slug' => $slug, 'company_name' => ucfirst($slug)]));
}

it('refuses the takeover: an admin-made login with the victim email reaches only companies paired with this one', function () {
    $victimCompany = companyWithoutDatabase('pest-victim');
    $attackerCompany = companyWithoutDatabase('pest-attacker');
    try {
        TenantUserLink::link('victim@pest.test', $victimCompany->id); // provisioned owner of the victim company
        TenantSwitchLink::pair('victim@pest.test', $this->tenant->id, $attackerCompany->id); // attacker linked their own two companies
        $login = User::create(['name' => 'Fake', 'email' => 'victim@pest.test', 'password' => 'secret123']);
        $login->assignRole(Role::Owner->value);

        $this->actingAs($login)->get("{$this->base}/switch/{$victimCompany->id}")->assertForbidden();
        expect(SwitchCompanyController::optionsFor('victim@pest.test'))->toBe([$attackerCompany->id => 'Pest-attacker']);
    } finally {
        Tenant::withoutEvents(fn () => [$victimCompany->delete(), $attackerCompany->delete()]);
    }
});

it('switches both ways between two paired companies, and not on to a third paired only with the second', function () {
    $b = companyWithoutDatabase('pest-bee');
    $c = companyWithoutDatabase('pest-sea');
    try {
        TenantSwitchLink::pair('owner@pest.test', $this->tenant->id, $b->id);
        TenantSwitchLink::pair('owner@pest.test', $b->id, $c->id);

        expect(array_keys(SwitchCompanyController::optionsFor('owner@pest.test')))->toBe([$b->id])
            ->and(array_keys(SwitchCompanyController::optionsFor('owner@pest.test', $b->id)))->toEqualCanonicalizing([$this->tenant->id, $c->id])
            ->and(TenantSwitchLink::allows('owner@pest.test', $this->tenant->id, $c->id))->toBeFalse();
        $this->actingAs($this->owner)->get("{$this->base}/switch/{$c->id}")->assertForbidden();
    } finally {
        Tenant::withoutEvents(fn () => [$b->delete(), $c->delete()]);
    }
});

it('adds another company only with the password of the login there', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $links = TenantUserLink::count();

    Livewire::test(SwitchCompany::class)->set('address', 'pest')->set('password', 'x')->call('link')
        ->assertHasErrors('address')->assertSee('No other company found at that address.');
    Livewire::test(SwitchCompany::class)->set('address', 'nobody-here')->set('password', 'x')->call('link')
        ->assertHasErrors('address');

    expect(TenantUserLink::count())->toBe($links);
});
