<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant login account (tenant DB). HR / receptionist users may have no employee record.
 */
#[Fillable(['name', 'email', 'phone', 'password', 'employee_id', 'is_active', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /** Mirror the DB defaults so freshly created models behave the same as loaded ones. */
    protected $attributes = ['is_active' => true, 'locale' => 'en'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Plain employees use the /me PWA, not the admin panel.
        return $panel->getId() === 'tenant'
            && $this->is_active
            && $this->hasAnyRole(array_map(fn (Role $r) => $r->value, [Role::Owner, Role::Admin, Role::Hr, Role::Manager, Role::BranchManager, Role::Receptionist]));
    }

    /** Branches this user is limited to (feature multi_branch); none = all branches. */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'phone', 'employee_id', 'is_active'])->logOnlyDirty();
    }
}
