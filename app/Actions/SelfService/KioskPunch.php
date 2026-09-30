<?php

declare(strict_types=1);

namespace App\Actions\SelfService;

use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\IngestResult;
use App\Actions\Punches\PunchData;
use App\Enums\EmployeeStatus;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\VerifyType;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Services\SelfService\PunchPhotoStore;
use App\Services\SelfService\QrBadge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tablet kiosk punch (spec §6.5). Server time is authoritative, except for punches the kiosk queued while
 * offline: those carry the time they were taken (≤ 24 h old, not in the future) and a client id, so a replay
 * produces the same dedupe hash and is ignored.
 */
final class KioskPunch
{
    public function __construct(
        private readonly IngestPunchAction $ingest,
        private readonly PunchPhotoStore $photos,
        private readonly QrBadge $qr,
    ) {}

    /**
     * @param  array{mode: string, pin?: ?string, kiosk_pin?: ?string, qr?: ?string, direction?: ?string, photo?: ?string,
     *               client_time?: ?string, client_id?: ?string, offline?: bool}  $input
     * @return array{result: IngestResult, employee: Employee, direction: PunchDirection, at: CarbonImmutable}
     */
    public function __invoke(Kiosk $kiosk, array $input): array
    {
        $employee = $this->identify($kiosk, $input);
        if ($kiosk->require_photo && empty($input['photo'])) {
            throw ValidationException::withMessages(['photo' => __('A photo is required.')]);
        }

        $at = CarbonImmutable::now('UTC');
        if (! empty($input['offline']) && ! empty($input['client_time'])) {
            $client = CarbonImmutable::parse($input['client_time'])->utc();
            if ($client->lessThanOrEqualTo($at->addMinutes(2)) && $client->greaterThan($at->subDay())) {
                $at = $client->startOfSecond();
            }
        }

        $direction = PunchDirection::tryFrom((string) ($input['direction'] ?? '')) ?? SelfPunch::nextDirection($employee->id);

        $result = ($this->ingest)(new PunchData(
            punchedAt: $at,
            source: PunchSource::Kiosk,
            employeeId: $employee->id,
            deviceId: $kiosk->device_id,
            deviceSerial: 'kiosk:' . $kiosk->id,
            direction: $direction,
            verifyType: match ($input['mode']) {
                'qr' => VerifyType::Card, default => VerifyType::Pin,
            },
            photoPath: $this->photos->store($input['photo'] ?? null),
            externalId: $input['client_id'] ?? null,
            meta: array_filter(['client_time' => $input['client_time'] ?? null, 'mode' => $input['mode'], 'offline' => ! empty($input['offline'])]),
        ));

        $kiosk->device?->forceFill(['last_seen_at' => now(), 'status' => 'online', 'last_sync_at' => now()])->saveQuietly();

        return ['result' => $result, 'employee' => $employee, 'direction' => $direction, 'at' => $at];
    }

    private function identify(Kiosk $kiosk, array $input): Employee
    {
        $fail = fn () => throw ValidationException::withMessages(['pin' => __('Not recognised. Please try again.')]);
        $mode = (string) ($input['mode'] ?? 'pin');
        if (! $kiosk->allows($mode)) {
            throw ValidationException::withMessages(['mode' => __('This kiosk does not accept :m.', ['m' => $mode])]);
        }

        $employee = match ($mode) {
            'pin' => Employee::query()->where('device_pin', (string) ($input['pin'] ?? ''))->first(),
            'qr' => ($id = $this->qr->verify((string) ($input['qr'] ?? ''))) ? Employee::query()->find($id) : null,
            default => null,
        };
        if ($employee === null || $employee->status === EmployeeStatus::Left) {
            $fail();
        }
        if ($kiosk->branch_id && $employee->branch_id && $employee->branch_id !== $kiosk->branch_id) {
            throw ValidationException::withMessages(['pin' => __('Please use the kiosk at your own branch.')]);
        }
        // Optional second factor in PIN mode: the 4-digit kiosk PIN, when the employee has one.
        if ($mode === 'pin' && $employee->kiosk_pin !== null) {
            if (empty($input['kiosk_pin'])) {
                throw ValidationException::withMessages(['kiosk_pin' => __('Enter your 4-digit kiosk PIN.')]);
            }
            if (! Hash::check((string) $input['kiosk_pin'], $employee->kiosk_pin)) {
                $fail();
            }
        }

        return $employee;
    }
}
