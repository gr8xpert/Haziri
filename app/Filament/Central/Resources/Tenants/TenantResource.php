<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants;

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'company';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('company_name')->required()->maxLength(255),
            TextInput::make('slug')->required()->regex('/^[a-z][a-z0-9-]{1,30}[a-z0-9]$/')
                ->helperText('Subdomain: {slug}.' . config('hazri.central_domain'))
                ->disabledOn('edit'),
            TextInput::make('admin_email')->email()->required()->visibleOn('create')->dehydrated(false)->label('Owner email'),
            TextInput::make('admin_name')->visibleOn('create')->dehydrated(false)->label('Owner name'),
            Select::make('plan_id')->options(fn () => Plan::pluck('name', 'id'))->label('Plan'),
            TextInput::make('employee_limit')->numeric()->integer()->minValue(1)->helperText('Blank = plan limit'),
            Select::make('timezone')->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()->default(config('hazri.default_timezone'))->required(),
            Select::make('status')->options(TenantStatus::class)->visibleOn('edit')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()
                    ->url(fn (Tenant $r) => 'http://' . $r->slug . '.' . config('hazri.central_domain') . '/admin', shouldOpenInNewTab: true),
                TextColumn::make('plan.name'),
                TextColumn::make('status')->badge()->color(fn (TenantStatus $state) => match ($state) {
                    TenantStatus::Active => 'success', TenantStatus::Trial => 'info', TenantStatus::Suspended => 'danger',
                }),
                TextColumn::make('employee_limit')->placeholder('plan'),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(TenantStatus::class)])
            ->recordActions([
                EditAction::make(),
                Action::make('features')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
                    ->fillForm(fn (Tenant $r) => ['flags' => array_keys(array_filter(SetTenantFeatureFlags::current($r)))])
                    ->schema([
                        CheckboxList::make('flags')->options(array_combine(config('hazri.feature_flags'), config('hazri.feature_flags')))->columns(2),
                    ])
                    ->action(function (Tenant $r, array $data, SetTenantFeatureFlags $set) {
                        $flags = array_fill_keys(config('hazri.feature_flags'), false);
                        foreach ($data['flags'] as $f) {
                            $flags[$f] = true;
                        }
                        $set($r, $flags);
                        Notification::make()->success()->title('Feature flags saved')->send();
                    }),
                Action::make('suspend')->icon(Heroicon::OutlinedExclamationTriangle)->color('danger')->requiresConfirmation()
                    ->visible(fn (Tenant $r) => $r->status !== TenantStatus::Suspended)
                    ->action(fn (Tenant $r) => $r->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()])),
                Action::make('activate')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                    ->visible(fn (Tenant $r) => $r->status !== TenantStatus::Active)
                    ->action(fn (Tenant $r) => $r->update(['status' => TenantStatus::Active, 'suspended_at' => null])),
                Action::make('impersonate')->label('Log in as owner')->icon(Heroicon::OutlinedIdentification)->color('warning')
                    ->requiresConfirmation()->modalDescription('This is recorded in the audit log.')
                    ->action(function (Tenant $r) {
                        $ownerId = $r->run(fn () => User::role(Role::Owner->value)->orderBy('id')->value('id'));
                        if ($ownerId === null) {
                            Notification::make()->danger()->title('This company has no owner user')->send();

                            return;
                        }
                        $token = tenancy()->impersonate($r, (string) $ownerId, '/admin', 'web');
                        activity('central')->causedBy(auth('super_admin')->user())->performedOn($r)
                            ->event('impersonated')->log("Impersonated owner #{$ownerId}");

                        return redirect('http://' . $r->slug . '.' . config('hazri.central_domain')
                            . (request()->getPort() && ! in_array(request()->getPort(), [80, 443], true) ? ':' . request()->getPort() : '')
                            . '/impersonate/' . $token->token);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
