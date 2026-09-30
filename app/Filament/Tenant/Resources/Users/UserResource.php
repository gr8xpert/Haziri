<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Users;

use App\Enums\Role;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\User;
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
use Illuminate\Support\Facades\Hash;

/** Tenant logins and their roles (spec §8). Only the owner can grant the owner role. */
class UserResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Users & roles';

    protected static string $viewPermission = 'users.manage';

    protected static string $managePermission = 'users.manage';

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
                    : $query->where('name', '!=', Role::Owner->value)),
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
                TextColumn::make('roles.name')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
