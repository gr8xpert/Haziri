<?php

declare(strict_types=1);

use App\Actions\Periods\PeriodActions;
use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PunchSource;
use App\Filament\Tenant\Pages\Reports\LateRegister;
use App\Filament\Tenant\Pages\Reports\MonthlyAttendance;
use App\Filament\Tenant\Pages\Reports\MonthlySheet;
use App\Filament\Tenant\Pages\Reports\ReportsHub;
use App\Filament\Tenant\Widgets\Analytics\HeadlineStats;
use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Reports\AbsentRegisterReport;
use App\Reports\CellFormat;
use App\Reports\DailyMusterReport;
use App\Reports\EmployeeInformationReport;
use App\Reports\EmployeeStatementReport;
use App\Reports\LateRegisterReport;
use App\Reports\MonthlySheetReport;
use App\Reports\MonthSummaryReport;
use App\Reports\ReportExporter;
use Carbon\CarbonImmutable;
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
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $rows = (new MonthlySheetReport)->rows(['month' => '2026-08']);
    $row = collect($rows)->firstWhere('code', 'R1');

    expect($row['d3'])->toBe('L')->and($row['d4'])->toBe('A')->and($row['d8'])->toBe('WO')
        ->and($row['A'])->toBe(4)->and($row['L'])->toBe(3)
        ->and($row['ded'])->toBe(5.0) // 4 absents + floor(3 lates / 3)
        ->and($row['_url'])->toContain('/admin/reports/monthly-attendance')->toContain('month=2026-08')->toContain("employee={$this->e->id}");
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

it('shows flags, sources and categories as words, never codes', function () {
    AttendanceDay::where('employee_id', $this->e->id)->whereDate('date', '2026-08-03')
        ->update(['flags' => json_encode(['late', 'early_out', 'overtime']), 'source_summary' => json_encode(['adms' => 2])]);
    $this->e->forceFill(['employment_type' => EmploymentType::DailyWage])->saveQuietly();

    $muster = collect((new DailyMusterReport)->rows(['date' => '2026-08-03']))->firstWhere('code', 'R1');
    $info = collect((new EmployeeInformationReport)->rows([]))->firstWhere('code', 'R1');

    expect($muster['flags'])->toBe(implode(', ', [AttendanceFlag::Late->getLabel(), AttendanceFlag::EarlyOut->getLabel(), AttendanceFlag::Overtime->getLabel()]))
        ->and($muster['source'])->toBe(PunchSource::Adms->getLabel())
        ->and($info['category'])->toBe(EmploymentType::DailyWage->getLabel())
        ->and($info['joined'])->toBe('01 Jan 2025');
});

it('names export files after the month, not the whole subtitle', function () {
    $pdf = app(ReportExporter::class)->pdf(new MonthSummaryReport, ['month' => '2026-08', 'group_by' => 'employee']);

    expect($pdf->headers->get('content-disposition'))->toContain('monthly-totals-august-2026.pdf');
});

it('shows amounts with thousand separators on screen and in the PDF', function () {
    expect(CellFormat::show(87125))->toBe('87,125')
        ->and(CellFormat::show(1234.5))->toBe('1,234.5')
        ->and(CellFormat::show(14.3))->toBe('14.3')
        ->and(CellFormat::show(28.0))->toBe('28')
        ->and(CellFormat::show('320001'))->toBe('320001') // IDs are strings: untouched
        ->and(CellFormat::show(null))->toBe('');
});

