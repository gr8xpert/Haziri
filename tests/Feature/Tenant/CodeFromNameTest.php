<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\CodeFromName;
use Filament\Facades\Filament;
use Livewire\Livewire;

/* Codes are made from the name, so HR never has to invent or remember one. */

it('makes a short unique code from a name', function () {
    Department::create(['name' => 'Production', 'code' => 'PRO']);

    expect(CodeFromName::suggest('Casual Leave', LeaveType::class))->toBe('CL2') // CL is a default leave type
        ->and(CodeFromName::suggest('Hajj Leave', LeaveType::class))->toBe('HL')
        ->and(CodeFromName::suggest('Lahore Head Office', Department::class))->toBe('LHO')
        ->and(CodeFromName::suggest('Production', Department::class))->toBe('PRO2')
        ->and(CodeFromName::suggest('Quality', Department::class))->toBe('QUA');
});

it('fills the code in when the name is typed on a form', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());

    Livewire::test(ManageLeaveTypes::class)
        ->mountAction('create')
        ->fillForm(['name' => 'Maternity Leave'])
        ->assertActionDataSet(['code' => 'ML'])
        ->fillForm(['name' => 'Paternity Leave', 'code' => 'PAT'])
        ->assertActionDataSet(['code' => 'PAT']);
});
