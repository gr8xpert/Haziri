<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Kiosks;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Kiosk;
use App\Rules\IpOrCidr;
use App\Services\Settings\TenantSettings;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Reception tablets (spec §6.5). Open the kiosk URL in Chrome kiosk mode on the tablet. */
class KioskResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Kiosk::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Devices;

    protected static ?int $navigationSort = 15;

    protected static string $viewPermission = 'kiosk.manage';

    protected static string $managePermission = 'kiosk.manage';

    public static function canViewAny(): bool
    {
        return app(TenantSettings::class)->featureEnabled('kiosk') && parent::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->default('Reception tablet')->visibleOn('create'),
            Select::make('branch_id')->relationship('branch', 'name')->helperText('Employees of other branches are refused'),
            CheckboxList::make('allowed_modes')->options(fn () => ['pin' => 'Employee PIN (+ optional 4-digit kiosk PIN)', 'qr' => 'QR badge from the phone']
                + (app(TenantSettings::class)->featureEnabled('kiosk_face') ? ['face' => 'Face recognition (enrol faces under People → Face enrolment)'] : []))
                ->default(['pin', 'qr'])->required(),
            Toggle::make('require_photo')->default(true)->helperText('Take a selfie with every punch'),
            Toggle::make('lock_to_device')->label('Only approved tablets')->default(true)
                ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                    if (! $value && in_array('face', (array) $get('allowed_modes'), true)) {
                        $fail(__('Face recognition needs this on: face punches are only trusted from approved tablets.'));
                    }
                })
                ->helperText('The kiosk link works only on tablets you approve under Kiosk tablets, so it cannot be used from home'),
            TagsInput::make('allowed_ips')->label('Only from these internet addresses')->placeholder('e.g. 39.45.10.22 or 39.45.10.0/24')
                ->helperText('Optional: the office internet IP. Leave empty to allow any connection.')
                ->nestedRecursiveRules([new IpOrCidr]),
        ]);
    }

    public static function url(Kiosk $kiosk): string
    {
        return url("/kiosk/{$kiosk->token}");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device.name')->label('Name'),
                TextColumn::make('branch.name')->placeholder('Any branch'),
                TextColumn::make('allowed_modes')->badge(),
                IconColumn::make('require_photo')->boolean()->label('Photo'),
                IconColumn::make('lock_to_device')->boolean()->label('Locked'),
                TextColumn::make('tablets_count')->counts(['tablets' => fn ($q) => $q->where('status', 'pending')])->label('Waiting tablets')
                    ->badge()->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('device.last_seen_at')->since()->label('Last punch')->placeholder('never'),
            ])
            ->recordActions([
                Action::make('open')->label('Kiosk link')->icon(Heroicon::OutlinedIdentification)
                    ->modalContent(fn (Kiosk $r) => new HtmlString(
                        '<p class="text-sm">Open this address on the tablet (Chrome → Add to home screen):</p>'
                        . '<p class="mt-2 break-all font-mono text-sm">' . e(self::url($r)) . '</p>'))
                    ->modalSubmitAction(false),
                Action::make('rotate')->label('New link')->icon(Heroicon::OutlinedArrowPath)->color('warning')->requiresConfirmation()
                    ->modalDescription('The old link stops working immediately. Use this if a tablet is lost.')
                    ->action(function (Kiosk $r) {
                        $r->update(['token' => Kiosk::newToken()]);
                        Notification::make()->success()->title('New kiosk link created')->body(self::url($r))->persistent()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageKiosks::route('/')];
    }
}
