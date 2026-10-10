<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DevicePunchSourceType;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Enums\VerifyType;
use App\Models\Announcement;
use App\Models\AnnouncementAck;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Models\Kiosk;
use App\Models\KioskDevice;
use App\Models\Punch;
use App\Models\UnmappedPin;
use App\Models\UsbImport;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Devices\DeviceCommander;
use App\Services\SelfService\FaceMatcher;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kiosk and phone punching (face enrolment, photos with match scores, punches waiting for approval, tablets),
 * machine housekeeping (commands, a USB import, an unknown PIN), visitors and announcements.
 */
final class DemoSelfServiceSeeder
{
    use DemoSteps;

    private const COLOURS = [[16, 122, 87], [37, 99, 235], [180, 83, 9], [190, 24, 93], [91, 33, 182], [15, 118, 110]];

    public function __construct(private readonly DeviceCommander $commander) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Closure(string): void  $say
     */
    public function run(Collection $employees, CarbonImmutable $today, Closure $say): void
    {
        $this->say = $say;
        $owner = User::role(Role::Owner->value)->firstOrFail();
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $this->faces($employees, $owner);
        $this->kiosk($employees, $today, $tz, $owner);
        $this->phonePunches($employees, $today, $tz);
        $this->machines($owner);
        $this->visitors($employees, $today, $tz);
        $this->announcements($employees, $today, $owner);
    }

    /** 16 of 20 enrolled; the last four still have to be (Face enrolment shows who). */
    private function faces(Collection $employees, User $owner): void
    {
        foreach ($employees->take(16) as $i => $e) {
            mt_srand(1000 + $i);
            FaceTemplate::firstOrCreate(['employee_id' => $e->id], [
                'descriptor' => array_map(fn () => round(mt_rand(-1000, 1000) / 1000, 4), range(1, FaceMatcher::DESCRIPTOR_LENGTH)),
                'quality' => 0.86 + ($i % 10) / 100, 'created_by' => $owner->id,
            ]);
        }
    }

    /**
     * Reception tablet punches with photos and face-match scores: today's late arrivals and a few yesterday. One photo
     * doesn't match and one has no face, so "Suspicious only" on Punch photos has something to show.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function kiosk(Collection $employees, CarbonImmutable $today, string $tz, User $owner): void
    {
        $tablet = Device::where('serial_number', self::serial('KIOSK'))->firstOrFail();
        // [employee, days ago, time, face check, similarity]
        $punches = [
            [12, 0, '09:14', 'match', 0.91], [13, 0, '09:17', 'match', 0.88], [14, 0, '09:21', 'match', 0.84],
            [15, 0, '09:26', 'mismatch', 0.34], [16, 0, '09:33', 'no_face', null],
            [2, 1, '08:52', 'match', 0.9], [6, 1, '08:58', 'match', 0.87], [10, 1, '09:05', 'match', 0.93],
        ];
        foreach ($punches as [$i, $ago, $time, $result, $similarity]) {
            $e = $employees[$i];
            $local = CarbonImmutable::parse(self::workday($today->subDays($ago), -1)->format('Y-m-d') . " {$time}", $tz);
            $this->selfPunch($e, $tablet, $local, PunchSource::Kiosk, $result === 'match' ? PunchStatus::Valid : PunchStatus::Pending,
                ['face_check' => ['result' => $result, 'similarity' => $similarity], 'mode' => 'pin']);
        }

        $kiosk = Kiosk::where('device_id', $tablet->id)->firstOrFail();
        KioskDevice::firstOrCreate(['kiosk_id' => $kiosk->id, 'label' => 'Lahore reception tablet'], [
            'key_hash' => hash('sha256', Str::random(40)), 'pairing_code' => 'LHR-4821', 'status' => KioskDevice::APPROVED,
            'ip' => '39.40.12.8', 'decided_by' => $owner->id, 'decided_at' => now()->subDays(20), 'last_seen_at' => now()->subMinutes(4),
        ]);
        KioskDevice::firstOrCreate(['kiosk_id' => $kiosk->id, 'label' => 'Unknown tablet'], [
            'key_hash' => hash('sha256', Str::random(40)), 'pairing_code' => 'QX7-1150', 'status' => KioskDevice::PENDING,
            'ip' => '182.185.77.40', 'last_seen_at' => now()->subHours(2),
        ]);
    }

    /** Field staff punching from their phones: two are waiting for approval (outside the office / not on its internet). */
    private function phonePunches(Collection $employees, CarbonImmutable $today, string $tz): void
    {
        $lahore = Branch::where('code', 'LHE')->firstOrFail();
        $at = fn (string $time) => CarbonImmutable::parse("{$today->format('Y-m-d')} {$time}", $tz);
        $this->selfPunch($employees[7], null, $at('09:41'), PunchSource::Mobile, PunchStatus::Pending, [
            'geofence_fail' => true, 'geofence' => ['branch_id' => $lahore->id, 'distance_m' => 2350, 'office_network' => null],
            'face_check' => ['result' => 'match', 'similarity' => 0.89],
        ], [31.4697, 74.2728]);
        $this->selfPunch($employees[17], null, $at('09:48'), PunchSource::Mobile, PunchStatus::Pending, [
            'geofence' => ['branch_id' => $employees[17]->branch_id, 'distance_m' => 40, 'office_network' => false],
            'face_check' => ['result' => 'match', 'similarity' => 0.86],
        ], [24.8612, 67.0019]);
    }

