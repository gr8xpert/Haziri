<?php

declare(strict_types=1);

namespace App\Actions\SelfService;

use App\Actions\Punches\IngestOutcome;
use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\IngestResult;
use App\Actions\Punches\PunchData;
use App\Enums\EmployeeStatus;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\VerifyType;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Services\SelfService\FaceMatcher;
use App\Services\SelfService\PunchPhotoStore;
use App\Services\SelfService\QrBadge;
use App\Services\Settings\TenantSettings;
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
        private readonly FaceMatcher $faces,
        private readonly TenantSettings $settings,
    ) {}

    /** @var array{employee_id: ?int, similarity: float, runner_up: float}|null last face match, recorded on the punch */
    private ?array $faceMatch = null;

    /**
     * @param  array{mode: string, pin?: ?string, kiosk_pin?: ?string, qr?: ?string, direction?: ?string, photo?: ?string,
     *               client_time?: ?string, client_id?: ?string, offline?: bool, face?: ?list<float>}  $input
     * @return array{result: IngestResult, employee: Employee, direction: PunchDirection, at: CarbonImmutable, kept: ?Punch} kept = the earlier punch when this one was a double tap
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

        $direction = PunchDirection::tryFrom((string) ($input['direction'] ?? '')) ?? SelfPunch::nextDirection($employee->id, $at);
        // PIN / QR: someone else may know the PIN or hold the card. Check the face in the photo against the enrolled one.
        $faceCheck = $input['mode'] === 'face' ? null : $this->faces->checkPunch($employee, $input['face'] ?? null);
        // The descriptor is worked out on the tablet, so (as for face mode) it is only trusted from a tablet HR approved:
        // from any other browser it could be computed from someone's picture. Unlocked kiosk → can't verify → approval.
        if ($faceCheck !== null && ! $kiosk->lock_to_device) {
            $faceCheck = ['result' => 'unverified_device', 'similarity' => null];
        }

        $result = ($this->ingest)(new PunchData(
            punchedAt: $at,
            source: PunchSource::Kiosk,
            employeeId: $employee->id,
            deviceId: $kiosk->device_id,
            deviceSerial: 'kiosk:' . $kiosk->id,
            direction: $direction,
            verifyType: match ($input['mode']) {
                'qr' => VerifyType::Card, 'face' => VerifyType::Face, default => VerifyType::Pin,
            },
            photoPath: $this->photos->store($input['photo'] ?? null),
            externalId: $input['client_id'] ?? null,
            meta: array_filter(['client_time' => $input['client_time'] ?? null, 'mode' => $input['mode'], 'offline' => ! empty($input['offline']),
                'face_similarity' => $this->faceMatch['similarity'] ?? null, 'face_runner_up' => $this->faceMatch['runner_up'] ?? null,
                'face_check' => $faceCheck]),
            requiresApproval: $faceCheck !== null && $faceCheck['result'] !== 'match',
        ));

        $kiosk->device?->forceFill(['last_seen_at' => now(), 'status' => 'online', 'last_sync_at' => now()])->saveQuietly();

        return ['result' => $result, 'employee' => $employee, 'direction' => $direction, 'at' => $at,
            'kept' => $result->outcome === IngestOutcome::DoubleTap ? $this->keptPunch($employee, $at) : null];
    }

    /** The punch that made this one a double tap: the employee's valid punch closest before $at. */
    private function keptPunch(Employee $employee, CarbonImmutable $at): ?Punch
    {
        return Punch::query()->where('employee_id', $employee->id)->where('status', PunchStatus::Valid)
            ->where('punched_at', '<=', $at->addMinute()->format('Y-m-d H:i:s'))
            ->orderByDesc('punched_at')->first();
    }

    /** Face mode: the tablet checked liveness and sends the face descriptor; we match it against enrolled faces. */
    private function identifyFace(Kiosk $kiosk, array $input): ?Employee
    {
        if (! $this->settings->featureEnabled('kiosk_face')) {
            throw ValidationException::withMessages(['mode' => __('Face recognition is not enabled for your company.')]);
        }
        // Liveness (head turn, anti-spoof) runs on the tablet and cannot be proven to the server, so a face punch is only
        // accepted from a tablet HR approved (KioskDeviceGuard already checked the device key) and always with a photo
        // for audit. Otherwise anyone with the kiosk link could replay a descriptor computed from someone's picture.
        if (! $kiosk->lock_to_device) {
            throw ValidationException::withMessages(['mode' => __('Face punching needs a kiosk locked to approved tablets.')]);
        }
        if (empty($input['photo'])) {
            throw ValidationException::withMessages(['photo' => __('A photo is required for face punching.')]);
        }
        $descriptor = FaceMatcher::clean($input['face'] ?? null);
        if ($descriptor === null || empty($input['liveness_passed'])) {
            throw ValidationException::withMessages(['face' => __('Face not captured. Look at the camera and try again.')]);
        }
        $match = $this->faces->identify($descriptor, $kiosk->branch_id);
        $this->faceMatch = $match;
        if ($match['employee_id'] === null) {
            throw ValidationException::withMessages(['face' => __('Face not recognised. Please use your PIN.')]);
        }

        return Employee::query()->find($match['employee_id']);
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
            'face' => $this->identifyFace($kiosk, $input),
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
