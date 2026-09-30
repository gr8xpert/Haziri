<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('provisions a tenant DB with defaults and an owner', function () {
    expect($this->tenant->database()->getName())->toBe('hazri_t_pest')
        ->and($this->tenant->domains()->pluck('domain')->all())->toBe(['pest'])
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'employee', 'hr', 'manager', 'owner', 'receptionist'])
        ->and(Shift::where('is_default', true)->count())->toBe(1)
        ->and(LeaveType::pluck('code')->sort()->values()->all())->toBe(['AL', 'CL', 'SL'])
        ->and(Holiday::count())->toBeGreaterThanOrEqual(7)
        ->and(User::where('email', 'owner@pest.test')->first()->hasRole('owner'))->toBeTrue();
});

it('gives the owner every permission and the receptionist only front-desk ones', function () {
    $owner = Role::findByName('owner');
    $receptionist = Role::findByName('receptionist');

    expect($owner->permissions->count())->toBe(count(config('permissions.permissions')))
        ->and($receptionist->hasPermissionTo('attendance.manual_entry'))->toBeTrue()
        ->and($receptionist->hasPermissionTo('periods.lock'))->toBeFalse();
});

it('rejects reserved or duplicate slugs', function (string $slug) {
    app(App\Actions\Tenants\ProvisionTenant::class)($slug, 'X', 'x@x.test');
})->with(['admin', 'pest', 'Bad_Slug'])->throws(Illuminate\Validation\ValidationException::class);

it('keeps tenant rows out of the central DB', function () {
    expect(Tenant::on('central')->where('slug', 'pest')->exists())->toBeTrue()
        ->and(Illuminate\Support\Facades\Schema::connection('central')->hasTable('employees'))->toBeFalse();
});
