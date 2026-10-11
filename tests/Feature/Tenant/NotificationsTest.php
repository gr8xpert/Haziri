<?php

declare(strict_types=1);

use App\Actions\Announcements\BroadcastAnnouncement;
use App\Actions\Leave\DecideLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Filament\Tenant\Pages\Integrations;
use App\Filament\Tenant\Resources\Holidays\HolidayResource;
use App\Models\Announcement;
use App\Models\AttendanceDay;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->boss = Employee::create(['code' => 'NB', 'first_name' => 'Nadia', 'joined_on' => '2025-01-01', 'email' => 'nadia@pest.test']);
    $this->emp = Employee::create(['code' => 'NE', 'first_name' => 'Imran', 'joined_on' => '2025-01-01', 'manager_employee_id' => $this->boss->id]);
    $this->manager = User::create(['name' => 'Nadia', 'email' => 'nadia@pest.test', 'password' => 'x', 'employee_id' => $this->boss->id]);
    $this->manager->assignRole(Role::Manager->value);
    $this->staff = User::create(['name' => 'Imran', 'email' => 'imran@pest.test', 'password' => 'x', 'employee_id' => $this->emp->id, 'locale' => 'ur']);
    $this->staff->assignRole(Role::Employee->value);
    $this->hr = User::create(['name' => 'HR', 'email' => 'hr-n@pest.test', 'password' => 'x']);
    $this->hr->assignRole(Role::Hr->value);
});

it('tells the manager about a new leave request by email and in-app', function () {
    app(SubmitLeaveRequest::class)($this->emp, LeaveType::where('code', 'CL')->first(), '2026-09-01', '2026-09-01', $this->staff);

    $logs = NotificationLog::where('event', 'leave_requested')->get();
    expect($logs->where('channel', 'mail')->first()->status)->toBe('sent')
        ->and($logs->where('channel', 'database')->first()->status)->toBe('sent')
        ->and($logs->where('channel', 'whatsapp')->first()->status)->toBe('skipped') // feature off for this tenant
        ->and($this->manager->fresh()->notifications()->count())->toBe(1);
});

it('tells the employee the decision in their own language', function () {
    $r = app(SubmitLeaveRequest::class)($this->emp, LeaveType::where('code', 'CL')->first(), '2026-09-01', '2026-09-01', $this->staff);
    app(DecideLeaveRequest::class)($r, $this->hr, true);

    $note = $this->staff->fresh()->notifications()->latest()->first();
    expect($note->data['title'])->toContain('آپ کی چھٹی')
        ->and(NotificationLog::where(['event' => 'leave_decided', 'recipient' => "user:{$this->staff->id}"])->exists())->toBeTrue();
});

it('uses tenant template overrides and fills placeholders', function () {
    NotificationTemplate::create(['event' => 'leave_decided', 'channel' => 'mail', 'locale' => 'en', 'subject' => 'Leave {{ status }}!', 'body' => 'Dear {{ name }}']);

    $msg = app(TemplateRenderer::class)->render('leave_decided', 'database', 'en', ['status' => 'approved', 'name' => 'Imran']);

    expect($msg->subject)->toBe('Leave approved!')->and($msg->body)->toBe('Dear Imran');
});

it('sends time-based alerts once per day', function () {
    $this->artisan('hazri:notify:scheduled', ['--now' => now('Asia/Karachi')->format('Y-m-d') . ' 11:30'])->assertSuccessful();
    $this->artisan('hazri:notify:scheduled', ['--now' => now('Asia/Karachi')->format('Y-m-d') . ' 11:45'])->assertSuccessful();

    $isWorkingDay = now('Asia/Karachi')->dayOfWeek !== 0;
    expect(NotificationLog::where('event', 'employee_absent_no_leave')->where('channel', 'database')->count())->toBe($isWorkingDay ? 1 : 0);
});

it('warns owners and admins when a device goes offline', function () {
    Device::create(['name' => 'Gate', 'serial_number' => 'OFF01', 'type' => DevicePunchSourceType::Adms, 'status' => 'online', 'last_seen_at' => now()->subHour()]);

    $this->artisan('hazri:devices:check')->assertSuccessful();

    $owner = User::where('email', 'owner@pest.test')->first();
    expect(NotificationLog::where(['event' => 'device_offline', 'recipient' => "user:{$owner->id}", 'channel' => 'database'])->value('status'))->toBe('sent');
    DeviceRegistry::where('serial_number', 'OFF01')->delete();
});

it('broadcasts a scheduled announcement when it publishes, not when it is saved', function () {
    $broadcast = app(BroadcastAnnouncement::class);
    $later = Announcement::create(['title' => 'Closed Friday', 'body' => 'x', 'notify' => true, 'publish_at' => now()->addDay()]);
    $sent = fn () => NotificationLog::where(['event' => 'broadcast', 'dedupe_key' => "announcement:{$later->id}"])->count();

    expect($broadcast($later))->toBe(0)->and($sent())->toBe(0);

    $this->travel(25)->hours();
    $this->artisan('hazri:notify:scheduled')->assertSuccessful();
    expect($sent())->toBeGreaterThan(0)->and($later->fresh()->notified_at)->not->toBeNull();

    $before = $sent();
    $this->artisan('hazri:notify:scheduled')->assertSuccessful();
    expect($sent())->toBe($before);
});