    /**
     * Commands for the Karachi sync agent (one done, one waiting: the agent queue, unlike ADMS, is ours), a USB import
     * from the site without internet, an unknown PIN.
     */
    private function machines(User $owner): void
    {
        $adms = Device::where('serial_number', self::serial('ADMS-01'))->firstOrFail();
        $agent = Device::where('serial_number', self::serial('AGENT-01'))->firstOrFail();
        if (! DeviceCommand::where('device_id', $agent->id)->exists()) {
            $this->attempt('machine commands', function () use ($agent) {
                $this->commander->syncTime($agent);
                DeviceCommand::where('device_id', $agent->id)->update(['status' => 'done', 'sent_at' => now()->subHours(5),
                    'completed_at' => now()->subHours(5), 'result' => 'Clock set']);
                $this->commander->reboot($agent);
            });
        }

        $site = Device::firstOrCreate(['serial_number' => self::serial('USB-01')], ['name' => 'Sheikhupura site (USB)', 'type' => DevicePunchSourceType::Usb,
            'branch_id' => Branch::where('code', 'LHE')->value('id')]);
        UsbImport::firstOrCreate(['device_id' => $site->id, 'file_path' => 'usb-imports/demo/1_attlog.dat'], [
            'rows_total' => 412, 'rows_imported' => 380, 'rows_duplicate' => 28, 'rows_unmapped' => 4, 'imported_by' => $owner->id,
            'log' => ['unmapped_pins' => ['999' => 4]],
        ]);
        UnmappedPin::firstOrCreate(['device_id' => $adms->id, 'device_pin' => '999'], ['first_seen_at' => now()->subDays(3), 'count' => 3, 'device_user_name' => 'Guard Akram']);
    }

