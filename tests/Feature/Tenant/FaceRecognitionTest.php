<?php

declare(strict_types=1);

use App\Enums\DevicePunchSourceType;
use App\Enums\VerifyType;
use App\Filament\Tenant\Pages\FaceEnrollment;
use App\Models\Branch;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\SelfService\FaceMatcher;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/* Kiosk face recognition: descriptors are matched on the server; the tablet only proves liveness and sends numbers. */

/** A face descriptor (real ones are 1024 values of roughly ±1): one "person" per seed, noise for another photo of them. */
function faceOf(int $person, float $noise = 0.0, int $sample = 1): array
{
    return array_map(fn (int $i) => round(sin($i * 0.37 * $person + $person) + $noise * sin($i * 1.7 + $sample), 6), range(0, 1023));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    foreach (['kiosk', 'kiosk_face'] as $flag) {
        Setting::updateOrCreate(['key' => "feature.{$flag}"], ['value' => true]);
    }
    app(TenantSettings::class)->flush();
    $this->asma = Employee::create(['code' => 'FR1', 'first_name' => 'Asma', 'joined_on' => '2025-01-01']);
    $this->bina = Employee::create(['code' => 'FR2', 'first_name' => 'Bina', 'joined_on' => '2025-01-01']);
    foreach ([[$this->asma, 1], [$this->bina, 2]] as [$e, $person]) {
        foreach ([1, 2, 3] as $sample) {
            FaceTemplate::create(['employee_id' => $e->id, 'descriptor' => faceOf($person, 0.1, $sample)]);
        }
    }
    $device = Device::create(['name' => 'Tab', 'serial_number' => 'KIOSK-FACE', 'type' => DevicePunchSourceType::Kiosk]);
    $this->kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin', 'face'], 'require_photo' => false]);
    $this->url = 'http://pest.' . config('hazri.central_domain') . "/kiosk/{$this->kiosk->token}";
});

afterEach(fn () => DeviceRegistry::where('serial_number', 'KIOSK-FACE')->delete());

it('scores the same face high and different faces low, like the browser library', function () {
    expect(FaceMatcher::similarity(faceOf(1), faceOf(1)))->toBe(1.0)
        ->and(FaceMatcher::similarity(faceOf(1), faceOf(1, 0.1, 5)))->toBeGreaterThan(0.6)
        ->and(FaceMatcher::similarity(faceOf(1), faceOf(2)))->toBeLessThan(0.5);
});

it('identifies the closest enrolled employee and refuses unknown or doubtful faces', function () {
    $matcher = app(FaceMatcher::class);

    expect($matcher->identify(faceOf(1, 0.1, 9))['employee_id'])->toBe($this->asma->id)
        ->and($matcher->identify(faceOf(7))['employee_id'])->toBeNull();

    // Two people enrolled with the same face: never guess between them.
    FaceTemplate::create(['employee_id' => $this->bina->id, 'descriptor' => faceOf(1, 0.1, 1)]);
    expect($matcher->identify(faceOf(1, 0.1, 9))['employee_id'])->toBeNull();
});

it('only matches employees of the kiosk branch', function () {
    $shop = Branch::create(['name' => 'Shop 2', 'code' => 'S2']);
    $this->asma->update(['branch_id' => $shop->id]);
    $other = Branch::create(['name' => 'Shop 3', 'code' => 'S3']);

    expect(app(FaceMatcher::class)->identify(faceOf(1, 0.1, 9), $other->id)['employee_id'])->toBeNull()
        ->and(app(FaceMatcher::class)->identify(faceOf(1, 0.1, 9), $shop->id)['employee_id'])->toBe($this->asma->id);
});

it('punches on the kiosk by face after the liveness check', function () {
    $punch = fn (array $body) => $this->postJson("{$this->url}/punch", ['mode' => 'face'] + $body);

    $punch(['face' => faceOf(1, 0.1, 9)])->assertStatus(422);                                   // no liveness
    $punch(['face' => faceOf(7), 'liveness_passed' => true])->assertStatus(422)
        ->assertJsonPath('message', 'Face not recognised. Please use your PIN.');
    $punch(['face' => faceOf(1, 0.1, 9), 'liveness_passed' => true])->assertOk()->assertJson(['ok' => true, 'name' => 'Asma']);

    $saved = Punch::where('employee_id', $this->asma->id)->firstOrFail();
    expect($saved->verify_type)->toBe(VerifyType::Face)
        ->and($saved->meta['face_similarity'])->toBeGreaterThan(0.6);
});

it('switches face punching off with the feature', function () {
    Setting::updateOrCreate(['key' => 'feature.kiosk_face'], ['value' => false]);
    app(TenantSettings::class)->flush();

    $this->getJson("{$this->url}/config")->assertJsonPath('modes', ['pin']);
    $this->postJson("{$this->url}/punch", ['mode' => 'face', 'face' => faceOf(1), 'liveness_passed' => true])->assertStatus(422);
});

it('enrols and deletes an employee\'s face from the admin panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    $carl = Employee::create(['code' => 'FR3', 'first_name' => 'Carl', 'joined_on' => '2025-01-01']);

    Livewire::test(FaceEnrollment::class)->set('employeeId', $carl->id)
        ->call('saveSamples', [faceOf(3, 0.1, 1), faceOf(3, 0.1, 2), faceOf(3, 0.1, 3)], [0.95, 0.94, 0.97]);
    expect(FaceTemplate::where('employee_id', $carl->id)->count())->toBe(3);

    Livewire::test(FaceEnrollment::class)->call('forget', $carl->id);
    expect(FaceTemplate::where('employee_id', $carl->id)->count())->toBe(0);
});
