<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Enums\DevicePunchSourceType;
use App\Models\Central\AgentKey;
use App\Models\Device;
use App\Support\Brand;

/**
 * A ready agent.yaml for the sync agent: our server address, a new agent key, and every machine of type "agent"
 * with its LAN IP and comm key. IT copies it next to hazri-agent.exe and starts the service; nothing to type in.
 */
final class AgentSetupFile
{
    /** Where the built program is served from (public/downloads, shipped with the release, not in git). */
    public const PROGRAM = 'downloads/hazri-agent.exe';

    public static function programAvailable(): bool
    {
        return is_file(public_path(self::PROGRAM));
    }

    /** A new key labelled after the download, so it can be revoked on its own under Devices → Agent keys. */
    public function make(string $scheme = 'https', ?int $port = null): string
    {
        ['plain' => $key] = AgentKey::issue((string) tenant('id'), __('Setup file :date', ['date' => now()->format('d M Y H:i')]));
        $server = $scheme . '://' . Brand::host('api') . ($port && ! in_array($port, [80, 443], true) ? ":{$port}" : '');

        $lines = [
            '# Haziri sync agent settings for ' . tenant('company_name') . ', made ' . now()->format('d M Y H:i') . '.',
            '# Keep this file private: the api_key lets the agent send punches for your company.',
            "# Put it next to hazri-agent.exe as agent.yaml. Fill in any ip left as 0.0.0.0 (the machine's LAN address).",
            'server: ' . $server,
            'api_key: ' . $key,
            'poll_seconds: 60',
            'state_file: hazri-agent.sqlite',
            'verify_tls: true',
            '',
            'devices:',
        ];
        $devices = Device::query()->where('type', DevicePunchSourceType::Agent)->orderBy('name')->get();
        foreach ($devices as $device) {
            $lines[] = '  - serial: ' . self::yaml($device->serial_number) . '   # ' . str_replace(["\r", "\n"], ' ', $device->name);
            $lines[] = '    ip: ' . (filled($device->ip) ? self::yaml($device->ip) : '0.0.0.0');
            $lines[] = '    port: 4370';
            // ZKTeco comm keys are numbers, and the machine library needs one (0 = none).
            $lines[] = '    password: ' . (ctype_digit((string) $device->comm_key) ? (int) $device->comm_key : 0);
            $lines[] = '    clear_after_sync: false';
        }
        if ($devices->isEmpty()) {
            $lines[] = '  []   # add the machine under Devices (type: agent) first, then download this file again';
        }

        return implode("\n", $lines) . "\n";
    }

    /** A YAML scalar that stays a string whatever it contains (quotes escaped). */
    private static function yaml(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
