<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Models\Kiosk;
use App\Models\KioskDevice;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Stops a kiosk link being used away from the office. A locked kiosk only accepts tablets an admin approved
 * (each tablet keeps a random key in its browser storage); a kiosk with allowed IPs only accepts requests
 * from those addresses (the office internet connection).
 */
final class KioskDeviceGuard
{
    /**
     * @return array{ok: bool, status: string, message: ?string, code: ?string}
     *                                                                          status: approved|open|pending|blocked|ip_blocked|no_key
     */
    public function check(Kiosk $kiosk, ?string $deviceKey, ?string $ip, ?string $userAgent = null): array
    {
        $ips = array_values(array_filter(array_map('trim', $kiosk->allowed_ips ?? [])));
        if ($ips !== [] && ($ip === null || ! IpUtils::checkIp($ip, $ips))) {
            return $this->deny('ip_blocked', __('This kiosk only works on the office internet connection.'));
        }
        if (! $kiosk->lock_to_device) {
            return ['ok' => true, 'status' => 'open', 'message' => null, 'code' => null];
        }
        if ($deviceKey === null || strlen($deviceKey) < 32) {
            return $this->deny('no_key', __('This browser cannot be used as a kiosk.'));
        }

        $device = KioskDevice::query()->firstOrCreate(
            ['kiosk_id' => $kiosk->id, 'key_hash' => hash('sha256', $deviceKey)],
            ['pairing_code' => Str::upper(Str::random(4)), 'label' => Str::limit((string) $userAgent, 250), 'ip' => $ip],
        );
        $device->forceFill(['last_seen_at' => now(), 'ip' => $ip])->saveQuietly();

        return match ($device->status) {
            KioskDevice::APPROVED => ['ok' => true, 'status' => 'approved', 'message' => null, 'code' => $device->pairing_code],
            KioskDevice::BLOCKED => $this->deny('blocked', __('This tablet has been blocked for this kiosk.'), $device->pairing_code),
            default => $this->deny('pending', __('This tablet is waiting for approval. Ask HR to approve code :code.', ['code' => $device->pairing_code]),
                $device->pairing_code),
        };
    }

    /** @return array{ok: false, status: string, message: string, code: ?string} */
    private function deny(string $status, string $message, ?string $code = null): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'code' => $code];
    }
}
