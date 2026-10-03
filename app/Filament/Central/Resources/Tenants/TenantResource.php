<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants;

use App\Actions\Tenants\SaveBranding;
use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Filament\Central\FeatureToggles;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Features\FeatureCatalog;
use App\Services\Settings\TenantSettings;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            Select::make('plan_id')->options(fn () => Plan::pluck('name', 'id'))->label('Plan')
                ->live()->afterStateUpdated(function (?string $state, string $operation, Set $set) {
                    // A new plan brings its feature switches; they are saved with the form.
                    $plan = $operation === 'edit' && $state ? Plan::find($state) : null;
                    if ($plan !== null) {
                        $set('flags', FeatureCatalog::fromConfig()->normalise($plan->feature_flags ?? []));
                        Notification::make()->info()->title("Features set to the {$plan->name} plan's. Save to apply.")->send();
                    }
                }),
            TextInput::make('employee_limit')->numeric()->integer()->minValue(1)
                ->helperText(function (Get $get) {
                    $limit = Plan::find($get('plan_id'))?->limits['employees'] ?? null;

                    return 'Blank = the plan\'s limit (' . ($limit ?? 'unlimited') . ')';
                }),
            Select::make('timezone')->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()->default(config('hazri.default_timezone'))->required(),
            Select::make('status')->options(TenantStatus::class)->visibleOn('edit')->required()->live(),
            DateTimePicker::make('trial_ends_at')->label('Trial ends')->seconds(false)
                ->visible(fn (Get $get, string $operation) => $operation === 'edit' && self::isTrial($get('status')))
                ->required(fn (Get $get) => self::isTrial($get('status')))
                ->helperText('Move it later to extend the trial. When it passes, the company is locked until you activate it.'),
            Section::make('Features')->visibleOn('edit')->columnSpanFull()
                ->description('Connected features follow automatically; data is kept when a feature is off.')
                ->schema(FeatureToggles::schema('flags')),
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
                TextColumn::make('trial_ends_at')->label('Trial ends')->date()->sortable()->placeholder('—')
                    ->color(fn (Tenant $r) => $r->status === TenantStatus::Trial && $r->trial_ends_at?->lt(now()->addDays(3)) ? 'danger' : null),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(TenantStatus::class)])
            ->recordActions([
                EditAction::make(),
                Action::make('branding')->icon(Heroicon::OutlinedSwatch)->color('gray')->modalWidth('3xl')
                    ->visible(fn (Tenant $r) => SetTenantFeatureFlags::current($r)['white_label'] ?? false)
                    ->modalDescription('The client\'s own name, logo and colours, shown instead of ours everywhere they can see.')
                    ->fillForm(fn (Tenant $r) => $r->run(function () {
                        $settings = app(TenantSettings::class);
                        $settings->flush();

                        return array_combine(SaveBranding::TEXT_KEYS, array_map(fn (string $k) => $settings->get($k), SaveBranding::TEXT_KEYS))
                            + ['branding_locked' => (bool) $settings->get('branding_locked')];
                    }))
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('app_name')->label('App name')->maxLength(60)->placeholder('AASML Attendance'),
                            TextInput::make('assistant_name')->label('AI assistant name')->maxLength(60)->placeholder('Ask AI'),
                            TextInput::make('email_from_name')->label('Email sender name')->maxLength(60),
                            FileUpload::make('logo')->image()->disk('local')->directory('branding-uploads')->maxSize(150)
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
                            FileUpload::make('icon')->label('App icon (square)')->image()->disk('local')->directory('branding-uploads')->maxSize(150)
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
                            ColorPicker::make('brand_color')->label('Theme colour')->hex()->regex('/^#[0-9a-fA-F]{6}$/'),
                        ]),
                        Toggle::make('branding_locked')->label('Lock branding (the client cannot change it)'),
                    ])
                    ->action(function (Tenant $r, array $data) {
                        $read = function (?string $path): ?string {
                            if (empty($path)) {
                                return null;
                            }
                            $bytes = (string) Storage::disk('local')->get($path);
                            Storage::disk('local')->delete($path);

                            return $bytes;
                        };
                        $logo = $read($data['logo'] ?? null);
                        $icon = $read($data['icon'] ?? null);
                        $r->run(fn () => app(SaveBranding::class)(array_intersect_key($data, array_flip(SaveBranding::TEXT_KEYS)), $logo, $icon,
                            (bool) ($data['branding_locked'] ?? false)));
                        Notification::make()->success()->title('Branding saved')->send();
                    }),
                Action::make('suspend')->icon(Heroicon::OutlinedExclamationTriangle)->color('danger')->requiresConfirmation()
                    ->visible(fn (Tenant $r) => $r->status !== TenantStatus::Suspended)
                    ->action(fn (Tenant $r) => $r->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()])),
                Action::make('activate')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                    ->visible(fn (Tenant $r) => $r->status !== TenantStatus::Active)
                    ->action(fn (Tenant $r) => $r->update(['status' => TenantStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])),
                Action::make('resetOwnerPassword')->label('Reset owner password')->icon(Heroicon::OutlinedKey)->color('gray')
                    ->requiresConfirmation()->modalDescription('Makes a new password for the company owner and shows it to you once. This is recorded in the audit log.')
                    ->action(function (Tenant $r) {
                        $password = Str::password(16, symbols: false);
                        $owner = $r->run(function () use ($password) {
                            $owner = User::role(Role::Owner->value)->orderBy('id')->first();
                            $owner?->update(['password' => $password]);

                            return $owner;
                        });
                        if ($owner === null) {
                            Notification::make()->danger()->title('This company has no owner user')->send();

                            return;
                        }
                        activity('central')->causedBy(auth('super_admin')->user())->performedOn($r)
                            ->event('owner_password_reset')->log("Reset the password of owner #{$owner->id}");
                        Notification::make()->success()->persistent()->title('Owner password reset')
                            ->body("Owner login: {$owner->email} / {$password} (shown once)")->send();
                    }),
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

    /** The status field holds the enum or its value, depending on whether it was just changed. */
    private static function isTrial(mixed $status): bool
    {
        return ($status instanceof TenantStatus ? $status : TenantStatus::tryFrom((string) $status)) === TenantStatus::Trial;
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
