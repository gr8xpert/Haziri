<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\KioskDevices;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\KioskDevice;
use App\Services\Settings\TenantSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Tablets that opened a locked kiosk link: approve the office tablet, block anything else. */
class KioskDeviceResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = KioskDevice::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Devices;

    protected static ?int $navigationSort = 16;

    protected static ?string $modelLabel = 'kiosk tablet';

    protected static string $viewPermission = 'kiosk.manage';

    protected static string $managePermission = 'kiosk.manage';

    public static function canViewAny(): bool
    {
        return app(TenantSettings::class)->featureEnabled('kiosk') && parent::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = KioskDevice::query()->where('status', KioskDevice::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $decide = fn (string $status) => function (KioskDevice $record) use ($status) {
            $record->update(['status' => $status, 'decided_by' => auth()->id(), 'decided_at' => now()]);
            Notification::make()->success()->title($status === KioskDevice::APPROVED ? __('Tablet approved') : __('Tablet blocked'))->send();
        };

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('pairing_code')->label('Code')->fontFamily('mono')->weight('bold'),
                TextColumn::make('kiosk.device.name')->label('Kiosk'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    KioskDevice::APPROVED => 'success', KioskDevice::BLOCKED => 'danger', default => 'warning',
                }),
                TextColumn::make('label')->label('Browser')->limit(50)->tooltip(fn (KioskDevice $r) => $r->label)->toggleable(),
                TextColumn::make('ip')->label('IP address'),
                TextColumn::make('created_at')->label('First seen')->since(),
                TextColumn::make('last_seen_at')->label('Last seen')->since(),
            ])
            ->filters([SelectFilter::make('status')->options([
                KioskDevice::PENDING => 'Waiting', KioskDevice::APPROVED => 'Approved', KioskDevice::BLOCKED => 'Blocked',
            ])])
            ->recordActions([
                Action::make('approve')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (KioskDevice $r) => $r->status !== KioskDevice::APPROVED && auth()->user()->can('kiosk.manage'))
                    ->requiresConfirmation()
                    ->modalDescription(fn (KioskDevice $r) => __('Only approve if the tablet in the office shows code :code.', ['code' => $r->pairing_code]))
                    ->action($decide(KioskDevice::APPROVED)),
                Action::make('block')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (KioskDevice $r) => $r->status !== KioskDevice::BLOCKED && auth()->user()->can('kiosk.manage'))
                    ->requiresConfirmation()
                    ->action($decide(KioskDevice::BLOCKED)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListKioskDevices::route('/')];
    }
}