    /**
     * Visitor log: today (two still inside) and the last few days, each with a badge number per day.
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function visitors(Collection $employees, CarbonImmutable $today, string $tz): void
    {
        $lhr = Branch::where('code', 'LHE')->firstOrFail();
        $khi = Branch::where('code', 'KHI')->firstOrFail();
        $visits = [
            // [days ago, in, out (null = still inside), name, company, purpose, host index, branch]
            [0, '09:40', null, 'Imran Qureshi', 'Habib Bank', 'Payroll account opening', 1, $lhr],
            [0, '10:15', '10:50', 'Sana Javed', 'Al-Noor Manpower', 'Contract staff attendance', 3, $lhr],
            [0, '11:30', null, 'Waqas Butt', 'TechServe IT', 'Fingerprint machine service', 2, $lhr],
            [0, '12:05', '12:40', 'Ayesha Siddiqui', null, 'Job interview', 3, $khi],
            [1, '10:00', '11:20', 'Kamran Akmal', 'Packages Ltd', 'Sales meeting', 0, $lhr],
            [1, '14:30', '15:00', 'Nadia Hussain', 'TCS Courier', 'Document delivery', 1, $lhr],
            [2, '09:20', '09:45', 'Bilal Ashraf', 'K-Electric', 'Meter reading', 2, $khi],
            [3, '11:00', '13:10', 'Rizwan Ahmed', 'FBR', 'Tax audit', 1, $lhr],
            [9, '10:30', '11:15', 'Hamid Mir', 'Jubilee Insurance', 'Group health policy renewal', 3, $lhr],
            [16, '15:00', '16:20', 'Saima Noor', 'EOBI office', 'Contribution inspection', 1, $khi],
            [24, '11:45', '12:30', 'Fahad Malik', 'PTCL', 'Internet line upgrade', 2, $lhr],
        ];
        $badges = [];
        foreach ($visits as [$ago, $in, $out, $name, $company, $purpose, $host, $branch]) {
            $day = $today->subDays($ago)->format('Y-m-d');
            $badges[$day] = ($badges[$day] ?? 0) + 1;
            Visitor::firstOrCreate(['name' => $name, 'checked_in_at' => CarbonImmutable::parse("{$day} {$in}", $tz)->utc()], [
                'phone' => sprintf('0321%07d', 2000000 + crc32($name) % 1000000), 'company' => $company, 'purpose' => $purpose,
                'host_employee_id' => $employees[$host]->id, 'branch_id' => $branch->id, 'badge_no' => sprintf('V%03d', $badges[$day]),
                'checked_out_at' => $out ? CarbonImmutable::parse("{$day} {$out}", $tz)->utc() : null,
            ]);
        }
    }

    /** One that needs a "read it" from everyone (half have), one past and one scheduled for next week. */
    private function announcements(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        $timings = Announcement::firstOrCreate(['title' => 'New office timings from next month'], [
            'body' => "From the 1st, office hours are 9:00 to 5:30 with a 45-minute lunch break.\nPlease plan your travel accordingly.",
            'requires_ack' => true, 'notify' => false, 'publish_at' => now()->subDays(3), 'created_by' => $owner->id]);
        foreach ($employees->take(10) as $i => $e) {
            AnnouncementAck::firstOrCreate(['announcement_id' => $timings->id, 'employee_id' => $e->id], ['acked_at' => now()->subDays(3)->addHours($i)]);
        }
        Announcement::firstOrCreate(['title' => 'Eid holidays'], [
            'body' => 'The office will remain closed for Eid. Wishing everyone Eid Mubarak!', 'requires_ack' => false, 'notify' => false,
            'publish_at' => $today->subDays(20), 'created_by' => $owner->id]);
        Announcement::firstOrCreate(['title' => 'Quarterly town hall'], [
            'body' => 'All staff: town hall in the Lahore conference room at 4 pm. Karachi joins on Zoom.', 'requires_ack' => false,
            'notify' => false, 'publish_at' => $today->addDays(6)->setTime(9, 0), 'created_by' => $owner->id]);
    }

    /**
     * A kiosk or phone punch with a generated photo (initials on a colour: no real faces in the demo).
     *
     * @param  array<string, mixed>  $meta
     * @param  array{0: float, 1: float}|null  $location
     */
    private function selfPunch(Employee $e, ?Device $device, CarbonImmutable $local, PunchSource $source, PunchStatus $status, array $meta, ?array $location = null): void
    {
        $utc = $local->utc();
        $hash = sha1("{$source->value}|{$e->id}|{$utc->format('Y-m-d H:i:s')}");
        if (Punch::where('dedupe_hash', $hash)->exists()) {
            return;
        }
        Punch::create([
            'dedupe_hash' => $hash, 'employee_id' => $e->id, 'device_pin' => $e->device_pin, 'device_id' => $device?->id,
            'punched_at' => $utc->format('Y-m-d H:i:s'), 'local_date' => $local->format('Y-m-d'), 'direction' => PunchDirection::In,
            'source' => $source, 'status' => $status, 'verify_type' => $source === PunchSource::Kiosk ? VerifyType::Pin : VerifyType::Photo,
            'lat' => $location[0] ?? null, 'lng' => $location[1] ?? null, 'accuracy_m' => $location ? 18 : null,
            'photo_path' => $this->photo($e), 'meta' => $meta,
        ]);
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
