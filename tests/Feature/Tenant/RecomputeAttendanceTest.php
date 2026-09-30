<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\PeriodStatus;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\RequestStatus;
use App\Jobs\RecomputeAttendanceDay;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftOverride;
use App\Services\Attendance\AttendanceRecomputer;
use Carbon\CarbonImmutable;

const TZ = 'Asia/Karachi';

function employee(array $attrs = []): Employee
{
    static $n = 0;
    $n++;

    return Employee::create(array_merge([
        'code' => "E{$n}",
        'first_name' => "Emp{$n}",
        'joined_on' => '2025-01-01',
        'device_pin' => (string) (100 + $n),
    ], $attrs));
}

function punch(Employee $e, string $local, array $attrs = []): Punch
{
    $at = CarbonImmutable::parse($local, TZ);

    return Punch::create(array_merge([
        'employee_id' => $e->id,
        'device_pin' => $e->device_pin,
        'punched_at' => $at->utc()->format('Y-m-d H:i:s'),
        'local_date' => $at->format('Y-m-d'),
        'source' => PunchSource::Manual,
        'status' => PunchStatus::Valid,
        'dedupe_hash' => sha1("manual|{$e->device_pin}|{$local}|" . uniqid()),
    ], $attrs));
}

function recompute(Employee $e, string $date): ?AttendanceDay
{
    return app(AttendanceRecomputer::class)->recompute($e->id, $date, CarbonImmutable::parse('2026-09-20 12:00', TZ)->utc());
}

it('writes a present day from punches on the tenant default shift', function () {
    $e = employee();
    punch($e, '2026-09-01 09:05');
    punch($e, '2026-09-01 18:02');

    $day = recompute($e, '2026-09-01');

    expect($day->status)->toBe(AttendanceStatus::Present)
        ->and($day->worked_min)->toBe(477)
        ->and($day->shift_id)->toBe(Shift::where('is_default', true)->value('id'))
        ->and($day->source_summary)->toBe(['manual' => 2])
        ->and($day->computed_version)->toBe(AttendanceRecomputer::ENGINE_VERSION)
        ->and(AttendanceDay::count())->toBe(1);
});

it('updates the same row on recompute instead of duplicating it', function () {
    $e = employee();
    recompute($e, '2026-09-01');
    punch($e, '2026-09-01 09:00');
    punch($e, '2026-09-01 18:00');
    $day = recompute($e, '2026-09-01');

    expect(AttendanceDay::count())->toBe(1)->and($day->status)->toBe(AttendanceStatus::Present);
});

it('ignores non-valid punches', function () {
    $e = employee();
    punch($e, '2026-09-01 09:00', ['status' => PunchStatus::Duplicate]);

    expect(recompute($e, '2026-09-01')->status)->toBe(AttendanceStatus::Absent);
});

it('refuses to recompute a locked period', function () {
    $e = employee();
    AttendancePeriod::create(['year' => 2026, 'month' => 9, 'status' => PeriodStatus::Locked]);
    punch($e, '2026-09-01 09:00');

    expect(recompute($e, '2026-09-01'))->toBeNull()
        ->and(AttendanceDay::count())->toBe(0)
        ->and(recompute($e, '2026-10-01'))->not->toBeNull();
});

it('leaves a locked row untouched', function () {
    $e = employee();
    $day = recompute($e, '2026-09-01');
    $day->update(['is_locked' => true]);
    punch($e, '2026-09-01 09:00');
    punch($e, '2026-09-01 18:00');

    expect(recompute($e, '2026-09-01'))->toBeNull()
        ->and($day->fresh()->status)->toBe(AttendanceStatus::Absent);
});

