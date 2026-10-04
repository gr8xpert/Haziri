<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees\Pages;

use App\Filament\Tenant\Resources\Employees\EmployeeResource;
use App\Services\Ai\AiGateway;
use App\Services\Employees\EmployeeImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Upload our template or the client's own sheet → confirm which column is which (guessed, remembered for next time)
 * → preview with per-row errors → import.
 */
class ImportEmployees extends Page
{
    protected static string $resource = EmployeeResource::class;

    protected string $view = 'filament.tenant.employees.import';

    protected static ?string $title = 'Import employees';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Stored upload being worked on (relative to the local disk). */
    public ?string $file = null;

    /** @var list<string> field per column of the file, by position ('' = skip); headings can hold dots, so no keys */
    public array $mapping = [];

    /** Departments named in the file but not in Haziri are created instead of being errors. */
    public bool $createMissing = false;

    /** @var array<string, mixed>|null result of EmployeeImporter::preview() */
    public ?array $preview = null;

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('employees.import');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FileUpload::make('file')->label(__('Excel (.xlsx) or CSV file: our template, or your own list'))->disk('local')->directory('imports')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'text/plain', 'application/csv',
                ])
                ->maxSize(5120)->required(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label(__('Download template'))->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->action(function (EmployeeImporter $importer): BinaryFileResponse {
                    $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
                    $importer->writeTemplate($path);

                    return response()->download($path, 'employees-template.xlsx')->deleteFileAfterSend();
                }),
        ];
    }

    /** Read a newly uploaded file: guess its columns and preview it. */
    public function check(EmployeeImporter $importer): void
    {
        $this->file = $this->form->getState()['file'];
        $this->mapping = [];
        $this->preview = null;
        $this->refreshPreview($importer);
    }

    public function updatedMapping(): void
    {
        $this->refreshPreview(app(EmployeeImporter::class));
    }

    public function updatedCreateMissing(): void
    {
        $this->refreshPreview(app(EmployeeImporter::class));
    }

    /** Optional: let the AI place the columns that are still unmatched. Only the headings are sent. */
    public function suggestWithAi(EmployeeImporter $importer, AiGateway $ai): void
    {
        $headers = $this->preview['headers'] ?? [];
        $unmatched = array_values(array_filter($headers, fn (string $h, int $i) => blank($this->mapping[$i] ?? null), ARRAY_FILTER_USE_BOTH));
        $suggested = $importer->suggestWithAi($unmatched, $ai);
        $matched = 0;
        foreach ($headers as $i => $header) {
            $field = $suggested[strtolower($header)] ?? null;
            if ($field !== null && blank($this->mapping[$i] ?? null) && ! in_array($field, $this->mapping, true)) {
                $this->mapping[$i] = $field;
                $matched++;
            }
        }
        if ($matched === 0) {
            Notification::make()->warning()->title(__('No suggestions for the remaining columns.'))->send();

            return;
        }
        $this->refreshPreview($importer);
        Notification::make()->success()->title(__(':n columns matched. Please check them.', ['n' => $matched]))->send();
    }

    public function canSuggestWithAi(): bool
    {
        return app(AiGateway::class)->available();
    }

    /** @return array<string, string> */
    public function getFieldOptions(): array
    {
        return array_map(fn (string $label) => __($label), EmployeeImporter::FIELDS);
    }

    /** @return list<string> labels of required fields no column is matched to */
    public function getMissingRequired(): array
    {
        $chosen = array_filter($this->mapping);
        $missing = array_diff(EmployeeImporter::REQUIRED, $chosen);
        if (in_array('full_name', $chosen, true)) {
            $missing = array_diff($missing, ['first_name']);
        }

        return array_values(array_map(fn (string $f) => __(EmployeeImporter::FIELDS[$f]), $missing));
    }

    public function import(EmployeeImporter $importer): void
    {
        if ($this->preview === null || $this->preview['rows'] === []) {
            return;
        }
        $importer->rememberMapping($this->preview['headers'], $this->byHeading() ?? []);
        $count = $importer->import($this->preview['rows']);
        Notification::make()->success()->title(__(':n employees imported', ['n' => $count]))->send();
        $this->redirect(EmployeeResource::getUrl('index'));
    }

    /** @return array<string, ?string>|null the importer's form (lower-cased heading => field), null = let it guess */
    private function byHeading(): ?array
    {
        if ($this->mapping === [] || $this->preview === null) {
            return null;
        }
        $out = [];
        foreach ($this->preview['headers'] as $i => $header) {
            $out[strtolower($header)] ??= filled($this->mapping[$i] ?? null) ? (string) $this->mapping[$i] : null;
        }

        return $out;
    }

    private function refreshPreview(EmployeeImporter $importer): void
    {
        if ($this->file === null) {
            return;
        }
        try {
            $this->preview = $importer->preview(Storage::disk('local')->path($this->file), $this->byHeading(), $this->createMissing);
            $this->mapping = array_map(fn (string $h) => $this->preview['mapping'][strtolower($h)] ?? '', $this->preview['headers']);
        } catch (\Throwable $e) {
            report($e);
            $this->preview = null;
            Notification::make()->danger()->title(__('Could not read the file'))->body($e->getMessage())->send();
        }
    }
}
