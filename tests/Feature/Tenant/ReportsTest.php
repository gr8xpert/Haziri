<?php

declare(strict_types=1);

use App\Actions\Periods\PeriodActions;
use App\Enums\AttendanceStatus;
use App\Filament\Tenant\Pages\Reports\MonthlyAttendance;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\User;
use App\Reports\AbsentRegisterReport;
use App\Reports\DailyMusterReport;
use App\Reports\EmployeeStatementReport;
use App\Reports\LateRegisterReport;
use App\Reports\MonthlySheetReport;
use App\Reports\MonthSummaryReport;
use App\Reports\ReportExporter;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    $this->e = Employee::create(['code' => 'R1', 'first_name' => 'Rep', 'joined_on' => '2025-01-01']);
    $day = fn (string $date, AttendanceStatus $s, array $extra = []) => AttendanceDay::create(array_merge([
        'employee_id' => $this->e->id, 'date' => $date, 'status' => $s, 'flags' => [],
    ], $extra));
    $day('2026-08-03', AttendanceStatus::Present, ['flags' => ['late'], 'late_min' => 20, 'worked_min' => 470,
        'first_in' => '2026-08-03 04:35:00', 'last_out' => '2026-08-03 13:00:00']);
    $day('2026-08-04', AttendanceStatus::Absent);
    $day('2026-08-05', AttendanceStatus::Absent);
    $day('2026-08-06', AttendanceStatus::Present, ['flags' => ['late'], 'late_min' => 5]);
    $day('2026-08-07', AttendanceStatus::Absent);
    $day('2026-08-08', AttendanceStatus::WeeklyOff);
    $day('2026-08-09', AttendanceStatus::Absent);
    $day('2026-08-10', AttendanceStatus::Present, ['flags' => ['late'], 'late_min' => 30]);
});

it('builds the daily muster with local times', function () {
    $rows = (new DailyMusterReport)->rows(['date' => '2026-08-03']);
    $row = collect($rows)->firstWhere('code', 'R1');

    expect($row['in'])->toBe('09:35')->and($row['out'])->toBe('18:00')->and($row['late'])->toBe(20)->and($row['status'])->toBe('Present');
});

it('builds the monthly grid with codes, totals and late rollup', function () {
    $rows = (new MonthlySheetReport)->rows(['month' => '2026-08']);
    $row = collect($rows)->firstWhere('code', 'R1');

    expect($row['d3'])->toBe('L')->and($row['d4'])->toBe('A')->and($row['d8'])->toBe('WO')
        ->and($row['A'])->toBe(4)->and($row['L'])->toBe(3)
        ->and($row['ded'])->toBe(5.0); // 4 absents + floor(3 lates / 3)
});

it('counts absence streaks across weekly offs', function () {
    $rows = collect((new AbsentRegisterReport)->rows(['from' => '2026-08-01', 'to' => '2026-08-31']))->where('code', 'R1')->values();

    expect($rows->pluck('streak')->all())->toBe([1, 2, 1, 2]);
});

it('keeps a running late count', function () {
    $rows = collect((new LateRegisterReport)->rows(['from' => '2026-08-01', 'to' => '2026-08-31']))->where('code', 'R1');

    expect($rows->pluck('count')->all())->toBe([1, 2, 3]);
});

it('produces a statement with a day row for every date', function () {
    $r = new EmployeeStatementReport;
    $filters = ['employee_id' => $this->e->id, 'month' => '2026-08'];

    expect($r->rows($filters))->toHaveCount(31)
        ->and($r->summary($filters, [])['Absent'])->toBe(4);
});

it('exports xlsx and pdf', function () {
    $x = app(ReportExporter::class);

    $xlsx = $x->xlsx(new MonthlySheetReport, ['month' => '2026-08']);
    $pdf = $x->pdf(new EmployeeStatementReport, ['employee_id' => $this->e->id, 'month' => '2026-08']);

    expect(filesize($xlsx->getFile()->getPathname()))->toBeGreaterThan(1000)
        ->and($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and(strlen($pdf->getContent()))->toBeGreaterThan(1000);
});

it('shows a month per employee: live while open, the lock snapshot once locked', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $live = collect((new MonthSummaryReport)->rows(['month' => '2026-08']))->firstWhere('code', 'R1');
    expect($live['absent'])->toBe(4)->and($live['late'])->toBe(3)->and($live['deduction'])->toBe(5.0)
        ->and($live['_url'])->toContain('month=2026-08')->toContain("employee={$this->e->id}");

    $actions = app(PeriodActions::class);
    $period = $actions->lock($actions->period(2026, 8), User::where('email', 'owner@pest.test')->first());
    // The page must read what payroll was paid on, not a fresh calculation.
    $summary = $period->summary;
    $summary['employees'][$this->e->id]['absentDays'] = 9;
    $period->update(['summary' => $summary]);

    $locked = collect((new MonthSummaryReport)->rows(['month' => '2026-08']))->firstWhere('code', 'R1');
    expect($locked['absent'])->toBe(9)
        ->and((new MonthSummaryReport)->subtitle(['month' => '2026-08']))->toContain('Locked on');
});

it('opens the monthly attendance report on the linked employee and month', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::withQueryParams(['month' => '2026-08', 'employee' => $this->e->id])
        ->test(MonthlyAttendance::class)
        ->assertSet('month', '2026-08')->assertSet('employeeId', $this->e->id);
});
