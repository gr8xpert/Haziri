<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Punches\IngestOutcome;
use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\PunchData;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchSource;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\UnmappedPin;
use App\Services\Devices\ZkCodes;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Agent API (spec §13.1). Tenant context is set by AuthenticateAgent. */
final class AgentController
{
    public function punches(Request $request, IngestPunchAction $ingest): JsonResponse
    {
        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:20'],
            'device_serial' => ['required', 'string', 'max:50'],
            'punches' => ['required', 'array', 'max:5000'],
            'punches.*.pin' => ['required', 'string', 'max:20'],
            'punches.*.timestamp' => ['required', 'date'],
            'punches.*.status' => ['nullable', 'integer'],
            'punches.*.verify' => ['nullable', 'integer'],
            'punches.*.uid' => ['nullable', 'integer'],
        ]);
        $device = $this->device($data['device_serial']);
        $tz = $device->timezone ?? tenant('timezone') ?? config('hazri.default_timezone');

        $counts = array_fill_keys(array_map(fn (IngestOutcome $o) => $o->value, IngestOutcome::cases()), 0);
        $errors = 0;
        foreach ($data['punches'] as $p) {
            try {
                // Timestamps with an offset are honoured; bare ones are device-local time.
                $at = CarbonImmutable::parse($p['timestamp'], $tz)->utc();
                $result = $ingest(new PunchData(
                    punchedAt: $at,
                    source: PunchSource::Agent,
                    devicePin: (string) $p['pin'],
                    deviceId: $device->id,
                    deviceSerial: $device->serial_number,
                    direction: ZkCodes::direction((int) ($p['status'] ?? 0)),
                    verifyType: ZkCodes::verify((int) ($p['verify'] ?? 0)),
                    externalId: isset($p['uid']) ? (string) $p['uid'] : null,
                ));
                $counts[$result->outcome->value]++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Agent punch ingest failed', ['tenant' => tenant('id'), 'serial' => $device->serial_number, 'punch' => $p, 'error' => $e->getMessage()]);
            }
        }

        $device->forceFill(['last_sync_at' => now(), 'last_seen_at' => now(), 'status' => 'online', 'ip' => $request->ip()])->saveQuietly();

        return response()->json(['received' => count($data['punches']), 'errors' => $errors] + $counts, $errors > 0 ? 207 : 200);
    }

    /** Device user list (PIN + name + card only) — helps map PINs; never templates. */
    public function users(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_serial' => ['required', 'string', 'max:50'],
            'users' => ['required', 'array', 'max:10000'],
            'users.*.pin' => ['required', 'string', 'max:20'],
            'users.*.name' => ['nullable', 'string', 'max:100'],
            'users.*.card' => ['nullable', 'string', 'max:30'],
        ]);
        $device = $this->device($data['device_serial']);
        $known = Employee::query()->whereNotNull('device_pin')->pluck('device_pin')->flip();

        $new = 0;
        foreach ($data['users'] as $u) {
            if (isset($known[$u['pin']])) {
                continue;
            }
            $row = UnmappedPin::query()->firstOrNew(['device_id' => $device->id, 'device_pin' => $u['pin']]);
            $new += $row->exists ? 0 : 1;
            $row->first_seen_at ??= now();
            $row->count ??= 0;
            $row->device_user_name = $u['name'] ?? $row->device_user_name;
            $row->save();
        }

        return response()->json(['users' => count($data['users']), 'unmapped_new' => $new]);
    }

    public function commands(Request $request): JsonResponse
    {
        $request->validate(['device_serial' => ['required', 'string', 'max:50']]);
        $device = $this->device((string) $request->query('device_serial'));

        $commands = DeviceCommand::query()->where('device_id', $device->id)->where('status', 'pending')
            ->orderBy('id')->limit(50)->get();
        DeviceCommand::query()->whereIn('id', $commands->pluck('id'))->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json(['commands' => $commands->map(fn (DeviceCommand $c) => [
            'id' => $c->id, 'command' => $c->command, 'payload' => $c->payload ?? (object) [],
        ])->values()]);
    }

    public function commandResult(Request $request, int $command): JsonResponse
    {
        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $row = DeviceCommand::query()->findOrFail($command);
        $row->update(['status' => $data['ok'] ? 'done' : 'failed', 'completed_at' => now(), 'result' => $data['message'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:20'],
            'devices' => ['nullable', 'array'],
            'devices.*.serial' => ['required', 'string', 'max:50'],
            'devices.*.reachable' => ['required', 'boolean'],
            'devices.*.unsynced' => ['nullable', 'integer', 'min:0'],
            'devices.*.error' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['devices'] ?? [] as $d) {
            $device = Device::query()->where('serial_number', $d['serial'])->where('type', DevicePunchSourceType::Agent)->first();
            $device?->forceFill([
                'last_seen_at' => $d['reachable'] ? now() : $device->last_seen_at,
                'status' => $d['reachable'] ? 'online' : 'offline',
                'unsynced_count' => $d['unsynced'] ?? $device->unsynced_count,
                'settings' => array_merge($device->settings ?? [], [
                    'agent_version' => $data['agent_version'] ?? null, 'agent_seen_at' => now()->toIso8601String(), 'last_error' => $d['error'] ?? null,
                ]),
            ])->saveQuietly();
        }

        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]);
    }

    private function device(string $serial): Device
    {
        $device = Device::query()->where('serial_number', $serial)->where('type', DevicePunchSourceType::Agent)->first();
        abort_if($device === null, 404, 'Unknown device serial for this company. Add it under Devices first (type: agent).');

        return $device;
    }
}
