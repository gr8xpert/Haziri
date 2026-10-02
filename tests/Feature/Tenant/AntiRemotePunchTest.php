<?php

declare(strict_types=1);

use App\Enums\DevicePunchSourceType;
use App\Enums\Role;
use App\Models\Anomaly;
use App\Models\Branch;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\KioskDevice;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AnomalyDetector;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/* Punching from home: locked kiosks, office-internet rules and mobile checks. */

function antiRemoteSetting(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    app(TenantSettings::class)->flush();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    antiRemoteSetting('feature.kiosk', true);
    antiRemoteSetting('feature.mobile_punch', true);
    $this->office = Branch::create(['name' => 'Shop 1', 'code' => 'S1', 'lat' => 31.5204, 'lng' => 74.3587, 'geofence_radius_m' => 150]);
    $this->emp = Employee::create(['code' => 'AR1', 'first_name' => 'Asad', 'joined_on' => '2025-01-01', 'device_pin' => '801', 'branch_id' => $this->office->id]);
    $this->user = User::create(['name' => 'Asad', 'email' => 'asad@pest.test', 'password' => 'secret123', 'employee_id' => $this->emp->id]);
    $this->user->assignRole(Role::Employee->value);
    $this->base = 'http://pest.' . config('hazri.central_domain');
    $device = Device::create(['name' => 'Shop tablet', 'serial_number' => 'KIOSK-LOCK', 'type' => DevicePunchSourceType::Kiosk]);
    $this->kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin'], 'require_photo' => false,
        'lock_to_device' => true]);
    $this->kioskUrl = "{$this->base}/kiosk/{$this->kiosk->token}";
    $this->tabletKey = str_repeat('ab', 32);
});

afterEach(fn () => DeviceRegistry::where('serial_number', 'KIOSK-LOCK')->delete());

it('refuses a locked kiosk until HR approves the tablet', function () {
    $punch = fn (array $extra = []) => $this->postJson("{$this->kioskUrl}/punch", ['mode' => 'pin', 'pin' => '801'] + $extra);

    $punch()->assertForbidden()->assertJsonPath('access.status', 'no_key');
    $pending = $punch(['device_key' => $this->tabletKey])->assertForbidden()->assertJsonPath('access.status', 'pending');
    $tablet = KioskDevice::where('kiosk_id', $this->kiosk->id)->firstOrFail();
    expect($pending->json('access.code'))->toBe($tablet->pairing_code)
        ->and(Punch::where('employee_id', $this->emp->id)->count())->toBe(0);

    $tablet->update(['status' => KioskDevice::APPROVED]);
    $punch(['device_key' => $this->tabletKey])->assertOk()->assertJson(['ok' => true, 'direction' => 'in']);
    $this->getJson("{$this->kioskUrl}/config?device_key={$this->tabletKey}")->assertJsonPath('access.status', 'approved');

    // A different browser (e.g. the same link opened at home) is a different tablet and stays locked out.
    $punch(['device_key' => str_repeat('cd', 32)])->assertForbidden()->assertJsonPath('access.status', 'pending');

    $tablet->update(['status' => KioskDevice::BLOCKED]);
    $punch(['device_key' => $this->tabletKey])->assertForbidden()->assertJsonPath('access.status', 'blocked');
});

it('only accepts kiosk punches from the office internet when IPs are set', function () {
    $this->kiosk->update(['lock_to_device' => false, 'allowed_ips' => ['39.45.10.0/24']]);
    $punch = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson("{$this->kioskUrl}/punch", ['mode' => 'pin', 'pin' => '801']);

    $punch('110.93.1.5')->assertForbidden()->assertJsonPath('access.status', 'ip_blocked');
    $punch('39.45.10.22')->assertOk();
});

it('blocks phone punches for staff who must use the shop machine', function () {
    $this->emp->update(['mobile_punch_allowed' => false]);

    $this->actingAs($this->user)->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 10])
        ->assertStatus(422)->assertJsonPath('errors.punch.0', 'Please punch on the machine or kiosk at your workplace.');
});

it('rejects a weak GPS fix and a missing selfie when the company asks for them', function () {
    antiRemoteSetting('mobile_max_accuracy_m', 50);
    antiRemoteSetting('mobile_require_selfie', true);
    $this->actingAs($this->user);

    $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 400])->assertStatus(422)->assertJsonValidationErrors('punch');
    $this->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 10])->assertStatus(422)->assertJsonValidationErrors('photo');
});

it('sends a phone punch from outside the office internet for approval even inside the geofence', function () {
    $this->office->update(['allowed_ips' => ['39.45.10.22']]);
    $this->actingAs($this->user);
    $punch = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson("{$this->base}/me/punch", ['lat' => 31.5205, 'lng' => 74.3588, 'accuracy' => 10]);

    $punch('110.93.1.5')->assertOk()->assertJson(['outcome' => 'pending_approval', 'inside_geofence' => true, 'office_network' => false]);
    $this->travel(2)->minutes();
    $punch('39.45.10.22')->assertOk()->assertJson(['outcome' => 'stored', 'office_network' => true]);
});

it('flags punches too far apart to travel between', function () {
    $at = CarbonImmutable::parse('2026-09-08 09:00', 'Asia/Karachi');
    $mk = fn (CarbonImmutable $t, float $lat, float $lng) => Punch::create(['employee_id' => $this->emp->id, 'punched_at' => $t->utc(),
        'local_date' => $t->format('Y-m-d'), 'source' => 'mobile', 'direction' => 'in', 'status' => 'valid', 'lat' => $lat, 'lng' => $lng,
        'dedupe_hash' => md5($t . $lat)]);
    $mk($at, 31.5204, 74.3587);                     // Lahore
    $mk($at->addMinutes(20), 24.8607, 67.0011);     // Karachi, 20 minutes later

    app(AnomalyDetector::class)->run(CarbonImmutable::parse('2026-09-08', 'Asia/Karachi'));

    $anomaly = Anomaly::where('type', 'impossible_travel')->where('employee_id', $this->emp->id)->first();
    expect($anomaly)->not->toBeNull()
        ->and($anomaly->evidence['minutes'])->toBe(20);
});
