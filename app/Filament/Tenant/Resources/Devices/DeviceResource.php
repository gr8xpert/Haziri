<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Devices;

use App\Enums\DevicePunchSourceType;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Device;
use App\Services\Devices\DeviceCommander;
use App\Support\Brand;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Device management with per-type setup guide and remote commands (spec §9 phase 2). */
class DeviceResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Device::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Devices;

    protected static ?int $navigationSort = 10;

    protected static string $viewPermission = 'devices.manage';

    protected static string $managePermission = 'devices.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(100)->placeholder('Reception K40'),
            Select::make('type')->options(DevicePunchSourceType::class)->required()->live()->default(DevicePunchSourceType::Adms),
            TextInput::make('serial_number')->required()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText('Menu → System Info → Device Info → Serial Number'),
            TextInput::make('model')->maxLength(50)->placeholder('K40 / K50 / MB20 / SpeedFace'),
            Select::make('branch_id')->relationship('branch', 'name'),
            Select::make('timezone')->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()->placeholder('Company timezone'),
            TextInput::make('comm_key')->label('Comm key')->password()->revealable()->maxLength(20)
                ->visible(fn (Get $get) => self::typeIs($get, DevicePunchSourceType::Adms)),
            TextInput::make('ip')->label('LAN IP (for the agent)')->maxLength(45)
                ->visible(fn (Get $get) => self::typeIs($get, DevicePunchSourceType::Agent)),
            Section::make(__('Setup instructions'))->columnSpanFull()->collapsible()->schema([
                Html::make(fn (Get $get) => new HtmlString(self::instructions($get('type'), (string) $get('serial_number')))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('serial_number')->searchable()->label('Serial'),
                TextColumn::make('type')->badge(),
                TextColumn::make('branch.name'),
                TextColumn::make('status')->badge()->color(fn (?string $state) => match ($state) {
                    'online' => 'success', 'offline' => 'danger', default => 'gray',
                }),
                TextColumn::make('last_seen_at')->since()->placeholder('never'),
                TextColumn::make('last_sync_at')->since()->placeholder('never')->label('Last sync'),
                TextColumn::make('unsynced_count')->label('Unsynced'),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::command('syncTime', 'Sync time', Heroicon::OutlinedClock, fn (DeviceCommander $c, Device $d) => $c->syncTime($d)),
                    self::command('pushEmployees', 'Push all employees', Heroicon::OutlinedUsers, fn (DeviceCommander $c, Device $d) => $c->pushEmployees($d)),
                    self::command('reboot', 'Reboot', Heroicon::OutlinedArrowPath, fn (DeviceCommander $c, Device $d) => $c->reboot($d))
                        ->requiresConfirmation(),
                    Action::make('deleteUser')->label('Delete employee from device')->icon(Heroicon::OutlinedExclamationTriangle)->color('danger')
                        ->visible(fn (Device $d) => in_array($d->type, [DevicePunchSourceType::Adms, DevicePunchSourceType::Agent], true))
                        ->schema([TextInput::make('pin')->required()->label('Machine PIN')])
                        ->action(fn (Device $record, array $data, DeviceCommander $c) => self::notify($c->deleteEmployee($record, $data['pin']))),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    private static function command(string $name, string $label, Heroicon $icon, \Closure $run): Action
    {
        return Action::make($name)->label(__($label))->icon($icon)
            ->visible(fn (Device $d) => in_array($d->type, [DevicePunchSourceType::Adms, DevicePunchSourceType::Agent], true))
            ->action(function (Device $record, DeviceCommander $commander) use ($run) {
                try {
                    self::notify($run($commander, $record));
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                }
            });
    }

    private static function notify(int $queued): void
    {
        Notification::make()->success()->title(__(':n command(s) queued', ['n' => $queued]))
            ->body(__('The device picks them up on its next check-in (usually within a minute).'))->send();
    }

    private static function typeIs(Get $get, DevicePunchSourceType $type): bool
    {
        $value = $get('type');

        return $value === $type || $value === $type->value;
    }

    public static function instructions(mixed $type, string $serial): string
    {
        $type = $type instanceof DevicePunchSourceType ? $type : DevicePunchSourceType::tryFrom((string) $type);
        $adms = e(Brand::host('adms'));
        $api = e(Brand::host('api'));

        return match ($type) {
            DevicePunchSourceType::Adms => <<<HTML
                <ol class="list-decimal ps-5 space-y-1 text-sm">
                  <li>On the device: <b>Menu → Comm. → Cloud Server Setting</b> (older firmware: <b>ADMS</b>).</li>
                  <li>Server address: <code>{$adms}</code>, port <code>443</code>, <b>HTTPS on</b> (or port 80 without HTTPS), Enable Domain Name: <b>ON</b>.</li>
                  <li>Proxy: off. Save and restart the device.</li>
                  <li>If you set a comm key here, enter the same key on the device (Comm. → Comm Key).</li>
                  <li>Within a minute this page shows <b>online</b>. Then use <b>Push all employees</b> so PINs and names are on the machine.</li>
                </ol>
                HTML,
            DevicePunchSourceType::Agent => <<<HTML
                <ol class="list-decimal ps-5 space-y-1 text-sm">
                  <li>Install the <b>sync agent</b> on any always-on PC or Raspberry Pi on the same network as the device.</li>
                  <li>Create an agent key: <b>Devices → Agent keys</b>. Copy it — it is shown once.</li>
                  <li>In <code>agent.yaml</code>: <code>server: https://{$api}</code>, <code>api_key: …</code>, and add this device with serial <code>{$serial}</code> and its LAN IP (port 4370).</li>
                  <li>Start the service. This page shows <b>online</b> after the first poll (60 s).</li>
                </ol>
                HTML,
            DevicePunchSourceType::Usb => '<p class="text-sm">No network needed: on the device use <b>Menu → Data Mgt. → USB → Download attendance</b>, then upload the <code>*_attlog.dat</code> file under <b>Devices → Import from USB</b>.</p>',
            default => '<p class="text-sm">Choose a type to see setup steps.</p>',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
