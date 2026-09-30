<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Device;
use App\Models\Employee;
use App\Models\UnmappedPin;
use App\Models\UsbImport as UsbImportRecord;
use App\Services\Devices\UsbImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/** Import from USB (spec §6.3): upload → preview (duplicates, unmapped PINs with inline mapping) → import. */
class UsbImport extends Page
{
    protected string $view = 'filament.tenant.pages.usb-import';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Devices;

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Import from USB';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array<string, int|string|null> PIN => employee id chosen inline */
    public array $mapping = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('usb.import');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            Select::make('device_id')->label(__('Device'))->options(fn () => Device::pluck('name', 'id'))
                ->placeholder(__('Unknown / other')),
            FileUpload::make('file')->label(__('attlog.dat, CSV or XLSX'))->disk('local')->directory('usb-imports')
                ->preserveFilenames()->maxSize(20480)->required(),
        ]);
    }

    /** @return array<int, string> */
    public function getEmployeeOptions(): array
    {
        return Employee::query()->whereNull('device_pin')->orderBy('code')->get()
            ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])->all();
    }

    public function check(UsbImporter $importer): void
    {
        $state = $this->form->getState();
        $this->preview = $importer->preview(
            Storage::disk('local')->path($state['file']),
            basename((string) $state['file']),
            isset($state['device_id']) ? Device::find($state['device_id']) : null,
        );
        $this->mapping = array_fill_keys(array_map('strval', array_keys($this->preview['unmapped'])), null);
    }

    public function import(UsbImporter $importer): void
    {
        $state = $this->form->getState();

        // Inline mapping first, so those rows import as valid punches straight away.
        foreach (array_filter($this->mapping) as $pin => $employeeId) {
            Employee::whereKey($employeeId)->whereNull('device_pin')->update(['device_pin' => (string) $pin]);
        }

        $record = $importer->import(
            $state['file'],
            Storage::disk('local')->path($state['file']),
            basename((string) $state['file']),
            isset($state['device_id']) ? Device::find($state['device_id']) : null,
            auth()->user(),
        );

        Notification::make()->success()->title(__('Import finished'))->body(__(':i imported, :d duplicates, :u unmapped', [
            'i' => $record->rows_imported, 'd' => $record->rows_duplicate, 'u' => $record->rows_unmapped,
        ]))->send();
        $this->preview = null;
        $this->form->fill();
    }

    /** @return \Illuminate\Support\Collection<int, UsbImportRecord> */
    public function getHistory(): \Illuminate\Support\Collection
    {
        return UsbImportRecord::query()->with(['device', 'importedBy'])->latest()->limit(10)->get();
    }

    public function unmappedCount(): int
    {
        return UnmappedPin::whereNull('resolved_employee_id')->count();
    }
}
