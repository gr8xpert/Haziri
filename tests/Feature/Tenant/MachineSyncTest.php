<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Events\DeviceWentOffline;
use App\Filament\Tenant\Resources\Devices\Pages\CreateDevice;
use App\Filament\Tenant\Resources\Devices\Pages\EditDevice;
use App\Filament\Tenant\Resources\Devices\Pages\ListDevices;
use App\Models\AttendanceDay;
use App\Models\Central\AgentKey;
use App\Models\Central\DeviceRegistry;
use App\Models\Central\UnknownDevice;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\UnmappedPin;
use App\Models\User;
use App\Services\Devices\DeviceCommander;
use App\Services\Devices\UsbImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use TanemRahman\ZktecoAdms\Models\ZktecoDeviceCommand;
use TanemRahman\ZktecoAdms\Models\ZktecoDeviceUser;

function hazriFixture(string $path): string
{
    return file_get_contents(base_path("tests/Fixtures/{$path}"));
}

/*
| HTTP calls stay in the current tenant context: re-initialising tenancy would reconnect the tenant DB and
| discard the test transaction. The ADMS/agent middlewares resolve the same tenant, so they no-op the init.
*/
beforeEach(function () {
    $this->ali = Employee::create(['code' => 'ALI', 'first_name' => 'Ali', 'last_name' => 'Khan', 'joined_on' => '2025-01-01', 'device_pin' => '47']);
    $this->adms = 'http://' . config('hazri.adms_domain');
    $this->api = 'http://' . config('hazri.api_domain');
});

afterEach(function () {
    // Central rows are outside the tenant transaction.
    DeviceRegistry::query()->whereIn('serial_number', ['ZKADMS01', 'ZKAGENT01', 'ZKADMS77', 'KIOSK-LIST1'])->delete();
    UnknownDevice::query()->whereIn('serial_number', ['ZKNOBODY', 'ZKBLOCKED'])->delete();
    AgentKey::query()->where('label', 'pest agent')->delete();
    DB::connection('central')->table('tenants')->where('id', 'other-co-id')->delete();
});

it('registers ADMS devices centrally and refuses serials owned by another company', function () {
    Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]);

    expect(DeviceRegistry::where('serial_number', 'ZKADMS01')->value('tenant_id'))->toBe($this->tenant->id);

    // Another company (raw central row: no DB is created for it).
    DB::connection('central')->table('tenants')->insertOrIgnore([
        'id' => 'other-co-id', 'slug' => 'other-co', 'company_name' => 'Other', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DeviceRegistry::where('serial_number', 'ZKADMS01')->update(['tenant_id' => 'other-co-id']);
    expect(fn () => Device::create(['name' => 'Dup', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]))
        ->toThrow(ValidationException::class);
});

it('accepts an ADMS handshake and ATTLOG push, ingesting punches into the tenant', function () {
    $device = Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]);

    $handshake = $this->get("{$this->adms}/iclock/cdata?SN=ZKADMS01&options=all&pushver=2.4.1");
    $push = $this->call('POST', "{$this->adms}/iclock/cdata?SN=ZKADMS01&table=ATTLOG&Stamp=9999", [], [], [], ['CONTENT_TYPE' => 'text/plain'], hazriFixture('adms/attlog.txt'));

    $handshake->assertOk();
    expect($handshake->getContent())->toContain('GET OPTION FROM: ZKADMS01');
    $push->assertOk();

    $punches = Punch::where('source', PunchSource::Adms)->orderBy('punched_at')->get();
    expect($punches)->toHaveCount(3)
        ->and($punches[0]->employee_id)->toBe($this->ali->id)
        ->and($punches[0]->punched_at->format('Y-m-d H:i:s'))->toBe('2026-09-01 04:02:11') // 09:02 PKT → UTC
        ->and($punches[0]->device_id)->toBe($device->id)
        ->and(UnmappedPin::where('device_pin', '99')->exists())->toBeTrue()
        ->and(AttendanceDay::where('employee_id', $this->ali->id)->whereDate('date', '2026-09-01')->value('status'))
        ->toBe(AttendanceStatus::Present)
        ->and($device->fresh()->status)->toBe('online');
});

