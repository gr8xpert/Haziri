<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Users;

use App\Enums\Role;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/** Tenant logins and their roles (spec §8). Only the owner can grant the owner role or change an owner's login. */
class UserResource extends Resource
{
    use AuthorizesByPermission {
        getAuthorizationResponse as permissionResponse;
    }

    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Users & roles';

    protected static string $viewPermission = 'users.manage';

    protected static string $managePermission = 'users.manage';

    /** An admin may not edit an owner's login: that would let them take it over or drop its Owner role. */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if ($record instanceof User && ! in_array($action, ['viewAny', 'view'], true)
            && $record->hasRole(Role::Owner->value) && ! auth()->user()?->hasRole(Role::Owner->value)) {
            return Response::deny(__('Only an owner can change an owner login.'));
        }

        return static::permissionResponse($action, $record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->tel()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state)),
            Select::make('roles')->multiple()->preload()
                ->relationship('roles', 'name', modifyQueryUsing: fn ($query) => auth()->user()->hasRole(Role::Owner->value)
                    ? $query
                    : $query->where('name', '!=', Role::Owner->value))
                ->getOptionLabelFromRecordUsing(fn ($record) => Role::tryFrom($record->name)?->getLabel() ?? $record->name),
            Select::make('branches')->multiple()->preload()->relationship('branches', 'name')
                ->label('Limit to branches')->placeholder('All branches')
                ->helperText('A shop/branch manager sees only these branches. Leave empty for all branches. Owners and admins always see everything.')
                ->visible(fn () => app(TenantSettings::class)->featureEnabled('multi_branch')),
            Select::make('employee_id')->label('Linked employee')->searchable()
                ->options(fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
            Select::make('locale')->options(['en' => 'English', 'ur' => 'اردو'])->default('en'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge()->formatStateUsing(fn (string $state) => Role::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('branches.name')->badge()->color('gray')->placeholder('All branches')
                    ->visible(fn () => app(TenantSettings::class)->featureEnabled('multi_branch')),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
