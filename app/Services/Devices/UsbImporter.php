<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Actions\Punches\IngestOutcome;
use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\PunchData;
use App\Enums\PunchSource;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\UsbImport;
use App\Models\User;
use Carbon\CarbonImmutable;

/** USB log import: preview → import through the normal pipeline, with an import record + log (spec §6.3). */
final class UsbImporter
{
    public function __construct(
        private readonly UsbLogParser $parser,
        private readonly IngestPunchAction $ingest,
    ) {}

    /**
     * @return array{total: int, duplicates: int, unmapped: array<string, int>, from: ?string, to: ?string, errors: list<string>, format: string}
     */
    public function preview(string $path, string $originalName, ?Device $device): array
    {
        $parsed = $this->parser->parse($path, $originalName);
        $pins = Employee::query()->whereNotNull('device_pin')->pluck('id', 'device_pin');
        $codes = Employee::query()->pluck('id', 'code');

        $duplicates = 0;
        $unmapped = [];
        $dates = [];
        foreach ($parsed['rows'] as $r) {
            $dates[] = substr($r['local'], 0, 10);
            $known = isset($pins[$r['pin']]) || (($r['is_code'] ?? false) && isset($codes[$r['pin']]));
            if (! $known) {
                $unmapped[$r['pin']] = ($unmapped[$r['pin']] ?? 0) + 1;
            }
            if (Punch::withTrashed()->where('dedupe_hash', $this->data($r, $device, null)->dedupeHash())->exists()) {
                $duplicates++;
            }
        }
        sort($dates);

        return [
            'total' => count($parsed['rows']),
            'duplicates' => $duplicates,
            'unmapped' => $unmapped,
            'from' => $dates[0] ?? null,
            'to' => $dates ? end($dates) : null,
            'errors' => array_slice($parsed['errors'], 0, 50),
            'format' => $parsed['format'],
        ];
    }

    public function import(string $storedPath, string $absolutePath, string $originalName, ?Device $device, User $by): UsbImport
    {
        $parsed = $this->parser->parse($absolutePath, $originalName);
        $codes = Employee::query()->pluck('id', 'code');
        $counts = array_fill_keys(array_map(fn (IngestOutcome $o) => $o->value, IngestOutcome::cases()), 0);

        foreach ($parsed['rows'] as $r) {
            $employeeId = ($r['is_code'] ?? false) ? ($codes[$r['pin']] ?? null) : null;
            $result = ($this->ingest)($this->data($r, $device, $employeeId));
            $counts[$result->outcome->value]++;
        }

        $device?->forceFill(['last_sync_at' => now()])->saveQuietly();

        return UsbImport::create([
            'device_id' => $device?->id,
            'file_path' => $storedPath,
            'rows_total' => count($parsed['rows']),
            'rows_imported' => $counts[IngestOutcome::Stored->value],
            'rows_duplicate' => $counts[IngestOutcome::AlreadyStored->value] + $counts[IngestOutcome::DoubleTap->value],
            'rows_unmapped' => $counts[IngestOutcome::Unmapped->value],
            'imported_by' => $by->id,
            'log' => ['counts' => $counts, 'errors' => $parsed['errors'], 'format' => $parsed['format'], 'file' => $originalName],
        ]);
    }

    /** @param array<string, mixed> $r */
    private function data(array $r, ?Device $device, ?int $employeeId): PunchData
    {
        $tz = $device?->timezone ?? tenant('timezone') ?? config('hazri.default_timezone');

        return new PunchData(
            punchedAt: CarbonImmutable::parse($r['local'], $tz)->utc(),
            source: PunchSource::Usb,
            devicePin: $employeeId === null ? $r['pin'] : null,
            employeeId: $employeeId,
            deviceId: $device?->id,
            // Same serial as ADMS/agent would use, so a USB re-import of already-pushed punches dedupes.
            deviceSerial: $device?->serial_number ?? 'usb',
            direction: $r['direction'],
            verifyType: ZkCodes::verify((int) $r['verify']),
            workcode: $r['workcode'],
        );
    }
}
