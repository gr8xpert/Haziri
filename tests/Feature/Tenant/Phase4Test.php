<?php

declare(strict_types=1);

use App\Actions\Punches\CreateManualPunches;
use App\Actions\Rosters\ApproveShiftSwap;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceDay;
use App\Models\Central\TenantUserLink;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Analytics\PunctualityScore;
use App\Services\Settings\TenantSettings;
use App\Support\Security\PublicUrl;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@pest.test')->first();
    $this->base = 'http://pest.' . config('hazri.central_domain');
    $this->evening = Shift::create(['name' => 'Evening', 'code' => 'EVE', 'start_time' => '14:00', 'end_time' => '22:00', 'break_min' => 30]);
});

it('detects the nearest shift from the first punch when enabled', function () {
    Setting::updateOrCreate(['key' => 'auto_shift_detection'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $e = Employee::create(['code' => 'AUTO', 'first_name' => 'Auto', 'joined_on' => '2025-01-01']);

    app(CreateManualPunches::class)($e, '2026-09-08', '14:05', '22:00', 'test', $this->owner);

    $day = AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-09-08')->first();
    expect($day->shift_id)->toBe($this->evening->id)->and($day->status)->toBe(AttendanceStatus::Present)->and($day->flags)->not->toContain('late');
});

it('swaps two employees\' shifts for one day', function () {
    $a = Employee::create(['code' => 'SWA', 'first_name' => 'A', 'joined_on' => '2025-01-01']);
    $b = Employee::create(['code' => 'SWB', 'first_name' => 'B', 'joined_on' => '2025-01-01']);
    ShiftAssignment::create(['employee_id' => $b->id, 'shift_id' => $this->evening->id, 'effective_from' => '2026-01-01']);
    $swap = ShiftSwapRequest::create(['requester_employee_id' => $a->id, 'partner_employee_id' => $b->id, 'date' => '2026-10-20']);

    app(ApproveShiftSwap::class)($swap, $this->owner, true);

    $resolver = app(App\Services\Attendance\ShiftResolver::class);
    expect($resolver->resolve($a, '2026-10-20', 'Asia/Karachi')->shiftId)->toBe($this->evening->id)
        ->and($resolver->resolve($b, '2026-10-20', 'Asia/Karachi')->shiftId)->toBe(Shift::where('is_default', true)->value('id'))
        ->and($resolver->resolve($a, '2026-10-21', 'Asia/Karachi')->shiftId)->not->toBe($this->evening->id);
});

it('scores punctuality', function () {
    $days = collect([
        new AttendanceDay(['status' => AttendanceStatus::Present, 'flags' => []]),
        new AttendanceDay(['status' => AttendanceStatus::Present, 'flags' => ['late']]),
        new AttendanceDay(['status' => AttendanceStatus::Absent, 'flags' => []]),
        new AttendanceDay(['status' => AttendanceStatus::WeeklyOff, 'flags' => []]),
    ]);

    expect(PunctualityScore::of($days))->toMatchArray(['score' => 50, 'working' => 3, 'late' => 1, 'absent' => 1]);
});

it('switches to another linked company only for linked emails', function () {
    $this->actingAs($this->owner)->get("{$this->base}/switch/not-a-linked-tenant")->assertForbidden();

    expect(TenantUserLink::where('email', 'owner@pest.test')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

it('rejects SSRF targets for tenant-configured URLs', function (string $url, bool $ok) {
    expect(PublicUrl::check($url) === null)->toBe($ok);
})->with([
    ['http://169.254.169.254/latest/meta-data', false],
    ['http://127.0.0.1:8080/', false],
    ['http://10.0.0.5/send', false],
    ['http://100.64.1.1/', false],
    ['ftp://example.com/', false],
    ['https://user:pass@8.8.8.8/', false],
    ['https://8.8.8.8/sms', true],
]);

it('never reads files outside the visitor photo folder for badges', function () {
    $v = Visitor::create(['name' => 'X', 'checked_in_at' => now(), 'badge_no' => 'V001', 'photo_path' => '../../.env']);

    $html = $this->actingAs($this->owner)->get("{$this->base}/visitors/{$v->id}/badge")->assertOk()->getContent();

    expect($html)->not->toContain('data:image');
});

it('renders the roster, analytics and scorecard pages', function () {
    $this->actingAs($this->owner);
    foreach (['/admin/roster', '/admin/analytics', '/admin/reports/punctuality'] as $path) {
        $this->get($this->base . $path)->assertOk();
    }
});
