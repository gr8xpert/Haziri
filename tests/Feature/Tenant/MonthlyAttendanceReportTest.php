<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Filament\Tenant\Pages\Reports\MonthlyAttendance;
use App\Jobs\BuildMonthlyAttendanceExport;
use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Reports\MonthlyAttendanceExport;
use App\Services\Reports\MonthlyAttendancePdf;
use App\Services\Reports\MonthlyAttendanceStatement;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/* The client's "Monthly Attendance Report": one employee's pay month, day by day, with totals and leave. */

beforeEach(function () {
    $this->dept = Department::create(['name' => 'Administration', 'code' => 'ADM']);
    $this->emp = Employee::create(['code' => '7019', 'first_name' => 'Zahid', 'last_name' => 'Ali', 'joined_on' => '2016-02-09',
        'department_id' => $this->dept->id, 'designation_id' => Designation::create(['name' => 'Driver'])->id,
        'grade_id' => Grade::create(['code' => 'WRK', 'name' => 'Worker'])->id]);
    $shift = Shift::where('is_default', true)->firstOrFail();
    $holiday = Holiday::withoutEvents(fn () => Holiday::create(['date' => '2026-09-03', 'name' => '9th Muharram (test)'])); // no recompute: days are made by hand
    $at = fn (string $local) => CarbonImmutable::parse($local, 'Asia/Karachi')->utc();
    $day = fn (string $date, AttendanceStatus $status, array $extra = []) => AttendanceDay::create(array_merge([
        'employee_id' => $this->emp->id, 'date' => $date, 'status' => $status, 'flags' => [], 'shift_id' => $shift->id, 'expected_min' => 480,
    ], $extra));

    $day('2026-09-01', AttendanceStatus::Present, ['first_in' => $at('2026-09-01 08:41'), 'last_out' => $at('2026-09-01 17:36'), 'worked_min' => 475]);
    $day('2026-09-02', AttendanceStatus::Present, ['flags' => ['late', 'manual_punch'], 'first_in' => $at('2026-09-02 07:30'),
        'last_out' => $at('2026-09-02 19:00'), 'worked_min' => 630, 'overtime_min' => 150]);
    $day('2026-09-03', AttendanceStatus::Holiday, ['holiday_id' => $holiday->id]);
    $day('2026-09-06', AttendanceStatus::WeeklyOff);
    $day('2026-09-07', AttendanceStatus::Absent);
    Punch::create(['employee_id' => $this->emp->id, 'punched_at' => $at('2026-09-02 07:30'), 'local_date' => '2026-09-02', 'source' => PunchSource::Manual,
        'direction' => PunchDirection::In, 'status' => PunchStatus::Valid, 'is_manual' => true, 'reason' => 'Haji sb home duty', 'dedupe_hash' => md5('m1')]);
});

it('lays out the month day by day with statuses in words, manual times and remarks', function () {
    $s = app(MonthlyAttendanceStatement::class)->build($this->emp, 2026, 9);
    $rows = collect($s['rows'])->keyBy('date');

    expect($s['employee'])->toMatchArray(['code' => '7019', 'name' => 'Zahid Ali', 'designation' => 'Driver', 'department' => 'Administration',
        'grade' => 'Worker', 'joined' => '09-Feb-2016'])
        ->and($rows)->toHaveCount(30)
        ->and($rows['01-09-26'])->toMatchArray(['weekday' => 'Tuesday', 'day_type' => 'Full Day', 'sched_in' => '09:00', 'sched_out' => '18:00',
            'in' => '08:41', 'out' => '17:36', 'status' => 'Present (On Time)', 'duration' => '08:55', 'in_manual' => false])
        ->and($rows['02-09-26'])->toMatchArray(['status' => 'Present (Late)', 'in' => '07:30', 'in_manual' => true, 'out_manual' => false,
            'overtime' => '02:30', 'remarks' => 'Haji sb home duty'])
        ->and($rows['03-09-26'])->toMatchArray(['status' => 'Holiday', 'remarks' => '9th Muharram (test)'])
        ->and($rows['06-09-26'])->toMatchArray(['day_type' => 'Off Day', 'status' => 'Off Day', 'sched_in' => null])
        ->and($rows['07-09-26']['status'])->toBe('Absent')
        ->and($s['totals'])->toMatchArray(['on_time' => 1, 'late' => 1, 'absent' => 1, 'off' => 1, 'holiday' => 1, 'overtime_hm' => '02:30',
            'span_hm' => '20:25'])
        ->and(collect($s['leave'])->pluck('type'))->toContain('Casual Leave');
});

