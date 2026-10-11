<?php

declare(strict_types=1);

use App\Actions\Announcements\BroadcastAnnouncement;
use App\Actions\Payroll\PayrollActions;
use App\Actions\Periods\PeriodActions;
use App\Actions\Rosters\ApproveShiftSwap;
use App\Actions\SelfService\DecidePendingPunch;
use App\Enums\AttendanceStatus;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Kiosk;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationLog;
use App\Models\Punch;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Attendance\ShiftResolver;
use App\Services\SelfService\GeoFence;
use App\Services\SelfService\QrBadge;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Office hours, so punches fall inside the day shift's punch window whatever time CI runs.
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    foreach (['mobile_punch', 'kiosk'] as $f) {
        Setting::updateOrCreate(['key' => "feature.{$f}"], ['value' => true]);
    }
    app(TenantSettings::class)->flush();
    $this->office = Branch::create(['name' => 'HQ', 'code' => 'HQ', 'lat' => 31.5204, 'lng' => 74.3587, 'geofence_radius_m' => 150]);
    $this->emp = Employee::create(['code' => 'SS1', 'first_name' => 'Sana', 'joined_on' => '2025-01-01', 'device_pin' => '501',
        'branch_id' => $this->office->id, 'phone' => '03001234567']);
    $this->user = User::create(['name' => 'Sana', 'email' => 'sana@pest.test', 'password' => 'secret123', 'employee_id' => $this->emp->id]);
    $this->user->assignRole(Role::Employee->value);
    $this->base = 'http://pest.' . config('hazri.central_domain');
});

afterEach(fn () => DeviceRegistry::where('serial_number', 'like', 'KIOSK-%')->delete());

it('logs employees in with email and password and serves the app', function () {
    $this->get("{$this->base}/me/login")->assertOk()->assertSee('Send code');
    $this->post("{$this->base}/me/login", ['email' => 'sana@pest.test', 'password' => 'secret123'])->assertRedirect(route('me.home'));
    $this->get("{$this->base}/me")->assertOk()->assertSee('Sana');
    $this->getJson("{$this->base}/me/attendance")->assertOk()->assertJsonPath('employee.code', 'SS1')->assertJsonCount(30, 'calendar');
});

it('logs employees in with a phone OTP', function () {
    Log::spy();
    $this->post("{$this->base}/me/otp", ['phone' => '0300-1234567'])->assertRedirect();

    $code = null;
    Log::shouldHaveReceived('info')->withArgs(function (string $msg, array $ctx = []) use (&$code) {
        if (preg_match('/code is (\d{6})/', $ctx['body'] ?? '', $m)) {
            $code = $m[1];
        }

        return true;
    });
    expect($code)->not->toBeNull();

    $this->post("{$this->base}/me/otp/verify", ['phone' => '03001234567', 'code' => '000000'])->assertSessionHasErrors('code');
    $this->post("{$this->base}/me/otp/verify", ['phone' => '03001234567', 'code' => $code])->assertRedirect(route('me.home'));
    $this->assertAuthenticatedAs($this->user);
});

it('keeps plain employees out of the admin panel but in /me', function () {
    $this->actingAs($this->user)->get("{$this->base}/admin")->assertForbidden();
    $this->actingAs(User::where('email', 'owner@pest.test')->first())->get("{$this->base}/me")->assertRedirect(route('me.login'));
});

