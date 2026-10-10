<?php

declare(strict_types=1);

use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates a super admin with a generated password and can reset it later', function () {
    SuperAdmin::where('email', 'boss@haziri.test')->delete();

    expect(Artisan::call('hazri:super-admin', ['email' => 'boss@haziri.test']))->toBe(0);
    preg_match('/Password: (\S+)/', Artisan::output(), $m);
    $admin = SuperAdmin::where('email', 'boss@haziri.test')->firstOrFail();
    expect(Hash::check($m[1], $admin->password))->toBeTrue();

    expect(Artisan::call('hazri:super-admin', ['email' => 'boss@haziri.test', '--password' => 'a-new-long-password']))->toBe(0)
        ->and(Artisan::output())->toContain('Password reset')->not->toContain('a-new-long-password')
        ->and(Hash::check('a-new-long-password', $admin->fresh()->password))->toBeTrue()
        ->and(SuperAdmin::where('email', 'boss@haziri.test')->count())->toBe(1);
});

it('refuses a bad email or a short password', function () {
    expect(Artisan::call('hazri:super-admin', ['email' => 'not-an-email']))->toBe(1)
        ->and(Artisan::call('hazri:super-admin', ['email' => 'x@haziri.test', '--password' => 'short']))->toBe(1);
});