it('refuses unknown ADMS devices without saying OK and records them for the super admin', function () {
    $response = $this->call('POST', "{$this->adms}/iclock/cdata?SN=ZKNOBODY&table=ATTLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], hazriFixture('adms/attlog.txt'));

    $response->assertStatus(403);
    expect($response->getContent())->not->toBe('OK')
        ->and(UnknownDevice::where('serial_number', 'ZKNOBODY')->value('hits'))->toBe(1);
});

it('records a stray machine\'s check-ins at most once a minute', function () {
    $this->get("{$this->adms}/iclock/getrequest?SN=ZKNOBODY")->assertStatus(403);
    $this->get("{$this->adms}/iclock/getrequest?SN=ZKNOBODY")->assertStatus(403);

    expect(UnknownDevice::where('serial_number', 'ZKNOBODY')->value('hits'))->toBe(1);
});

it('turns a blocked machine away without recording it, and lets it in once a company adds it', function () {
    $this->get("{$this->adms}/iclock/getrequest?SN=ZKNOBODY");
    $row = UnknownDevice::where('serial_number', 'ZKNOBODY')->firstOrFail();
    $row->block();
    $this->travel(5)->minutes();

    $this->get("{$this->adms}/iclock/getrequest?SN=ZKNOBODY")->assertStatus(403);
    expect($row->fresh()->hits)->toBe(1);

    Device::create(['name' => 'Back gate', 'serial_number' => 'ZKNOBODY', 'type' => DevicePunchSourceType::Adms]);
    $this->get("{$this->adms}/iclock/getrequest?SN=ZKNOBODY")->assertOk();
    DeviceRegistry::where('serial_number', 'ZKNOBODY')->delete();
});

it('suggests the company whose registered machine uses the same IP', function () {
    Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]);
    DeviceRegistry::where('serial_number', 'ZKADMS01')->update(['last_ip' => '203.0.113.7']);
    $same = UnknownDevice::create(['serial_number' => 'ZKNOBODY', 'ip' => '203.0.113.7', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $other = UnknownDevice::create(['serial_number' => 'ZKBLOCKED', 'ip' => '198.51.100.9', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    expect($same->likelyTenants()->pluck('id')->all())->toBe([$this->tenant->id])
        ->and($other->likelyTenants())->toBeEmpty();
});

it('prunes machines not seen for 30 days but keeps blocked ones', function () {
    UnknownDevice::create(['serial_number' => 'ZKNOBODY', 'first_seen_at' => now()->subDays(40), 'last_seen_at' => now()->subDays(31)]);
    UnknownDevice::create(['serial_number' => 'ZKBLOCKED', 'first_seen_at' => now()->subDays(40), 'last_seen_at' => now()->subDays(31), 'blocked_at' => now()->subDays(31)]);

    $this->artisan('model:prune', ['--model' => [UnknownDevice::class]]);

    expect(UnknownDevice::whereIn('serial_number', ['ZKNOBODY', 'ZKBLOCKED'])->pluck('serial_number')->all())->toBe(['ZKBLOCKED']);
});

it('lists device users as unmapped PINs and never stores templates', function () {
    Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]);
    $this->call('POST', "{$this->adms}/iclock/cdata?SN=ZKADMS01&table=OPERLOG", [], [], [], ['CONTENT_TYPE' => 'text/plain'], hazriFixture('adms/operlog.txt'))->assertOk();

    expect(UnmappedPin::where('device_pin', '99')->value('device_user_name'))->toBe('New Guard')
        ->and(UnmappedPin::where('device_pin', '47')->exists())->toBeFalse();
    $columns = Schema::getColumnListing('zkteco_device_users');
    expect($columns)->not->toContain('template')->not->toContain('tmp')
        ->and(json_encode(ZktecoDeviceUser::all()->toArray()))->not->toContain('AAAABBBBCCCC');
});

it('rejects a wrong comm key', function () {
    Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms, 'comm_key' => '1234']);
    $bad = $this->get("{$this->adms}/iclock/cdata?SN=ZKADMS01&options=all&pushcommkey=0000");
    $good = $this->get("{$this->adms}/iclock/cdata?SN=ZKADMS01&options=all&pushcommkey=1234");

    $bad->assertStatus(401);
    $good->assertOk();
});

