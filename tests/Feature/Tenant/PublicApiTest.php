<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Settings\TenantSettings;
use App\Services\Webhooks\Webhooks;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'feature.public_api'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->base = 'http://pest.' . config('hazri.central_domain') . '/api/v1';
    $this->emp = Employee::create(['code' => 'API1', 'first_name' => 'Api', 'joined_on' => '2025-01-01', 'device_pin' => '701']);
    ['plain' => $this->read] = ApiToken::issue('reader', ['read'], null);
    ['plain' => $this->write] = ApiToken::issue('writer', ['read', 'punches:write'], null);
});

it('authenticates tokens and enforces abilities', function () {
    $this->getJson("{$this->base}/employees")->assertStatus(401);
    $this->getJson("{$this->base}/employees", ['Authorization' => "Bearer {$this->read}"])->assertOk()->assertJsonPath('data.0.code', 'API1');
    $this->postJson("{$this->base}/punches", ['employee_code' => 'API1', 'timestamp' => '2026-09-01 09:00'], ['Authorization' => "Bearer {$this->read}"])
        ->assertStatus(403);
});

it('accepts punches through the API and emits signed webhooks', function () {
    Http::fake(['203.0.113.20/*' => Http::response('ok')]);
    WebhookEndpoint::create(['url' => 'https://203.0.113.20/hook', 'secret' => 'whsec_test', 'events' => ['punch.created', 'attendance_day.updated']]);

    $this->postJson("{$this->base}/punches", ['employee_code' => 'API1', 'timestamp' => '2026-09-01 09:00', 'direction' => 'in'],
        ['Authorization' => "Bearer {$this->write}"])->assertCreated()->assertJson(['outcome' => 'stored']);

    expect(Punch::where('employee_id', $this->emp->id)->value('source')->value)->toBe('api')
        ->and(WebhookDelivery::where('event', 'punch.created')->value('status'))->toBe('delivered')
        ->and(WebhookDelivery::where('event', 'attendance_day.updated')->exists())->toBeTrue();

    Http::assertSent(function ($req) {
        [$ts, $sig] = [$req->header('X-Hazri-Timestamp')[0], $req->header('X-Hazri-Signature')[0]];

        return $sig === 'sha256=' . Webhooks::signature('whsec_test', (int) $ts, $req->body())
            && $req->header('X-Webhook-Signature')[0] === $sig && $req->header('X-Webhook-Timestamp')[0] === $ts;
    });
});

it('refuses webhook URLs that point inside the network', function () {
    Http::fake();
    WebhookEndpoint::create(['url' => 'http://169.254.169.254/latest', 'secret' => 's', 'events' => ['punch.created']]);

    $this->postJson("{$this->base}/punches", ['employee_code' => 'API1', 'timestamp' => '2026-09-01 10:00'], ['Authorization' => "Bearer {$this->write}"]);

    expect(WebhookDelivery::value('status'))->toBe('failed');
    Http::assertNothingSent();
});

it('serves attendance and payroll input', function () {
    AttendanceDay::create(['employee_id' => $this->emp->id, 'date' => '2026-08-03', 'status' => 'present', 'flags' => []]);

    $this->getJson("{$this->base}/attendance?from=2026-08-01&to=2026-08-31&employee=API1", ['Authorization' => "Bearer {$this->read}"])
        ->assertOk()->assertJsonPath('data.0.status', 'present');
    $this->getJson("{$this->base}/periods/2026-08/payroll-input", ['Authorization' => "Bearer {$this->read}"])
        ->assertOk()->assertJsonPath('data.0.code', 'API1');
});

it('is closed when the plan has no API', function () {
    Setting::where('key', 'feature.public_api')->update(['value' => json_encode(false)]);
    app(TenantSettings::class)->flush();

    $this->getJson("{$this->base}/employees", ['Authorization' => "Bearer {$this->read}"])->assertStatus(403);
});
