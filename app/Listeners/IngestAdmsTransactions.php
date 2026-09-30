<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\PunchData;
use App\Enums\PunchSource;
use App\Models\Device;
use App\Services\Devices\ZkCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use TanemRahman\ZktecoAdms\Events\TransactionsReceived;

/** Package ATTLOG → our ingestion pipeline (spec §6.1). Runs inside the tenant initialised by ResolveAdmsTenant. */
final class IngestAdmsTransactions
{
    public function __construct(private readonly IngestPunchAction $ingest) {}

    public function handle(TransactionsReceived $event): void
    {
        if (! tenancy()->initialized) {
            Log::warning('ADMS transactions outside tenant context ignored', ['serial' => $event->device->serial]);

            return;
        }

        $device = Device::query()->where('serial_number', $event->device->serial)->first();
        foreach ($event->records as $record) {
            try {
                ($this->ingest)(new PunchData(
                    punchedAt: CarbonImmutable::parse((string) $record['timestamp'], 'UTC'),
                    source: PunchSource::Adms,
                    devicePin: (string) $record['pin'],
                    deviceId: $device?->id,
                    deviceSerial: $event->device->serial,
                    direction: ZkCodes::direction((int) ($record['status'] ?? 0)),
                    verifyType: ZkCodes::verify((int) ($record['verify'] ?? 0)),
                    workcode: isset($record['workcode']) && $record['workcode'] !== '' ? (string) $record['workcode'] : null,
                ));
            } catch (\Throwable $e) {
                // Never lose the rest of the batch; the package keeps the raw row in zkteco_transactions.
                Log::error('ADMS punch ingest failed', [
                    'tenant' => tenant('id'), 'serial' => $event->device->serial, 'record' => $record, 'error' => $e->getMessage(),
                ]);
            }
        }

        $device?->forceFill(['last_sync_at' => now()])->saveQuietly();
    }
}
