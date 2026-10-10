<?php

declare(strict_types=1);

use App\Enums\DevicePunchSourceType;
use App\Enums\Role;
use App\Models\AttendanceDay;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Services\Demo\DemoDay;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Channels\LogChannel;
use App\Services\Notifications\Channels\MailChannel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

// The public demo company: config hazri.demo.tenant names it (here the `pest` test company).

it('only logs messages from the demo company, never sends them', function () {
    $registry = app(ChannelRegistry::class);
    expect($registry->resolve('mail'))->toBeInstanceOf(MailChannel::class);

    config(['hazri.demo.tenant' => 'pest']);
    foreach (['mail', 'sms', 'whatsapp'] as $channel) {
        expect($registry->resolve($channel))->toBeInstanceOf(LogChannel::class)
            ->and($registry->resolve($channel)->name())->toBe($channel);
    }
});

it('keeps the shared demo logins from being changed or deleted', function () {
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $other = User::create(['name' => 'Visitor-made', 'email' => 'someone@example.com', 'password' => 'x']);
    $other->assignRole(Role::Hr->value);

    config(['hazri.demo.tenant' => 'pest']);
    expect(fn () => $owner->update(['password' => 'mine-now']))->toThrow(ValidationException::class)
        ->and(fn () => $owner->update(['is_active' => false]))->toThrow(ValidationException::class)
        ->and(fn () => $owner->delete())->toThrow(ValidationException::class);

    $owner->fresh()->update(['name' => 'Renamed owner']); // harmless changes are fine
    $other->update(['password' => 'changed']);    // so are users a visitor added
    expect($owner->fresh()->name)->toBe('Renamed owner');

    config(['hazri.demo.tenant' => null]);
    $owner->fresh()->update(['password' => 'real-company']);
    expect(Hash::check('real-company', $owner->fresh()->password))->toBeTrue();
});

it('fills only the demo company with demo data on a live server', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['hazri.demo.tenant' => 'kunsystems']);

    expect(Artisan::call('hazri:demo:seed', ['slug' => 'pest']))->toBe(1)
        ->and(Artisan::output())->toContain('only the demo company');
});

it('refuses to reset when no demo company is configured', function () {
    config(['hazri.demo.tenant' => null]);

    expect(Artisan::call('hazri:demo:reset'))->toBe(1);
});

it('plays the demo working day only up to the current time, once', function () {
    foreach (['ADMS-01' => DevicePunchSourceType::Adms, 'KIOSK' => DevicePunchSourceType::Kiosk] as $serial => $type) {
        Device::create(['name' => $serial, 'serial_number' => "PEST-{$serial}", 'type' => $type]);
    }
    foreach (range(1, 9) as $n) {
        Employee::create(['code' => sprintf('E%03d', $n), 'first_name' => "Staff{$n}", 'device_pin' => (string) (100 + $n), 'joined_on' => '2025-01-01']);
    }
    $day = app(DemoDay::class);
    $tz = 'Asia/Karachi';
    $morning = CarbonImmutable::parse('2026-09-15 11:00', $tz); // a Tuesday

    expect($day->catchUp($morning))->toBeGreaterThan(5);
    $today = Punch::query()->whereDate('local_date', '2026-09-15');
    expect((clone $today)->count())->toBeGreaterThan(5)
        ->and((clone $today)->where('punched_at', '>', $morning->utc()->format('Y-m-d H:i:s'))->count())->toBe(0)
        ->and((clone $today)->whereHas('employee', fn ($q) => $q->where('code', 'E008'))->where('source', '!=', 'mobile')->count())->toBe(0)
        ->and($day->catchUp($morning))->toBe(0); // nothing new: idempotent

    $evening = CarbonImmutable::parse('2026-09-15 21:00', $tz);
    $before = (clone $today)->count();
    $day->catchUp($evening);
    expect((clone $today)->count())->toBeGreaterThan($before) // punch-outs
        ->and(AttendanceDay::whereDate('date', '2026-09-15')->count())->toBeGreaterThan(5);
});

it('shows the demo logins on the panel login page', function () {
    config(['hazri.demo.tenant' => 'pest']);

    $this->get('http://pest.' . config('hazri.central_domain') . '/admin/login')
        ->assertOk()->assertSee('Demo company: try any login')->assertSee('owner@pest.test');
});

it('opens the staff app login on email with the demo login in the demo', function () {
    config(['hazri.demo.tenant' => 'pest']);

    $this->get('http://pest.' . config('hazri.central_domain') . '/me/login')
        ->assertOk()->assertSee('employee@pest.test')->assertSee('Fill it in for me')->assertSee("mode: 'email'", false);
});
