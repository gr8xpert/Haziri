<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DevicePunchSourceType;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\VerifyType;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Punch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Demo punches: machine fingerprints, and kiosk / phone punches with a generated photo. Idempotent (dedupe hash). */
final class DemoPunches
{
    private const COLOURS = [[16, 122, 87], [37, 99, 235], [180, 83, 9], [190, 24, 93], [91, 33, 182], [15, 118, 110]];

    /** @return bool whether a new punch was made */
    public function machine(Employee $e, Device $device, CarbonImmutable $local): bool
    {
        $utc = $local->utc();
        $punch = Punch::query()->firstOrCreate(['dedupe_hash' => sha1("{$device->serial_number}|{$e->device_pin}|{$utc->format('Y-m-d H:i:s')}")], [
            'employee_id' => $e->id, 'device_pin' => $e->device_pin, 'device_id' => $device->id, 'punched_at' => $utc->format('Y-m-d H:i:s'),
            'local_date' => $local->format('Y-m-d'), 'source' => $device->type === DevicePunchSourceType::Agent ? PunchSource::Agent : PunchSource::Adms,
            'status' => PunchStatus::Valid, 'verify_type' => VerifyType::Fingerprint,
        ]);

        return $punch->wasRecentlyCreated;
    }

    /**
     * A kiosk or phone punch with a generated photo (initials on a colour: no real faces in the demo).
     *
     * @param  array<string, mixed>  $meta
     * @param  array{0: float, 1: float}|null  $location
     * @return bool whether a new punch was made
     */
    public function self(Employee $e, ?Device $device, CarbonImmutable $local, PunchSource $source, PunchStatus $status, array $meta, ?array $location = null): bool
    {
        $utc = $local->utc();
        $hash = sha1("{$source->value}|{$e->id}|{$utc->format('Y-m-d H:i:s')}");
        if (Punch::where('dedupe_hash', $hash)->exists()) {
            return false;
        }
        Punch::create([
            'dedupe_hash' => $hash, 'employee_id' => $e->id, 'device_pin' => $e->device_pin, 'device_id' => $device?->id,
            'punched_at' => $utc->format('Y-m-d H:i:s'), 'local_date' => $local->format('Y-m-d'), 'direction' => PunchDirection::In,
            'source' => $source, 'status' => $status, 'verify_type' => $source === PunchSource::Kiosk ? VerifyType::Pin : VerifyType::Photo,
            'lat' => $location[0] ?? null, 'lng' => $location[1] ?? null, 'accuracy_m' => $location ? 18 : null,
            'photo_path' => $this->photo($e), 'meta' => $meta,
        ]);

        return true;
    }

    private function photo(Employee $e): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        [$r, $g, $b] = self::COLOURS[$e->id % count(self::COLOURS)];
        $image = imagecreatetruecolor(240, 320);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledellipse($image, 120, 130, 120, 140, imagecolorallocate($image, min(255, $r + 60), min(255, $g + 60), min(255, $b + 60)));
        $initials = mb_substr($e->first_name, 0, 1) . mb_substr((string) $e->last_name, 0, 1);
        imagestring($image, 5, 108, 122, $initials, $white);
        imagestring($image, 3, 70, 280, 'DEMO PHOTO', $white);
        ob_start();
        imagejpeg($image, null, 80);
        $path = 'photos/demo/' . Str::uuid() . '.jpg';
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    }
}