it('prints one page per employee with page numbers', function () {
    $other = Employee::create(['code' => '7020', 'first_name' => 'Imran', 'joined_on' => '2020-01-01', 'department_id' => $this->dept->id]);
    $statements = app(MonthlyAttendanceStatement::class)->buildMany(collect([$this->emp, $other]), 2026, 9);

    $pdf = app(MonthlyAttendancePdf::class)->render($statements, 'HR');

    preg_match('#/Type /Pages[^>]*/Count (\d+)#s', $pdf, $m);
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and((int) ($m[1] ?? 0))->toBe(2)
        ->and(strlen($pdf))->toBeLessThan(400_000); // fonts are subset
});

it('previews an employee on screen and downloads single and bulk PDFs', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());

    $page = Livewire::test(MonthlyAttendance::class)->set('month', '2026-09')->set('departmentId', $this->dept->id)->set('employeeId', $this->emp->id)
        ->assertSee(['Zahid Ali', 'Present (Late)', 'Haji sb home duty', '9th Muharram (test)']);

    $page->call('downloadPdf')->assertFileDownloaded('attendance-7019-2026-09.pdf');
    $page->call('downloadAllPdf')->assertFileDownloaded('attendance-2026-09.pdf');
});

it('downloads separate PDFs in a ZIP and sends big batches to the background', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $this->actingAs($owner);

    $page = Livewire::test(MonthlyAttendance::class)->set('month', '2026-09')->set('departmentId', $this->dept->id);
    $page->call('downloadZip')->assertFileDownloaded('attendance-2026-09.zip');

    $zipPath = app(MonthlyAttendanceExport::class)->zip(collect([$this->emp]), 2026, 9, 'HR');
    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->getNameIndex(0))->toBe('7019 - Zahid Ali - ' . app(PayCycle::class)->label(2026, 9) . '.pdf');
    $zip->close();

    Queue::fake();
    foreach (range(1, MonthlyAttendanceExport::SYNC_LIMIT) as $i) {
        Employee::create(['code' => "BULK{$i}", 'first_name' => "Bulk{$i}", 'joined_on' => '2025-01-01', 'department_id' => $this->dept->id]);
    }
    $page->call('downloadAllPdf')->assertNoFileDownloaded();
    Queue::assertPushed(BuildMonthlyAttendanceExport::class, fn ($job) => count($job->employeeIds) > MonthlyAttendanceExport::SYNC_LIMIT);
});

it('builds a background export and notifies the user with a working download link', function () {
    Storage::fake('local');
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();

    (new BuildMonthlyAttendanceExport([$this->emp->id], 2026, 9, false, $owner->id, 'attendance-2026-09.pdf'))
        ->handle(app(MonthlyAttendanceExport::class));

    $notification = $owner->notifications()->latest()->first();
    $url = $notification->data['actions'][0]['url'];
    expect(Storage::disk('local')->files("exports/{$owner->id}"))->toHaveCount(1);
    $this->actingAs($owner)->get($url)->assertOk()->assertDownload('attendance-2026-09.pdf');

    // Another user with export rights cannot use the same link: the file is in the owner's folder only.
    $hr = User::create(['name' => 'HR2', 'email' => 'hr-export@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);
    $this->actingAs($hr)->get($url)->assertNotFound();
});