it('queues ADMS remote commands through the package and agent commands in our table', function () {
    $adms = Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms]);
    $agent = Device::create(['name' => 'Back', 'serial_number' => 'ZKAGENT01', 'type' => DevicePunchSourceType::Agent]);
    $commander = app(DeviceCommander::class);

    expect($commander->pushEmployees($adms))->toBe(1)
        ->and(ZktecoDeviceCommand::where('command', 'like', 'DATA UPDATE USERINFO PIN=47%')->exists())->toBeTrue()
        ->and($commander->syncTime($agent))->toBe(1)
        ->and(DeviceCommand::where('device_id', $agent->id)->value('command'))->toBe('sync_time');
});

it('accepts agent punches with a valid key and rejects bad keys', function () {
    $device = Device::create(['name' => 'Back', 'serial_number' => 'ZKAGENT01', 'type' => DevicePunchSourceType::Agent]);
    ['plain' => $key] = AgentKey::issue($this->tenant->id, 'pest agent');
    $payload = json_decode(hazriFixture('agent/punches.json'), true);

    $denied = $this->postJson("{$this->api}/api/v1/agent/punches", $payload, ['Authorization' => 'Bearer nope']);
    $ok = $this->postJson("{$this->api}/api/v1/agent/punches", $payload, ['Authorization' => "Bearer {$key}"]);
    $replay = $this->postJson("{$this->api}/api/v1/agent/punches", $payload, ['Authorization' => "Bearer {$key}"]);

    $denied->assertStatus(401);
    $ok->assertOk()->assertJson(['received' => 3, 'stored' => 2, 'unmapped' => 1]);
    $replay->assertOk()->assertJson(['already_stored' => 3]);
    expect(Punch::where('device_id', $device->id)->where('status', PunchStatus::Valid)->count())->toBe(2)
        ->and(Punch::where('external_id', '5813')->first()->punched_at->format('H:i:s'))->toBe('13:00:30'); // bare time = device local
});

it('serves agent commands and records results', function () {
    $device = Device::create(['name' => 'Back', 'serial_number' => 'ZKAGENT01', 'type' => DevicePunchSourceType::Agent]);
    $cmd = DeviceCommand::create(['device_id' => $device->id, 'command' => 'reboot']);
    ['plain' => $key] = AgentKey::issue($this->tenant->id, 'pest agent');

    $list = $this->getJson("{$this->api}/api/v1/agent/commands?device_serial=ZKAGENT01", ['Authorization' => "Bearer {$key}"]);
    $result = $this->postJson("{$this->api}/api/v1/agent/commands/{$cmd->id}/result", ['ok' => true, 'message' => 'done'], ['Authorization' => "Bearer {$key}"]);
    $beat = $this->postJson("{$this->api}/api/v1/agent/heartbeat", ['agent_version' => '1.0.0', 'devices' => [['serial' => 'ZKAGENT01', 'reachable' => true, 'unsynced' => 4]]], ['Authorization' => "Bearer {$key}"]);

    $list->assertOk()->assertJsonPath('commands.0.command', 'reboot');
    $result->assertOk();
    $beat->assertOk();
    expect($cmd->fresh()->status)->toBe('done')->and($device->fresh()->unsynced_count)->toBe(4);
});

