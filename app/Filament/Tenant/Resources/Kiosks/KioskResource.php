<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Kiosks;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Kiosk;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
        return app(\App\Services\Settings\TenantSettings::class)->featureEnabled('kiosk') && parent::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->default('Reception tablet')->visibleOn('create'),
            Select::make('branch_id')->relationship('branch', 'name')->helperText('Employees of other branches are refused'),
            CheckboxList::make('allowed_modes')->options(['pin' => 'Employee PIN (+ optional 4-digit kiosk PIN)', 'qr' => 'QR badge from the phone'])
                ->default(['pin', 'qr'])->required(),
            Toggle::make('require_photo')->default(true)->helperText('Take a selfie with every punch'),
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
                TextColumn::make('device.last_seen_at')->since()->label('Last punch')->placeholder('never'),
            ])
            ->recordActions([
                Action::make('open')->label('Kiosk link')->icon(Heroicon::OutlinedIdentification)
                    ->modalContent(fn (Kiosk $r) => new \Illuminate\Support\HtmlString(
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
