<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\UnknownDevices;

use App\Enums\DevicePunchSourceType;
use App\Models\Central\Tenant;
use App\Models\Central\UnknownDevice;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Devices that pushed to adms.* with an unregistered serial; attach one to a company in one click (spec §6.1) or block it. */
class UnknownDeviceResource extends Resource
{
    protected static ?string $model = UnknownDevice::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function getNavigationBadge(): ?string
    {
        $n = UnknownDevice::whereNull('blocked_at')->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('serial_number')->searchable(),
                TextColumn::make('ip'),
                TextColumn::make('likely')->label('Likely company')->placeholder('—')
                    ->state(fn (UnknownDevice $r) => $r->likelyTenants()->pluck('company_name')->implode(', ') ?: null)
                    ->description(fn (UnknownDevice $r) => $r->likelyTenants()->isNotEmpty() ? __('Same IP as their machine: a hint, confirm the serial') : null),
                TextColumn::make('hits')->label('Check-ins')->tooltip('Counted at most once a minute'),
                TextColumn::make('first_seen_at')->since(),
                TextColumn::make('last_seen_at')->since(),
                TextColumn::make('blocked_at')->label('Blocked')->since()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('blocked')->label('Blocked')->nullable()->attribute('blocked_at')
                    ->trueLabel('Blocked only')->falseLabel('Waiting only')->placeholder('All')->default(false),
            ])
            ->recordActions([
                Action::make('attach')->label('Attach to company')->icon(Heroicon::OutlinedBuildingOffice2)
                    ->fillForm(fn () => ['name' => 'ADMS device'])
                    ->schema([
                        Select::make('tenant_id')->label('Company')->options(fn () => Tenant::orderBy('company_name')->pluck('company_name', 'id'))->searchable()->required()
                            ->helperText(fn (UnknownDevice $record) => ($record->likelyTenants()->isNotEmpty()
                                ? __('Likely: :names (same IP). ', ['names' => $record->likelyTenants()->pluck('company_name')->implode(', ')]) : '')
                                . __('An IP can be shared (mobile networks, some internet providers): confirm the serial :serial with the company first; its punches go to the company you choose.', ['serial' => $record->serial_number])),
                        TextInput::make('name')->default('ADMS device')->required(),
                    ])
                    ->action(fn (UnknownDevice $record, array $data) => self::attach($record, Tenant::findOrFail($data['tenant_id']), $data['name'])),
                Action::make('block')->icon(Heroicon::OutlinedNoSymbol)->color('danger')->requiresConfirmation()
                    ->modalDescription('The machine is turned away straight away and no longer recorded. Unblock it any time from the "Blocked only" filter.')
                    ->visible(fn (UnknownDevice $record) => $record->blocked_at === null)
                    ->action(fn (UnknownDevice $record) => $record->block()),
                Action::make('unblock')->icon(Heroicon::OutlinedCheckCircle)->color('gray')
                    ->visible(fn (UnknownDevice $record) => $record->blocked_at !== null)
                    ->action(fn (UnknownDevice $record) => $record->unblock()),
                DeleteAction::make(),
            ]);
    }

    /** Creating the device inside the company registers the serial centrally and removes this row; buffered punches follow. */
    private static function attach(UnknownDevice $record, Tenant $tenant, string $name): void
    {
        $tenant->run(fn () => Device::create([
            'name' => $name, 'serial_number' => $record->serial_number, 'type' => DevicePunchSourceType::Adms,
        ]));
        Notification::make()->success()->title(__('Device attached to :company', ['company' => $tenant->company_name]))->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUnknownDevices::route('/')];
    }
}
