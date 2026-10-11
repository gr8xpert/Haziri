<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Tenant\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Tenant\Resources\Users\Pages\ManageUsers;
use App\Filament\Tenant\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin-roles@pest.test', 'password' => 'secret123']);
    $this->admin->assignRole(Role::Admin->value);
});

it('does not let an admin edit the owner login, so the owner role cannot be dropped', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($this->owner))
        ->assertActionVisible(TestAction::make('edit')->table($this->admin));

    expect($this->owner->fresh()->hasRole(Role::Owner->value))->toBeTrue();
});

it('keeps the owner role when the owner edits their own login', function () {
    $this->actingAs($this->owner);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($this->owner), ['name' => 'Owner Renamed'])
        ->assertHasNoActionErrors();

    expect($this->owner->fresh()->name)->toBe('Owner Renamed')
        ->and($this->owner->fresh()->hasRole(Role::Owner->value))->toBeTrue();
});

it('writes role changes to the audit log', function () {
    $this->actingAs($this->owner);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('edit')->table($this->admin), ['roles' => [SpatieRole::findByName(Role::Hr->value)->id]])
        ->assertHasNoActionErrors();

    $entry = Activity::query()->where('event', 'roles_changed')->where('subject_id', $this->admin->id)->sole();
    expect($this->admin->fresh()->getRoleNames()->all())->toBe([Role::Hr->value])
        ->and($entry->attribute_changes['old']['roles'])->toBe('Admin')
        ->and($entry->attribute_changes['attributes']['roles'])->toBe('HR')
        ->and($entry->causer_id)->toBe($this->owner->id);
});

it('offers every event in the audit log in the Event filter', function () {
    $this->actingAs($this->owner);
    activity()->performedOn($this->admin)->event('face_enrolled')->log('Face enrolled');

    Livewire::test(ListAuditLogs::class)
        ->assertTableFilterExists('event', fn (SelectFilter $filter) => array_key_exists('face_enrolled', $filter->getOptions())
            && $filter->getOptions()['face_enrolled'] === 'Face enrolled')
        ->filterTable('event', 'face_enrolled')
        ->assertCanSeeTableRecords(Activity::where('event', 'face_enrolled')->get());
});

it('does not make anyone owner when an admin submits the Owner role id', function () {
    $hr = User::create(['name' => 'HR', 'email' => 'hr-roles@pest.test', 'password' => 'secret123']);
    $hr->assignRole(Role::Hr->value);
    $this->actingAs($this->admin);

    UserResource::syncRoles($hr, [SpatieRole::findByName(Role::Owner->value)->id, SpatieRole::findByName(Role::Hr->value)->id]);

    expect($hr->fresh()->getRoleNames()->all())->toBe([Role::Hr->value]);
});