it('shows the agent\'s last error for a machine on the Devices list and edit page', function () {
    $device = Device::create(['name' => 'Back', 'serial_number' => 'ZKAGENT01', 'type' => DevicePunchSourceType::Agent]);
    ['plain' => $key] = AgentKey::issue($this->tenant->id, 'pest agent');
    $beat = fn (array $machine) => $this->postJson("{$this->api}/api/v1/agent/heartbeat", ['devices' => [$machine + ['serial' => 'ZKAGENT01']]],
        ['Authorization' => "Bearer {$key}"])->assertOk();

    $beat(['reachable' => false, 'error' => 'dial tcp 192.168.1.201:4370: i/o timeout']);
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('tenant'));
    Livewire\Livewire::test(ListDevices::class)->assertSee('Agent error: dial tcp 192.168.1.201:4370: i/o timeout');
    Livewire\Livewire::test(EditDevice::class, ['record' => $device->getRouteKey()])->assertSee('Agent error: dial tcp 192.168.1.201:4370: i/o timeout');

    $beat(['reachable' => true]);
    Livewire\Livewire::test(ListDevices::class)->assertDontSee('Agent error');
});

it('offers only machine types for a new device and leaves kiosk tablets to the Kiosks page', function () {
    Device::create(['name' => 'Gate K40', 'serial_number' => 'ZKADMS77', 'type' => DevicePunchSourceType::Adms]);
    $tablet = Device::create(['name' => 'Reception tablet', 'serial_number' => 'KIOSK-LIST1', 'type' => DevicePunchSourceType::Kiosk]);
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('tenant'));

    Livewire\Livewire::test(ListDevices::class)->assertCanSeeTableRecords(Device::where('serial_number', 'ZKADMS77')->get())
        ->assertCanNotSeeTableRecords([$tablet]);
    Livewire\Livewire::test(CreateDevice::class)
        ->fillForm(['name' => 'Tab 2', 'serial_number' => 'KIOSK-LIST2', 'type' => 'kiosk'])->call('create')
        ->assertHasFormErrors(['type']);
    Livewire\Livewire::test(CreateDevice::class)
        ->fillForm(['name' => 'Old K40', 'serial_number' => 'USB-LIST3', 'type' => 'usb'])->call('create')
        ->assertHasNoFormErrors();
});

it('imports a USB attlog.dat, skipping junk lines and deduping re-imports', function () {
    $device = Device::create(['name' => 'Old K40', 'serial_number' => 'USB01', 'type' => DevicePunchSourceType::Usb]);
    $path = base_path('tests/Fixtures/usb/1_attlog.dat');
    $importer = app(UsbImporter::class);
    $owner = User::where('email', 'owner@pest.test')->first();

    $preview = $importer->preview($path, '1_attlog.dat', $device);
    $first = $importer->import('usb-imports/1_attlog.dat', $path, '1_attlog.dat', $device, $owner);
    $second = $importer->import('usb-imports/1_attlog.dat', $path, '1_attlog.dat', $device, $owner);

    expect($preview['total'])->toBe(3)->and($preview['unmapped'])->toBe(['55' => 1])->and($preview['errors'])->toHaveCount(1)
        ->and($preview['from'])->toBe('2026-09-02')
        ->and($first->rows_imported)->toBe(2)->and($first->rows_unmapped)->toBe(1)
        ->and($second->rows_imported)->toBe(0)->and($second->rows_duplicate)->toBe(3)
        ->and(AttendanceDay::where('employee_id', $this->ali->id)->whereDate('date', '2026-09-02')->value('status'))->toBe(AttendanceStatus::Present);
});

it('marks silent devices offline once', function () {
    $device = Device::create(['name' => 'Front', 'serial_number' => 'ZKADMS01', 'type' => DevicePunchSourceType::Adms,
        'status' => 'online', 'last_seen_at' => now()->subMinutes(30)]);
    Event::fake([DeviceWentOffline::class]);

    $this->artisan('hazri:devices:check')->assertSuccessful();
    $this->artisan('hazri:devices:check')->assertSuccessful();

    expect($device->fresh()->status)->toBe('offline');
    Event::assertDispatchedTimes(DeviceWentOffline::class, 1);
});
