<?php

declare(strict_types=1);

use App\Actions\Announcements\BroadcastAnnouncement;
use App\Actions\Leave\DecideLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Enums\DevicePunchSourceType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\TemplateRenderer;
use Illuminate\Support\Facades\Mail;

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
