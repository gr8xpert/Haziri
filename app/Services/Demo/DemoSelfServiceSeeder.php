<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DevicePunchSourceType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementAck;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Models\Kiosk;
use App\Models\KioskDevice;
use App\Models\UnmappedPin;
use App\Models\UsbImport;
use App\Models\User;
use App\Services\Devices\DeviceCommander;
use App\Services\SelfService\FaceMatcher;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kiosk and phone set-up (face enrolment, tablets; the punches themselves come from DemoDay),
 * machine housekeeping (commands, a USB import, an unknown PIN) and announcements. Visitors come from DemoDay.
 */
final class DemoSelfServiceSeeder
{
    use DemoSteps;

    public function __construct(private readonly DeviceCommander $commander) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Closure(string): void  $say
     */
    public function run(Collection $employees, CarbonImmutable $today, Closure $say): void
    {
        $this->say = $say;
        $owner = User::role(Role::Owner->value)->firstOrFail();
        $this->faces($employees, $owner);
        $this->tablets($owner);
        $this->machines($owner);
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

    /** The approved reception tablet and one unknown tablet waiting (Kiosk tablets). Kiosk punches come from DemoDay. */
    private function tablets(User $owner): void
    {
        $tablet = Device::where('serial_number', self::serial('KIOSK'))->firstOrFail();
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
}
