<?php

declare(strict_types=1);

use App\Enums\DevicePunchSourceType;
use App\Models\Central\AgentKey;
use App\Models\Device;
use App\Services\Devices\AgentSetupFile;

it('writes a ready agent.yaml with a new key and the company\'s agent machines', function () {
    Device::create(['name' => 'Karachi office', 'serial_number' => 'PEST-AGT-1', 'type' => DevicePunchSourceType::Agent, 'ip' => '192.168.1.201', 'comm_key' => '1234']);
    Device::create(['name' => 'New "gate" machine', 'serial_number' => 'PEST-AGT-2', 'type' => DevicePunchSourceType::Agent]);
    Device::create(['name' => 'Cloud machine', 'serial_number' => 'PEST-ADMS-9', 'type' => DevicePunchSourceType::Adms]);

    $yaml = app(AgentSetupFile::class)->make('https');
    preg_match('/^api_key: (\S+)$/m', $yaml, $key);

    expect($yaml)->toContain('server: https://' . config('hazri.api_domain'))
        ->and(AgentKey::findActive($key[1] ?? '')?->tenant_id)->toBe(tenant('id'))   // a real, working key for this company
        ->and($yaml)->toContain('serial: "PEST-AGT-1"')->toContain('ip: "192.168.1.201"')->toContain('password: 1234')
        ->and($yaml)->toContain('serial: "PEST-AGT-2"')->toContain('ip: 0.0.0.0')     // IP still to fill in
        ->and($yaml)->not->toContain('PEST-ADMS-9');                                    // cloud machines don't use the agent
});

it('says when the program is not on the server', function () {
    $html = view('filament.tenant.agent-setup', ['program' => false])->render();

    expect($html)->toContain('not on this server yet')->toContain('hazri-agent.exe -c agent.yaml --once');
});