it('accepts a mobile punch inside the geofence and queues one outside for HR', function () {
    $this->actingAs($this->user);
    $inside = $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 12]);
    $inside->assertOk()->assertJson(['outcome' => 'stored', 'direction' => 'in', 'inside_geofence' => true]);

    $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588])->assertStatus(422); // 60 s rate limit

    $this->travel(2)->minutes();
    $outside = $this->postJson("{$this->base}/me/punch", ['lat' => 31.60, 'lng' => 74.40, 'accuracy' => 20]);
    $outside->assertOk()->assertJson(['outcome' => 'pending_approval', 'direction' => 'out', 'inside_geofence' => false])
        ->assertJsonPath('message', fn (string $m) => preg_match('/^Saved at \d\d:\d\d, but you are \d+ m from the office \(allowed 150 m\)\. HR will review it\.$/', $m) === 1);

    $pending = Punch::where('status', PunchStatus::Pending)->firstOrFail();
    expect($pending->meta['geofence_fail'])->toBeTrue();

    $hr = User::create(['name' => 'HR', 'email' => 'hr-ss@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);
    app(DecidePendingPunch::class)($pending, $hr, true);
    expect($pending->fresh()->status)->toBe(PunchStatus::Valid)
        ->and(AttendanceDay::where('employee_id', $this->emp->id)->whereDate('date', now('Asia/Karachi')->format('Y-m-d'))->value('flags'))
        ->toContain('geofence_fail');
});

it('tells staff the real reason a phone punch was not simply stored', function () {
    $this->actingAs($this->user);
    $this->office->update(['lat' => null, 'lng' => null]);
    $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 12])
        ->assertOk()->assertJson(['outcome' => 'pending_approval', 'message' => 'Saved at 10:00, but your branch has no office location set. HR will review it.']);

    // A machine punch 30 s before: the phone punch is a double tap, not an office-internet problem.
    $this->travel(2)->minutes();
    Punch::create(['employee_id' => $this->emp->id, 'punched_at' => now()->utc()->subSeconds(30), 'local_date' => '2026-09-08',
        'source' => 'adms', 'direction' => 'unknown', 'status' => 'valid', 'dedupe_hash' => md5('machine-30s')]);
    $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 12])
        ->assertOk()->assertJson(['outcome' => 'double_tap', 'message' => 'You had already punched a moment ago, so this one was not counted again.']);
});

it('lets field and remote staff punch anywhere', function (string $mode) {
    $this->emp->update(['work_mode' => $mode]);
    $this->office->update(['allowed_ips' => ['39.45.10.22']]);

    $this->actingAs($this->user)->withServerVariables(['REMOTE_ADDR' => '110.93.1.5'])
        ->postJson("{$this->base}/me/punch", ['lat' => 24.86, 'lng' => 67.00])
        ->assertJson(['outcome' => 'stored', 'inside_geofence' => true, 'office_network' => null]);
})->with(['field', 'remote']);

it('punches on the kiosk by PIN, asks for the kiosk PIN when set, and dedupes offline replays', function () {
    $device = Device::create(['name' => 'Tab', 'serial_number' => 'KIOSK-TEST1', 'type' => DevicePunchSourceType::Kiosk]);
    $kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin', 'qr'], 'require_photo' => false,
        'branch_id' => $this->office->id]);
    $url = "{$this->base}/kiosk/{$kiosk->token}";

    $this->get($url)->assertOk();
    $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '999'])->assertStatus(422);
    $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501'])->assertOk()->assertJson(['ok' => true, 'name' => 'Sana', 'direction' => 'in']);

    $this->emp->update(['kiosk_pin' => '4321']);
    $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501'])->assertStatus(422)->assertJsonPath('message', 'Enter your 4-digit kiosk PIN.');
    $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501', 'kiosk_pin' => '0000'])->assertStatus(422);

    $offline = ['mode' => 'pin', 'pin' => '501', 'kiosk_pin' => '4321', 'offline' => true,
        'client_time' => now()->subMinutes(30)->toIso8601String(), 'client_id' => (string) Str::uuid()];
    $this->postJson("{$url}/punch", $offline)->assertOk()->assertJson(['replay' => false]);
    $this->postJson("{$url}/punch", $offline)->assertOk()->assertJson(['replay' => true]);

    $other = Branch::create(['name' => 'Other', 'code' => 'OT']);
    $this->emp->update(['branch_id' => $other->id]);
    $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501', 'kiosk_pin' => '4321'])->assertStatus(422);
});