it('applies global and branch holidays from the DB', function () {
    $lahore = Branch::create(['name' => 'Lahore', 'code' => 'LHE']);
    $karachi = Branch::create(['name' => 'Karachi', 'code' => 'KHI']);
    $inLahore = employee(['branch_id' => $lahore->id]);
    $inKarachi = employee(['branch_id' => $karachi->id]);
    Holiday::create(['date' => '2026-09-02', 'name' => 'Company day']);
    Holiday::create(['date' => '2026-09-03', 'name' => 'Local holiday', 'branch_id' => $lahore->id]);

    expect(recompute($inKarachi, '2026-09-02')->status)->toBe(AttendanceStatus::Holiday)
        ->and(recompute($inLahore, '2026-09-03')->status)->toBe(AttendanceStatus::Holiday)
        ->and(recompute($inKarachi, '2026-09-03')->status)->toBe(AttendanceStatus::Absent);
});

it('uses branch weekly offs when the shift has none', function () {
    Shift::where('is_default', true)->update(['weekly_off_days' => null]);
    $branch = Branch::create(['name' => 'Six-day', 'code' => 'SIX', 'weekly_off_days' => [5]]);
    $e = employee(['branch_id' => $branch->id]);

    expect(recompute($e, '2026-09-04')->status)->toBe(AttendanceStatus::WeeklyOff) // Friday
        ->and(recompute($e, '2026-09-06')->status)->toBe(AttendanceStatus::Absent); // Sunday is a working day here
});

it('reads approved leave and sandwich dates, ignoring pending leave', function () {
    $e = employee();
    $al = LeaveType::where('code', 'AL')->first();
    LeaveRequest::create([
        'employee_id' => $e->id, 'leave_type_id' => $al->id, 'from_date' => '2026-09-04', 'to_date' => '2026-09-07',
        'days' => 2, 'status' => RequestStatus::Approved, 'sandwich_dates' => ['2026-09-06'],
    ]);
    LeaveRequest::create([
        'employee_id' => $e->id, 'leave_type_id' => $al->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10',
        'days' => 1, 'status' => RequestStatus::Pending,
    ]);

    expect(recompute($e, '2026-09-04')->status)->toBe(AttendanceStatus::LeavePaid)
        ->and(recompute($e, '2026-09-06')->status)->toBe(AttendanceStatus::LeavePaid)
        ->and(recompute($e, '2026-09-06')->flags)->toContain('sandwich_applied')
        ->and(recompute($e, '2026-09-10')->status)->toBe(AttendanceStatus::Absent);
});

it('uses the latest effective shift assignment and scoped overrides', function () {
    $night = Shift::create([
        'name' => 'Night', 'code' => 'NGT', 'start_time' => '22:00', 'end_time' => '06:00',
        'crosses_midnight' => true, 'break_min' => 0,
    ]);
    $e = employee();
    ShiftAssignment::create(['employee_id' => $e->id, 'shift_id' => $night->id, 'effective_from' => '2026-09-01']);
    ShiftOverride::create([
        'name' => 'Night grace', 'from_date' => '2026-09-01', 'to_date' => '2026-09-30',
        'scope' => 'shift', 'scope_id' => $night->id, 'grace_in_min' => 45,
    ]);
    punch($e, '2026-09-01 22:40');
    punch($e, '2026-09-02 06:00');

    $day = recompute($e, '2026-09-01');

    expect($day->shift_id)->toBe($night->id)
        ->and($day->override_id)->not->toBeNull()
        ->and($day->flags)->not->toContain('late') // 40 min is inside the 45 min override grace
        ->and($day->worked_min)->toBe(440);
});

it('keeps a manual status override across recomputes', function () {
    $e = employee();
    $day = recompute($e, '2026-09-01');
    $day->update(['manual_override' => ['status' => 'official_duty', 'reason' => 'client visit']]);

    $again = recompute($e, '2026-09-01');

    expect($again->status)->toBe(AttendanceStatus::OfficialDuty)->and($again->flags)->toContain('manual_override');
});

it('runs through the queued job', function () {
    $e = employee();
    punch($e, '2026-09-01 09:00');
    punch($e, '2026-09-01 18:00');

    RecomputeAttendanceDay::dispatchSync($e->id, '2026-09-01');

    expect(AttendanceDay::where('employee_id', $e->id)->value('status'))->toBe(AttendanceStatus::Present);
});

it('re-attaches punches when an unmapped PIN is mapped')->todo('Ingestion pipeline (§17 step 6)');
