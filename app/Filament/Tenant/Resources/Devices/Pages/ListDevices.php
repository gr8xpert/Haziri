<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Devices\Pages;

use App\Filament\Tenant\Pages\UsbImport;
use App\Filament\Tenant\Resources\Devices\DeviceResource;
use App\Models\Central\AgentKey;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('usb')->label(__('Import from USB'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->url(UsbImport::getUrl()),
            Action::make('agentKey')->label(__('New agent key'))->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                ->schema([TextInput::make('label')->required()->default('Office PC')->maxLength(100)])
                ->action(function (array $data) {
                    ['plain' => $plain] = AgentKey::issue((string) tenant('id'), $data['label']);
                    Notification::make()->success()->persistent()->title(__('Agent key created — copy it now'))
                        ->body($plain)->send();
                }),
            Action::make('revokeKeys')->label(__('Agent keys'))->icon(Heroicon::OutlinedListBullet)->color('gray')
                ->modalSubmitActionLabel(__('Revoke selected'))
                ->schema([
                    \Filament\Forms\Components\CheckboxList::make('revoke')->label(__('Active keys (tick to revoke)'))
                        ->options(fn () => AgentKey::where('tenant_id', tenant('id'))->whereNull('revoked_at')->get()
                            ->mapWithKeys(fn (AgentKey $k) => [$k->id => $k->label . ' · last used ' . ($k->last_used_at?->diffForHumans() ?? 'never')])),
                ])
                ->action(fn (array $data) => AgentKey::where('tenant_id', tenant('id'))->whereIn('id', $data['revoke'] ?? [])->update(['revoked_at' => now()])),
            CreateAction::make(),
        ];
    }
}