it('accepts today\'s QR badge only', function () {
    $badge = app(QrBadge::class);
    $today = $badge->token($this->emp);
    $yesterday = $badge->token($this->emp, CarbonImmutable::now('Asia/Karachi')->subDay());

    expect($badge->verify($today))->toBe($this->emp->id)
        ->and($badge->verify($yesterday))->toBeNull()
        ->and($badge->verify(substr($today, 0, -1) . 'x'))->toBeNull()
        ->and($badge->svg($this->emp))->toContain('<svg');
});

it('hides the kiosk when the feature is off', function () {
    Setting::where('key', 'feature.kiosk')->update(['value' => json_encode(false)]);
    app(TenantSettings::class)->flush();

    $this->get("{$this->base}/kiosk/" . str_repeat('a', 40))->assertNotFound();
});

it('shows announcements to their audience and records acknowledgements', function () {
    $all = Announcement::create(['title' => 'Eid timings', 'body' => 'Office closes at 1pm', 'requires_ack' => true]);
    Announcement::create(['title' => 'Karachi only', 'body' => 'x', 'audience' => ['branch_ids' => [999]]]);

    $this->actingAs($this->user)->getJson("{$this->base}/me/attendance")->assertJsonCount(1, 'announcements');
    $this->postJson("{$this->base}/me/announcements/{$all->id}/ack")->assertOk();

    expect($all->acks()->count())->toBe(1);
});

it('computes geofence distances', function () {
    expect(GeoFence::metres(31.5204, 74.3587, 31.5204, 74.3587))->toBe(0.0)
        ->and(round(GeoFence::metres(31.5204, 74.3587, 31.5294, 74.3587)))->toBe(1001.0);
});

it('shows notices on the kiosk after a punch until they are acknowledged there', function () {
    $device = Device::create(['name' => 'Tab', 'serial_number' => 'KIOSK-NOTICE', 'type' => DevicePunchSourceType::Kiosk]);
    $kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin'], 'require_photo' => false]);
    $url = "{$this->base}/kiosk/{$kiosk->token}";
    $policy = Announcement::create(['title' => 'New leave policy', 'body' => 'Read it', 'requires_ack' => true]);
    Announcement::create(['title' => 'Closed Friday', 'body' => 'x']);
    $old = Announcement::create(['title' => 'Old news', 'body' => 'x']);
    $old->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

    $first = $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501'])->assertOk();
    expect(collect($first->json('announcements'))->pluck('title')->sort()->values()->all())->toBe(['Closed Friday', 'New leave policy']);

    $ackUrl = collect($first->json('announcements'))->firstWhere('id', $policy->id)['ack_url'];
    $this->postJson($ackUrl)->assertOk();
    $this->postJson(str_replace('signature=', 'signature=x', $ackUrl))->assertForbidden(); // forged / changed link
    expect($policy->acks()->where('employee_id', $this->emp->id)->exists())->toBeTrue();

    $this->travel(5)->minutes();
    $second = $this->postJson("{$url}/punch", ['mode' => 'pin', 'pin' => '501'])->assertOk();
    expect(collect($second->json('announcements'))->pluck('title')->all())->toBe(['Closed Friday']);
});

it('sends an announcement only by the channels chosen and counts who they reach', function () {
    Mail::fake();
    $announcement = Announcement::create(['title' => 'By email', 'body' => 'x', 'notify' => true, 'channels' => ['mail']]);

    app(BroadcastAnnouncement::class)($announcement);

    expect(NotificationLog::where('dedupe_key', "announcement:{$announcement->id}")->pluck('channel')->unique()->values()->all())->toBe(['mail'])
        ->and(BroadcastAnnouncement::reach([]))->toMatchArray(['employees' => 1, 'app' => 1, 'email' => 1, 'phone' => 1])
        ->and(array_keys(BroadcastAnnouncement::availableChannels()))->toBe(['database', 'mail']);

    Setting::updateOrCreate(['key' => 'sms_provider'], ['value' => 'http']);
    app(TenantSettings::class)->flush();
    expect(array_keys(BroadcastAnnouncement::availableChannels()))->toContain('sms');
});

