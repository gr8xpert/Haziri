<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\SelfService\GeoFence;
use App\Services\SelfService\QrBadge;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

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

afterEach(fn () => App\Models\Central\DeviceRegistry::where('serial_number', 'like', 'KIOSK-%')->delete());

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
    $outside->assertOk()->assertJson(['outcome' => 'pending_approval', 'direction' => 'out', 'inside_geofence' => false]);

    $pending = Punch::where('status', PunchStatus::Pending)->firstOrFail();
    expect($pending->meta['geofence_fail'])->toBeTrue();

    $hr = User::create(['name' => 'HR', 'email' => 'hr-ss@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);
    app(App\Actions\SelfService\DecidePendingPunch::class)($pending, $hr, true);
    expect($pending->fresh()->status)->toBe(PunchStatus::Valid)
        ->and(AttendanceDay::where('employee_id', $this->emp->id)->whereDate('date', now('Asia/Karachi')->format('Y-m-d'))->value('flags'))
        ->toContain('geofence_fail');
});

it('lets field staff punch anywhere', function () {
    $this->emp->update(['work_mode' => 'field']);

    $this->actingAs($this->user)->postJson("{$this->base}/me/punch", ['lat' => 24.86, 'lng' => 67.00])->assertJson(['inside_geofence' => true]);
});

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
