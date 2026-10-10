<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Rosters\GenerateMonthlyRoster;
use App\Enums\ExemptionType;
use App\Enums\HolidayType;
use App\Enums\Role;
use App\Enums\ShiftOverrideScope;
use App\Enums\ShiftSwapStatus;
use App\Models\AttendanceExemption;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayOptIn;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftOverride;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/** Demo calendar: an optional and a company holiday, excused days, timing changes, shifts, a roster and shift swaps. */
final class DemoCalendarSeeder
{
    use DemoSteps;

    public function __construct(private readonly GenerateMonthlyRoster $roster, private readonly PayCycle $cycle) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Closure(string): void  $say
     */
    public function run(Collection $employees, CarbonImmutable $today, Closure $say): void
    {
        $this->say = $say;
        $owner = User::role(Role::Owner->value)->firstOrFail();
        $this->holidays($employees, $today, $owner);
        $this->exemptions($today, $owner);
        $this->timingChanges($today);
        $this->shifts($employees, $today, $owner);
    }

    /** @param Collection<int, Employee> $employees */
    private function holidays(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        // Optional: each person chooses; six have said they'll take it ("Who takes it").
        $sportsDay = Holiday::firstOrCreate(['name' => 'Company sports day (optional)'], [
            'date' => self::workday($today->addDays(18))->format('Y-m-d'), 'type' => HolidayType::Optional, 'is_paid' => true,
        ]);
        foreach ([1, 5, 9, 12, 15, 17] as $i) {
            HolidayOptIn::firstOrCreate(['holiday_id' => $sportsDay->id, 'employee_id' => $employees[$i]->id], ['created_by' => $owner->id]);
        }
        Holiday::firstOrCreate(['name' => 'Foundation day'], [
            'date' => self::workday($today->addMonthNoOverflow()->startOfMonth()->addDays(14))->format('Y-m-d'), 'type' => HolidayType::Company, 'is_paid' => true,
        ]);
    }

    private function exemptions(CarbonImmutable $today, User $owner): void
    {
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();
        AttendanceExemption::firstOrCreate(['date' => self::workday($lastMonth->addDays(6))->format('Y-m-d'), 'type' => ExemptionType::Late],
            ['reason' => 'Heavy rain: roads flooded', 'scope' => ShiftOverrideScope::Tenant, 'created_by' => $owner->id]);
        AttendanceExemption::firstOrCreate(['date' => self::workday($lastMonth->addDays(16))->format('Y-m-d'), 'type' => ExemptionType::Absent],
            ['reason' => 'City-wide strike: office closed in Karachi', 'scope' => ShiftOverrideScope::Branch,
                'scope_id' => Branch::where('code', 'KHI')->value('id'), 'created_by' => $owner->id]);
    }

    /** Fridays get a longer prayer break; winter timings are already planned for later. */
    private function timingChanges(CarbonImmutable $today): void
    {
        ShiftOverride::firstOrCreate(['name' => 'Friday prayer break'], [
            'from_date' => $today->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), 'to_date' => $today->addMonthsNoOverflow(6)->format('Y-m-d'),
            'scope' => ShiftOverrideScope::Tenant, 'weekday' => CarbonImmutable::FRIDAY, 'break_min' => 90, 'priority' => 10,
        ]);
        ShiftOverride::firstOrCreate(['name' => 'Winter timings'], [
            'from_date' => $today->addDays(60)->format('Y-m-d'), 'to_date' => $today->addDays(120)->format('Y-m-d'),
            'scope' => ShiftOverrideScope::Tenant, 'start_time' => '09:30', 'end_time' => '17:30', 'priority' => 20,
        ]);
    }

    /**
     * Morning / Evening / Night shifts: Karachi works Evening this month (monthly roster), two people are on Night
     * for a few days this week, and three swaps (waiting, approved, declined).
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function shifts(Collection $employees, CarbonImmutable $today, User $owner): void
    {
        $make = fn (string $code, string $name, string $from, string $to, bool $night = false) => Shift::firstOrCreate(['code' => $code],
            ['name' => $name, 'start_time' => $from, 'end_time' => $to, 'crosses_midnight' => $night, 'break_min' => $night ? 30 : 45]);
        $make('MOR', 'Morning', '06:00', '14:00');
        $evening = $make('EVE', 'Evening', '14:00', '22:00');
        $night = $make('NGT', 'Night', '22:00', '06:00', true);

        [$y, $m] = $this->cycle->periodFor($today->format('Y-m-d'));
        $khi = Branch::where('code', 'KHI')->first();
        if ($khi && ! ShiftAssignment::where('shift_id', $evening->id)->exists()) {
            $this->attempt('Karachi evening roster', fn () => ($this->roster)($y, $m, $evening, 'branch', $khi->id, $owner));
        }

        $monday = $today->startOfWeek(CarbonImmutable::MONDAY);
        foreach ([$employees[8], $employees[9]] as $e) {
            foreach ([2, 3, 4] as $offset) {
                $day = $monday->addDays($offset)->format('Y-m-d');
                ShiftAssignment::firstOrCreate(['employee_id' => $e->id, 'effective_from' => $day, 'effective_to' => $day], ['shift_id' => $night->id]);
            }
        }

        $swap = fn (int $from, int $to, int $inDays, string $reason, ShiftSwapStatus $status) => ShiftSwapRequest::firstOrCreate(
            ['requester_employee_id' => $employees[$from]->id, 'partner_employee_id' => $employees[$to]->id, 'date' => $today->addDays($inDays)->format('Y-m-d')],
            ['reason' => $reason, 'status' => $status,
                'decided_by' => in_array($status, [ShiftSwapStatus::Approved, ShiftSwapStatus::Rejected], true) ? $owner->id : null,
                'decided_at' => $status === ShiftSwapStatus::Pending ? null : now()]);
        $swap(16, 2, 2, 'Family wedding in the evening', ShiftSwapStatus::Pending);
        $swap(17, 18, 5, 'University exam in the morning', ShiftSwapStatus::Approved);
        $swap(19, 16, 4, 'Doctor appointment', ShiftSwapStatus::Declined);
    }
}