it('prints an announcement as an A4 notice with an Urdu heading', function () {
    $announcement = Announcement::create(['title' => 'Eid holidays', 'body' => 'Factory closed 3 days']);
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();

    $this->actingAs($owner)->get("{$this->base}/announcements/{$announcement->id}/print")
        ->assertOk()->assertSee('Eid holidays')->assertSee('اطلاع');
    $this->actingAs($this->user)->get("{$this->base}/announcements/{$announcement->id}/print")->assertForbidden();
});

it('makes an optional holiday a day off only for those who take it', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    $other = Employee::create(['code' => 'SS2', 'first_name' => 'Other', 'joined_on' => '2025-01-01', 'branch_id' => $this->office->id]);
    $diwali = Holiday::create(['date' => '2026-09-15', 'name' => 'Diwali', 'type' => 'optional']);

    $this->actingAs($this->user)->getJson("{$this->base}/me/attendance")
        ->assertJsonPath('optional_holidays.0.name', 'Diwali')->assertJsonPath('optional_holidays.0.taken', false);
    $this->postJson("{$this->base}/me/optional-holidays/{$diwali->id}")->assertOk()->assertJsonPath('taken', true);

    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00', 'Asia/Karachi'));
    $recompute = app(AttendanceRecomputer::class);
    foreach ([$this->emp, $other] as $e) {
        $recompute->recompute($e->id, '2026-09-15', CarbonImmutable::now('UTC'));
    }
    $status = fn (Employee $e) => AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-09-15')->value('status');

    expect($status($this->emp))->toBe(AttendanceStatus::Holiday)
        ->and($status($other))->toBe(AttendanceStatus::Absent);
});

it('lets an employee ask a colleague to swap shifts; the colleague accepts, then HR approves', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    Mail::fake();
    $night = Shift::create(['name' => 'Night', 'code' => 'NGT', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true]);
    $pal = Employee::create(['code' => 'SS3', 'first_name' => 'Pal', 'joined_on' => '2025-01-01', 'branch_id' => $this->office->id]);
    ShiftAssignment::create(['employee_id' => $pal->id, 'shift_id' => $night->id, 'effective_from' => '2026-01-01']);
    $palUser = User::create(['name' => 'Pal', 'email' => 'pal@pest.test', 'password' => 'x', 'employee_id' => $pal->id]);
    $palUser->assignRole(Role::Employee->value);

    $this->actingAs($this->user)->getJson("{$this->base}/me/attendance")->assertJsonPath('swaps.enabled', true);
    $id = $this->postJson("{$this->base}/me/shift-swaps", ['partner_employee_id' => $pal->id, 'date' => '2026-09-10', 'reason' => 'Wedding'])
        ->assertCreated()->assertJsonPath('status', 'Waiting for colleague')->json('id');
    $this->postJson("{$this->base}/me/shift-swaps/{$id}/answer", ['accept' => true])->assertStatus(422); // only the colleague answers

    $this->actingAs($palUser)->getJson("{$this->base}/me/attendance")->assertJsonPath('swaps.items.0.can_answer', true);
    $this->postJson("{$this->base}/me/shift-swaps/{$id}/answer", ['accept' => true])->assertOk()->assertJsonPath('status', 'Waiting for approval');

    app(ApproveShiftSwap::class)(ShiftSwapRequest::find($id), User::where('email', 'owner@pest.test')->first(), true);
    expect(app(ShiftResolver::class)->resolve($this->emp, '2026-09-10', 'Asia/Karachi')->shiftId)->toBe($night->id)
        ->and(NotificationLog::where('event', 'shift_swap_asked')->exists())->toBeTrue()
        ->and(NotificationLog::where('event', 'shift_swap_pending')->exists())->toBeTrue();
});

