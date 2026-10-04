<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Punches\IngestOutcome;
use App\Actions\SelfService\KioskPunch;
use App\Enums\PunchDirection;
use App\Models\Announcement;
use App\Models\AnnouncementAck;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Services\SelfService\KioskDeviceGuard;
use App\Services\Settings\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tablet kiosk (spec §6.5, §13.2): no login — the token in the URL identifies the kiosk. */
final class KioskController
{
    public function show(string $token, TenantSettings $settings): View
    {
        $kiosk = $this->kiosk($token, $settings);

        $modes = array_values(array_filter($kiosk->allowed_modes ?? ['pin'], fn ($m) => $m !== 'face' || $settings->featureEnabled('kiosk_face')));

        return view('kiosk.show', ['kiosk' => $kiosk, 'token' => $token, 'company' => tenant('company_name'), 'modes' => $modes ?: ['pin']]);
    }

    public function config(Request $request, string $token, TenantSettings $settings, KioskDeviceGuard $guard): JsonResponse
    {
        $kiosk = $this->kiosk($token, $settings);
        $access = $guard->check($kiosk, $request->query('device_key'), $request->ip(), $request->userAgent());

        return response()->json([
            'access' => $access,
            'company' => tenant('company_name'),
            'modes' => array_values(array_filter($kiosk->allowed_modes ?? ['pin'], fn ($m) => $m !== 'face' || $settings->featureEnabled('kiosk_face'))),
            'require_photo' => $kiosk->require_photo,
            // PIN / QR punches: send the face descriptor too, it is checked against the enrolled face.
            'face_check' => $settings->featureEnabled('kiosk_face') && (bool) $settings->get('face_check_photo_punches'),
            'branch' => $kiosk->branch?->name,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function punch(Request $request, string $token, KioskPunch $punch, TenantSettings $settings, KioskDeviceGuard $guard): JsonResponse
    {
        $kiosk = $this->kiosk($token, $settings);
        $access = $guard->check($kiosk, $request->input('device_key'), $request->ip(), $request->userAgent());
        if (! $access['ok']) {
            return response()->json(['ok' => false, 'access' => $access, 'message' => $access['message']], 403);
        }
        $data = $request->validate([
            'mode' => ['required', Rule::in(['pin', 'qr', 'face'])],
            'pin' => ['nullable', 'string', 'max:20'],
            'kiosk_pin' => ['nullable', 'digits:4'],
            'qr' => ['nullable', 'string', 'max:200'],
            'direction' => ['nullable', Rule::in(['auto', 'in', 'out'])],
            'photo' => ['nullable', 'string', 'max:2100000'],
            'client_time' => ['nullable', 'date'],
            'client_id' => ['nullable', 'uuid'],
            'offline' => ['nullable', 'boolean'],
            'device_key' => ['nullable', 'string', 'max:128'],
            'face' => ['nullable', 'array', 'size:1024'],
            'face.*' => ['numeric'],
            'liveness_passed' => ['nullable', 'boolean'],
        ]);

        try {
            $r = $punch($kiosk, $data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        if ($r['result']->outcome === IngestOutcome::DoubleTap) {
            // Nothing new was saved: say what is on record instead of pretending this tap counted.
            $kept = $r['kept'];
            $direction = in_array($kept?->direction, [PunchDirection::In, PunchDirection::Out], true) ? $kept->direction->value : null;
            $time = ($kept?->punched_at ?? $r['at'])->setTimezone($tz)->format('H:i');

            return response()->json([
                'ok' => true,
                'double_tap' => true,
                'name' => $r['employee']->first_name,
                'direction' => $direction,
                'time' => $time,
                'message' => $direction !== null
                    ? __('Already punched :dir at :time', ['dir' => strtoupper($direction), 'time' => $time])
                    : __('Already punched at :time', ['time' => $time]),
            ]);
        }

        return response()->json([
            'ok' => true,
            'replay' => $r['result']->outcome === IngestOutcome::AlreadyStored,
            'pending' => $r['result']->outcome === IngestOutcome::PendingApproval,
            'announcements' => $this->announcementsFor($r['employee'], $token),
            'name' => $r['employee']->first_name,
            'direction' => $r['direction']->value,
            'time' => $r['at']->setTimezone($tz)->format('H:i'),
        ]);
    }

    /** "I have read it" tapped on the kiosk; the signed link (from the punch response) says who and what. */
    public function ack(string $token, Announcement $announcement, Employee $employee, TenantSettings $settings): JsonResponse
    {
        $this->kiosk($token, $settings);
        abort_unless($announcement->isFor($employee), 404);
        AnnouncementAck::query()->firstOrCreate(['announcement_id' => $announcement->id, 'employee_id' => $employee->id], ['acked_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Notices to show after a punch, for workers without the app: ones still waiting for this employee's
     * acknowledgement, and other announcements from the last 2 days. At most 3.
     *
     * @return list<array{id: int, title: string, body: string, requires_ack: bool, ack_url: ?string}>
     */
    private function announcementsFor(Employee $employee, string $token): array
    {
        $recent = now()->subHours(48);

        return Announcement::visibleTo($employee)->get()
            ->filter(fn (Announcement $a) => $a->requires_ack
                ? ! $a->acks->contains('employee_id', $employee->id)
                : ($a->publish_at ?? $a->created_at)->greaterThanOrEqualTo($recent))
            ->take(3)
            ->map(fn (Announcement $a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'requires_ack' => $a->requires_ack,
                'ack_url' => $a->requires_ack ? URL::temporarySignedRoute('kiosk.announcement-ack', now()->addMinutes(10),
                    ['token' => $token, 'announcement' => $a->id, 'employee' => $employee->id]) : null,
            ])->values()->all();
    }

    private function kiosk(string $token, TenantSettings $settings): Kiosk
    {
        abort_unless($settings->featureEnabled('kiosk'), 404);
        $kiosk = Kiosk::query()->where('token', $token)->first();
        abort_if($kiosk === null, 404);

        return $kiosk;
    }
}
