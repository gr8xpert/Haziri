<?php

declare(strict_types=1);

use App\Enums\DevicePunchSourceType;
use App\Enums\PunchStatus;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/* The kiosk "Auto" button: each saved punch flips the employee between IN and OUT. */

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'feature.kiosk'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->worker = Employee::create(['code' => 'KA1', 'first_name' => 'Ali', 'joined_on' => '2025-01-01', 'device_pin' => '701']);
    $device = Device::create(['name' => 'Tab', 'serial_number' => 'KIOSK-AUTO', 'type' => DevicePunchSourceType::Kiosk]);
    $kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin'], 'require_photo' => false]);
    $url = 'http://pest.' . config('hazri.central_domain') . "/kiosk/{$kiosk->token}/punch";
    $this->punchAt = function (string $local, string $direction = 'auto') use ($url): array {
        $this->travelTo(CarbonImmutable::parse($local, 'Asia/Karachi'));

        return $this->postJson($url, ['mode' => 'pin', 'pin' => '701', 'direction' => $direction])->assertOk()->json();
    };
});

afterEach(fn () => DeviceRegistry::where('serial_number', 'KIOSK-AUTO')->delete());

it('toggles in and out through a day with a lunch break, and starts the next day with in', function () {
    $directions = array_map(fn (string $t) => ($this->punchAt)($t)['direction'],
        ['2026-09-01 09:00', '2026-09-01 13:00', '2026-09-01 14:00', '2026-09-01 18:00', '2026-09-02 09:00']);

    expect($directions)->toBe(['in', 'out', 'in', 'out', 'in']);
});

it('punches a night-shift worker out after midnight', function () {
    expect(($this->punchAt)('2026-09-01 20:00')['direction'])->toBe('in')
        ->and(($this->punchAt)('2026-09-02 04:00')['direction'])->toBe('out');
});

it('treats an arrival as in when yesterday\'s punch-out was forgotten', function () {
    ($this->punchAt)('2026-09-01 09:00');

    expect(($this->punchAt)('2026-09-02 09:05')['direction'])->toBe('in');
});

it('follows the last punch when In / Out were pressed by hand', function () {
    ($this->punchAt)('2026-09-01 09:00', 'in');
    ($this->punchAt)('2026-09-01 09:30', 'in'); // pressed In by mistake

    expect(($this->punchAt)('2026-09-01 13:00')['direction'])->toBe('out');
});

it('says "already punched" for a second tap instead of a fake success', function () {
    ($this->punchAt)('2026-09-01 09:00');
    $second = ($this->punchAt)('2026-09-01 09:00:30');

    expect($second)->toMatchArray(['ok' => true, 'double_tap' => true, 'direction' => 'in', 'time' => '09:00', 'message' => 'Already punched IN at 09:00'])
        ->and(Punch::where('employee_id', $this->worker->id)->where('status', PunchStatus::Valid)->count())->toBe(1)
        // the ignored tap does not flip the next one
        ->and(($this->punchAt)('2026-09-01 18:00')['direction'])->toBe('out');
});
