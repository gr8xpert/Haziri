<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Employee QR badge (spec §6.5): "hz1.{tenant}.{employee}.{Ymd}.{hmac}", valid only for its date (rotates daily)
 * and only in its own tenant. Scanned by the kiosk in QR mode.
 */
final class QrBadge
{
    public function token(Employee $employee, ?CarbonImmutable $day = null): string
    {
        $date = ($day ?? $this->today())->format('Ymd');
        $payload = 'hz1.' . tenant('id') . ".{$employee->id}.{$date}";

        return $payload . '.' . substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20);
    }

    /** @return int|null employee id when valid for today in this tenant */
    public function verify(string $token): ?int
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 5 || $parts[0] !== 'hz1') {
            return null;
        }
        [, $tenantId, $employeeId, $date, $sig] = $parts;
        $payload = "hz1.{$tenantId}.{$employeeId}.{$date}";
        $expected = substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 20);

        if (! hash_equals($expected, $sig) || $tenantId !== (string) tenant('id') || $date !== $this->today()->format('Ymd')) {
            return null;
        }

        return (int) $employeeId;
    }

    public function svg(Employee $employee): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'addQuietzone' => true,
        ])))->render($this->token($employee));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'));
    }
}