it('broadcasts an announcement without a publish time at once', function () {
    $now = Announcement::create(['title' => 'Salaries on the 3rd', 'body' => 'x', 'notify' => true]);

    expect(app(BroadcastAnnouncement::class)($now))->toBeGreaterThan(0)
        ->and($now->fresh()->notified_at)->not->toBeNull();
});

it('reminds HR to confirm a provisional holiday 3 days ahead, once', function () {
    $h = Holiday::create(['date' => '2026-09-12', 'name' => 'Eid ul Adha', 'type' => 'gazetted', 'needs_confirmation' => true]);
    Holiday::create(['date' => '2026-09-30', 'name' => 'Later', 'type' => 'gazetted', 'needs_confirmation' => true]);
    $sent = fn () => NotificationLog::where(['event' => 'holiday_unconfirmed', 'dedupe_key' => "holiday_confirm:{$h->id}"])->count();

    $this->artisan('hazri:notify:scheduled', ['--now' => '2026-09-09 09:30'])->assertSuccessful();
    $this->artisan('hazri:notify:scheduled', ['--now' => '2026-09-10 09:30'])->assertSuccessful();

    expect($sent())->toBeGreaterThan(0)
        ->and(NotificationLog::where('event', 'holiday_unconfirmed')->where('dedupe_key', 'like', 'holiday_confirm:%')->distinct()->count('dedupe_key'))->toBe(1);
    $this->travelTo(CarbonImmutable::parse('2026-09-09 10:00', 'Asia/Karachi'));
    expect(HolidayResource::getNavigationBadge())->toBe('2'); // both are within 30 days

    $before = $sent();
    $this->artisan('hazri:notify:scheduled', ['--now' => '2026-09-10 10:00'])->assertSuccessful();
    expect($sent())->toBe($before);
});

it('tells the manager once when a team member is late today, only when the switch is on', function () {
    $this->emp->update(['device_pin' => '7101']);
    $at = CarbonImmutable::parse('2026-09-01 09:40', 'Asia/Karachi');
    Punch::create(['employee_id' => $this->emp->id, 'device_pin' => '7101', 'punched_at' => $at->utc(), 'local_date' => '2026-09-01',
        'source' => PunchSource::Manual, 'status' => PunchStatus::Valid, 'dedupe_hash' => sha1('late-alert-' . uniqid())]);
    $recompute = fn () => app(AttendanceRecomputer::class)->recompute($this->emp->id, '2026-09-01', $at->addHour()->utc());
    $alerts = fn () => NotificationLog::where(['event' => 'employee_late', 'recipient' => "user:{$this->manager->id}"])->count();

    expect($recompute()->flags)->toContain('late')->and($alerts())->toBe(0);

    AttendanceDay::query()->delete();
    Setting::updateOrCreate(['key' => 'notify_employee_late'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $recompute();
    $recompute();

    expect($alerts())->toBe(1)
        ->and(NotificationLog::where('event', 'employee_late')->value('payload'))->toMatchArray(['employee' => 'Imran', 'time' => '09:40']);
});

it('sends no late alert when an old day is recomputed, and tells HR when there is no manager', function () {
    Setting::updateOrCreate(['key' => 'notify_employee_late'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->emp->update(['device_pin' => '7102', 'manager_employee_id' => null]);
    foreach (['2026-08-03', '2026-09-01'] as $date) {
        Punch::create(['employee_id' => $this->emp->id, 'device_pin' => '7102', 'punched_at' => CarbonImmutable::parse("{$date} 09:50", 'Asia/Karachi')->utc(),
            'local_date' => $date, 'source' => PunchSource::Manual, 'status' => PunchStatus::Valid, 'dedupe_hash' => sha1("late-old-{$date}")]);
    }
    $now = CarbonImmutable::parse('2026-09-01 12:00', 'Asia/Karachi')->utc();

    app(AttendanceRecomputer::class)->recompute($this->emp->id, '2026-08-03', $now);
    expect(NotificationLog::where('event', 'employee_late')->count())->toBe(0);

    app(AttendanceRecomputer::class)->recompute($this->emp->id, '2026-09-01', $now);
    expect(NotificationLog::where(['event' => 'employee_late', 'recipient' => "user:{$this->hr->id}"])->exists())->toBeTrue()
        ->and(NotificationLog::where(['event' => 'employee_late', 'recipient' => "user:{$this->manager->id}"])->exists())->toBeFalse();
});

it('marks SMS through the log-only provider as logged only, in the log and in the test message', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 12:00', 'Asia/Karachi')); // outside quiet hours
    $this->emp->update(['phone' => '03001234567']);

    app(Notifier::class)->send('missing_punch_out', [Recipient::fromEmployee($this->emp)], ['name' => 'Imran', 'date' => '01 Sep'], null, null, ['sms']);

    expect(NotificationLog::where(['event' => 'missing_punch_out', 'channel' => 'sms'])->value('status'))->toBe('logged');

    $this->actingAs(User::where('email', 'owner@pest.test')->first());
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    Livewire::test(Integrations::class)->callAction('test', ['channel' => 'sms', 'phone' => '03001234567'])
        ->assertNotified('Logged only (no provider set up)');
});