it('exports xlsx and pdf', function () {
    $x = app(ReportExporter::class);

    $xlsx = $x->xlsx(new MonthlySheetReport, ['month' => '2026-08']);
    $pdf = $x->pdf(new EmployeeStatementReport, ['employee_id' => $this->e->id, 'month' => '2026-08']);

    ob_start();
    $pdf->sendContent();
    $bytes = (string) ob_get_clean();

    expect(filesize($xlsx->getFile()->getPathname()))->toBeGreaterThan(1000)
        ->and($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and(strlen($bytes))->toBeGreaterThan(1000)->and(str_starts_with($bytes, '%PDF'))->toBeTrue();
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

it('downloads a report PDF and Excel from the report page buttons', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    // The PDF used to come back as a plain response whose bytes broke Livewire's JSON reply ("Malformed UTF-8").
    Livewire::test(LateRegister::class)->set('filters.from', '2026-08-01')->set('filters.to', '2026-08-31')
        ->callAction('pdf')->assertFileDownloaded()
        ->callAction('xlsx')->assertFileDownloaded();
});

it('asks for the required filters instead of downloading a broken file', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    expect((new MonthlySheetReport)->ready(['month' => '']))->toBeFalse()
        ->and((new MonthlySheetReport)->ready(['month' => '2026-08']))->toBeTrue();

    Livewire::test(MonthlySheet::class)->set('filters.month', null)
        ->assertSee('Choose the filters above.')
        ->callAction('xlsx')->assertNoFileDownloaded()->assertNotified('Choose the filters above before downloading.')
        ->callAction('pdf')->assertNoFileDownloaded();
});

it('lists the reports in five groups', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    $groups = Livewire::test(ReportsHub::class)->instance()->getGroups();
    expect(array_keys($groups))->toBe(['Daily', 'Monthly', 'Leave', 'People & audit', 'Payroll'])
        ->and(collect($groups['Monthly'])->pluck('title')->all())->toContain('Employee monthly report (detailed)', 'Monthly grid – all staff', 'Monthly totals');
});

it('sends old links of merged reports to the replacement', function (string $old, string $new, string $preset) {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $base = 'http://pest.' . config('hazri.central_domain');

    $location = urldecode((string) $this->get("{$base}{$old}")->assertRedirect()->headers->get('Location'));

    expect($location)->toContain($new)->toContain($preset);
})->with([
    'early-out register' => ['/admin/reports/early-out-register', '/admin/reports/late-register', 'filters[kind]=early'],
    'branch summary' => ['/admin/reports/branch-summary', '/admin/reports/month-summary', 'filters[group_by]=branch'],
    'department summary' => ['/admin/reports/department-summary', '/admin/reports/month-summary', 'filters[group_by]=department'],
]);

it('merges late and early-out, and groups monthly totals by department', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $dept = Department::create(['name' => 'Sales', 'code' => 'SAL']);
    $this->e->forceFill(['department_id' => $dept->id])->saveQuietly(); // no recompute: the days are set up by hand

    expect(count((new LateRegisterReport)->rows(['from' => '2026-08-01', 'to' => '2026-08-31', 'kind' => 'late'])))->toBe(3)
        ->and((new LateRegisterReport)->columns(['kind' => 'early']))->toHaveKey('shift_end');

    $byDept = (new MonthSummaryReport)->rows(['month' => '2026-08', 'group_by' => 'department']);
    $sales = collect($byDept)->firstWhere('name', 'Sales');
    expect($sales['employees'])->toBe(1)->and($sales['absent'])->toEqual(4)->and($sales['off'])->toEqual(1);
});

it('marks daily-wage staff in the month reports and explains it', function () {
    $this->e->forceFill(['employment_type' => EmploymentType::DailyWage])->saveQuietly();
    $report = new MonthlySheetReport;
    $rows = $report->rows(['month' => '2026-08']);

    expect(collect($rows)->firstWhere('code', 'R1')['name'])->toBe('Rep · daily wage')
        ->and($report->summary(['month' => '2026-08'], $rows))->toHaveKey('Daily wage');
});

it('counts analytics deduction days with each employee\'s own rules, as Monthly totals does', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00'));
    $this->e->forceFill(['late_deduction_exempt' => true])->saveQuietly(); // 4 absents; the 3 lates are excused

    $stats = (new ReflectionMethod(HeadlineStats::class, 'getStats'))->invoke(new HeadlineStats);

    expect($stats[1]->getLabel())->toBe('Deduction days, August')->and($stats[1]->getValue())->toBe('4')
        ->and(collect((new MonthSummaryReport)->rows(['month' => '2026-08']))->firstWhere('code', 'R1')['deduction'])->toEqual(4);
});
