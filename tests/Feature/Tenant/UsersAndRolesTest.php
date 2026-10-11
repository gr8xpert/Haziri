<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Tenant\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
