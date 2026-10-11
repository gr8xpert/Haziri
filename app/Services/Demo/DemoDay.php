<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\EmployeeStatus;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Visitor;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One working day of the demo company, the same every time for a given date: who comes, when (around their own
 * shift's start), who is late or absent, who forgets to punch out. Past days are filled by the seeder; today is
 * played out by hazri:demo:live as the clock passes each time, so the dashboard always shows a believable "today".
 * In the last two days a few people punch at the kiosk (with photo and face score) or from their phone (waiting
 * for approval), so those pages are never empty.
 */
final class DemoDay
{
    use DemoSteps;

    public function __construct(
        private readonly ShiftResolver $shifts,
        private readonly DemoPunches $punches,
        private readonly AttendanceRecomputer $recomputer,
    ) {}

    /**
     * Make every planned punch of $date that happened before $until (missing ones only).
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<int> ids of employees who got a new punch
     */
    public function play(Collection $employees, string $date, CarbonImmutable $until, bool $selfService): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $machine = Device::where('serial_number', self::serial('ADMS-01'))->first();
        // Karachi staff punch on the Karachi machine: one machine for both cities looks like impossible travel.
        $branchMachines = Device::query()->whereIn('serial_number', [self::serial('ADMS-01'), self::serial('AGENT-01')])
            ->whereNotNull('branch_id')->get()->keyBy('branch_id');
        $tablet = Device::where('serial_number', self::serial('KIOSK'))->first();
        if ($machine === null) {
            return [];
        }
        $changed = [];
        foreach ($employees as $e) {
            foreach ($this->plan($e, $date, $tz) as [$at, $how]) {
                if ($at->greaterThan($until)) {
                    continue;
                }
                $made = match ($selfService && $tablet !== null ? $how : 'machine') {
                    'machine' => $this->punches->machine($e, $branchMachines->get($e->branch_id) ?? $machine, $at),
                    'kiosk', 'kiosk_mismatch', 'kiosk_no_face' => $this->punches->self($e, $tablet, $at, PunchSource::Kiosk,
                        $how === 'kiosk' ? PunchStatus::Valid : PunchStatus::Pending, ['mode' => 'pin', 'face_check' => match ($how) {
                            'kiosk' => ['result' => 'match', 'similarity' => 0.84 + (crc32($e->code) % 12) / 100],
                            'kiosk_mismatch' => ['result' => 'mismatch', 'similarity' => 0.34],
                            default => ['result' => 'no_face', 'similarity' => null],
                        }]),
                    'phone_far' => $this->punches->self($e, null, $at, PunchSource::Mobile, PunchStatus::Pending, [
                        'geofence_fail' => true, 'geofence' => ['branch_id' => $e->branch_id, 'distance_m' => 2350, 'office_network' => null],
                        'face_check' => ['result' => 'match', 'similarity' => 0.89]], [31.4697, 74.2728]),
                    'phone_off_network' => $this->punches->self($e, null, $at, PunchSource::Mobile, PunchStatus::Pending, [
                        'geofence' => ['branch_id' => $e->branch_id, 'distance_m' => 40, 'office_network' => false],
                        'face_check' => ['result' => 'match', 'similarity' => 0.86]], self::branchSpot($e)),
                };
                if ($made) {
                    $changed[$e->id] = $e->id;
                }
            }
        }

        return array_values($changed);
    }

    /**
     * Today so far (and the end of yesterday, e.g. night-shift punch-outs after midnight), then recalculate the
     * days that changed. Run every few minutes by hazri:demo:live.
     *
     * @return int employees updated
     */
    public function catchUp(CarbonImmutable $now): int
    {
        $employees = self::staff();
        $updated = 0;
        foreach ([$now->subDay(), $now] as $day) {
            $date = $day->format('Y-m-d');
            $ids = $this->play($employees, $date, $now, selfService: true);
            foreach ($ids as $id) {
                $this->recomputer->recompute($id, $date, CarbonImmutable::now('UTC'));
            }
            $updated += count($ids);
        }
        $this->visitors($employees, $now);

        return $updated;
    }

    /**
     * Visitor log: the last few weeks, and today's visitors as they arrive (and leave), each with a badge number per
     * day.
     *
     * @param  Collection<int, Employee>  $employees
     */
    public function visitors(Collection $employees, CarbonImmutable $now): void
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $lhr = Branch::where('code', 'LHE')->first();
        $khi = Branch::where('code', 'KHI')->first();
        if ($lhr === null || $khi === null || $employees->count() < 4) {
            return;
        }
        $visits = [
            // [days ago, in, out (null = stays inside), name, company, purpose, host index, branch]
            [0, '09:40', null, 'Imran Qureshi', 'Habib Bank', 'Payroll account opening', 1, $lhr],
            [0, '10:15', '10:50', 'Sana Javed', 'Al-Noor Manpower', 'Contract staff attendance', 3, $lhr],
            [0, '11:30', '16:45', 'Waqas Butt', 'TechServe IT', 'Fingerprint machine service', 2, $lhr],
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
            $day = $now->subDays($ago)->format('Y-m-d');
            $badges[$day] = ($badges[$day] ?? 0) + 1;
            $checkIn = CarbonImmutable::parse("{$day} {$in}", $tz);
            if ($checkIn->greaterThan($now)) {
                continue; // not here yet
            }
            $checkOut = $out ? CarbonImmutable::parse("{$day} {$out}", $tz) : null;
            $visitor = Visitor::firstOrNew(['name' => $name, 'checked_in_at' => $checkIn->utc()]);
            $visitor->fill([
                'phone' => sprintf('0321%07d', 2000000 + crc32($name) % 1000000), 'company' => $company, 'purpose' => $purpose,
                'host_employee_id' => $employees[$host]->id, 'branch_id' => $branch->id, 'badge_no' => sprintf('V%03d', $badges[$day]),
                'checked_out_at' => $checkOut !== null && $checkOut->lessThanOrEqualTo($now) ? $checkOut->utc() : null,
            ]);
            if ($visitor->isDirty()) {
                $visitor->save();
            }
        }
    }

    /** @return Collection<int, Employee> current staff, by code (the demo's E001…E020) */
    public static function staff(): Collection
    {
        return Employee::query()->where('status', '!=', EmployeeStatus::Left)->orderBy('code')->get();
    }

    /**
     * The day's punches for one person: [local time, how]. Empty on their weekly off or before they joined.
     *
     * @return list<array{0: CarbonImmutable, 1: string}>
     */
    private function plan(Employee $e, string $date, string $tz): array
    {
        if ($e->joined_on !== null && $date < $e->joined_on->format('Y-m-d')) {
            return [];
        }
        $shift = $this->shifts->resolve($e, $date, $tz);
        if ($shift === null || in_array(CarbonImmutable::parse($date, $tz)->dayOfWeek, $shift->weeklyOffDays, true)) {
            return [];
        }

        mt_srand(crc32("{$date}|{$e->code}"));
        $roll = mt_rand(1, 100);
        if ($roll <= 4) {
            return []; // absent
        }
        $start = CarbonImmutable::parse("{$date} {$shift->startTime}", $tz);
        $end = CarbonImmutable::parse("{$date} {$shift->endTime}", $tz);
        if ($shift->crossesMidnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }
        $in = $start->subMinutes(15)->addMinutes(mt_rand(0, 25) + ($roll > 85 ? mt_rand(20, 150) : 0));
        $out = $end->addMinutes(mt_rand(-20, 90));

        $how = match ((int) substr((string) $e->code, 1)) {
            13, 14, 15 => 'kiosk', 16 => 'kiosk_mismatch', 17 => 'kiosk_no_face',
            8 => 'phone_far', 18 => 'phone_off_network', default => 'machine',
        };
        $punches = [[$in, $how]];
        if ($roll > 7) {
            $punches[] = [$out, 'machine']; // the rest forget to punch out
        }

        return $punches;
    }

    /** @return array{0: float, 1: float} a spot inside the employee's branch */
    private static function branchSpot(Employee $e): array
    {
        $branch = Branch::find($e->branch_id);

        return [(float) ($branch?->lat ?? 24.8607), (float) ($branch?->lng ?? 67.0011)];
    }
}
