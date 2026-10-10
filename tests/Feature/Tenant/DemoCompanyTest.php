<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Channels\LogChannel;
use App\Services\Notifications\Channels\MailChannel;
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