it('only lets employees ask colleagues in their own branch and department, a few at a time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    Mail::fake();
    $night = Shift::create(['name' => 'Night', 'code' => 'NGT', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true]);
    $far = Branch::create(['name' => 'Far', 'code' => 'FAR']);
    $stranger = Employee::create(['code' => 'SS4', 'first_name' => 'Stranger', 'joined_on' => '2025-01-01', 'branch_id' => $far->id]);
    $pal = Employee::create(['code' => 'SS5', 'first_name' => 'Pal', 'joined_on' => '2025-01-01', 'branch_id' => $this->office->id]);
    foreach ([$stranger, $pal] as $e) {
        ShiftAssignment::create(['employee_id' => $e->id, 'shift_id' => $night->id, 'effective_from' => '2026-01-01']);
    }

    $this->actingAs($this->user)->getJson("{$this->base}/me/attendance")->assertJsonCount(1, 'swaps.colleagues');
    $this->postJson("{$this->base}/me/shift-swaps", ['partner_employee_id' => $stranger->id, 'date' => '2026-09-10'])
        ->assertStatus(422)->assertJsonValidationErrors('partner');

    foreach (['2026-09-10', '2026-09-11', '2026-09-12'] as $date) {
        $this->postJson("{$this->base}/me/shift-swaps", ['partner_employee_id' => $pal->id, 'date' => $date])->assertCreated();
    }
    $this->postJson("{$this->base}/me/shift-swaps", ['partner_employee_id' => $pal->id, 'date' => '2026-09-13'])
        ->assertStatus(422)->assertJsonValidationErrors('swap');
});

it('lets an employee download their own monthly report, not future months', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 10:00', 'Asia/Karachi'));

    $pdf = $this->actingAs($this->user)->get("{$this->base}/me/report.pdf?month=2026-09")->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toContain('attendance-SS1-2026-09.pdf');
    $this->get("{$this->base}/me/report.pdf?month=2026-11")->assertNotFound();
});

it('lets an employee download their own payslip once the payroll is final', function () {
    Storage::fake('local');
    Setting::updateOrCreate(['key' => 'feature.payroll_lite'], ['value' => true]);
    app(TenantSettings::class)->flush();
    SalaryStructure::create(['employee_id' => $this->emp->id, 'effective_from' => '2025-01-01', 'basic' => 31000]);
    foreach (range(1, 31) as $d) {
        AttendanceDay::create(['employee_id' => $this->emp->id, 'date' => sprintf('2026-08-%02d', $d), 'status' => AttendanceStatus::Present, 'flags' => []]);
    }
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $period = app(PeriodActions::class)->period(2026, 8);
    app(PeriodActions::class)->lock($period, $owner);
    $payroll = app(PayrollActions::class);
    $run = $payroll->generate($period->fresh(), $owner);
    $this->actingAs($this->user);

    $this->getJson("{$this->base}/me/attendance?month=2026-08")->assertJsonPath('payslip', false); // draft: not yet
    $this->get("{$this->base}/me/payslip.pdf?month=2026-08")->assertNotFound();

    $payroll->finalise($run->fresh(), $owner);
    $this->getJson("{$this->base}/me/attendance?month=2026-08")->assertJsonPath('payslip', true);
    $pdf = $this->get("{$this->base}/me/payslip.pdf?month=2026-08")->assertOk();
    expect($pdf->headers->get('content-disposition'))->toContain('payslip-SS1-2026-08.pdf');
    $this->get("{$this->base}/me/payslip.pdf?month=2026-07")->assertNotFound();
});

it('lists my leave requests by leave type name with a readable status', function () {
    $type = LeaveType::create(['name' => 'Casual leave', 'code' => 'CLX', 'annual_quota_days' => 10]);
    LeaveRequest::create(['employee_id' => $this->emp->id, 'leave_type_id' => $type->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10',
        'days' => 1, 'status' => 'pending']);

    $this->actingAs($this->user)->getJson("{$this->base}/me/attendance")
        ->assertJsonPath('requests.0.type', 'Casual leave')->assertJsonPath('requests.0.status', 'pending')->assertJsonPath('requests.0.status_label', 'Pending');
});
