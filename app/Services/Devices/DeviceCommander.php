<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Enums\DevicePunchSourceType;
use App\Enums\EmployeeStatus;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use Illuminate\Validation\ValidationException;
use TanemRahman\ZktecoAdms\Models\ZktecoDevice;
use TanemRahman\ZktecoAdms\Services\CommandService;

/**
 * Remote commands for a device (spec §9 phase 2): sync time, reboot, push all employees, delete one.
 * ADMS devices → the package's command queue (served on /iclock/getrequest).
 * Agent devices → our device_commands table (served on /api/v1/agent/commands).
 */
final class DeviceCommander
{
    public function __construct(private readonly CommandService $adms) {}

    public function syncTime(Device $device): int
    {
        return $this->send($device, 'sync_time', [], fn (ZktecoDevice $z) => [$this->adms->syncTime($z)]);
    }

    public function reboot(Device $device): int
    {
        return $this->send($device, 'reboot', [], fn (ZktecoDevice $z) => [$this->adms->reboot($z)]);
    }

    /** Push PIN + name (+ card) of every active employee that has a machine PIN. Never templates. */
    public function pushEmployees(Device $device): int
    {
        $users = Employee::query()
            ->where('status', '!=', EmployeeStatus::Left)
            ->whereNotNull('device_pin')
            ->when($device->branch_id, fn ($q) => $q->where(fn ($b) => $b->where('branch_id', $device->branch_id)->orWhereNull('branch_id')))
            ->get()
            ->map(fn (Employee $e) => ['pin' => $e->device_pin, 'name' => mb_substr($e->fullName(), 0, 24), 'card' => $e->card_no])
            ->all();

        return $this->send($device, 'set_user', ['users' => $users], fn (ZktecoDevice $z) => $this->adms->addUsers($z, $users));
    }

    public function deleteEmployee(Device $device, string $pin): int
    {
        return $this->send($device, 'delete_user', ['pin' => $pin], fn (ZktecoDevice $z) => [$this->adms->deleteUser($z, $pin)]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(ZktecoDevice): array<int, mixed>  $viaAdms
     * @return int number of commands queued
     */
    private function send(Device $device, string $command, array $payload, callable $viaAdms): int
    {
        return match ($device->type) {
            DevicePunchSourceType::Adms => count($viaAdms($this->admsDevice($device))),
            DevicePunchSourceType::Agent => $this->queueForAgent($device, $command, $payload),
            default => throw ValidationException::withMessages(['device' => __('This device type does not accept remote commands.')]),
        };
    }

    private function queueForAgent(Device $device, string $command, array $payload): int
    {
        if ($command === 'set_user') {
            // One command per user keeps agent retries small and results per PIN.
            foreach ($payload['users'] as $user) {
                DeviceCommand::create(['device_id' => $device->id, 'command' => 'set_user', 'payload' => $user]);
            }

            return count($payload['users']);
        }
        DeviceCommand::create(['device_id' => $device->id, 'command' => $command, 'payload' => $payload ?: null]);

        return 1;
    }

    private function admsDevice(Device $device): ZktecoDevice
    {
        return ZktecoDevice::query()->firstOrCreate(
            ['serial' => $device->serial_number],
            ['name' => $device->name, 'protocol' => 'adms', 'status' => true, 'port' => 443],
        );
    }
}
