<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Punches\IngestOutcome;
use App\Actions\SelfService\KioskPunch;
use App\Enums\PunchDirection;
use App\Models\Kiosk;
use App\Services\SelfService\KioskDeviceGuard;
use App\Services\Settings\TenantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'name' => $r['employee']->first_name,
            'direction' => $r['direction']->value,
            'time' => $r['at']->setTimezone($tz)->format('H:i'),
        ]);
    }

    private function kiosk(string $token, TenantSettings $settings): Kiosk
    {
        abort_unless($settings->featureEnabled('kiosk'), 404);
        $kiosk = Kiosk::query()->where('token', $token)->first();
        abort_if($kiosk === null, 404);

        return $kiosk;
    }
}
