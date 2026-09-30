<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees\Pages;

use App\Filament\Tenant\Resources\Employees\EmployeeResource;
use App\Services\Employees\EmployeeImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportEmployees extends Page
{
    protected static string $resource = EmployeeResource::class;

    protected string $view = 'filament.tenant.employees.import';

    protected static ?string $title = 'Import employees';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array{rows: list<array<string, mixed>>, errors: array<int, list<string>>, creates: int, updates: int}|null */
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
            FileUpload::make('file')->label(__('Excel (.xlsx) or CSV file'))->disk('local')->directory('imports')
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

    public function check(EmployeeImporter $importer): void
    {
        $state = $this->form->getState();
        $path = Storage::disk('local')->path($state['file']);

        try {
            $this->preview = $importer->preview($path);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('Could not read the file'))->body($e->getMessage())->send();
        }
    }

    public function import(EmployeeImporter $importer): void
    {
        if ($this->preview === null || $this->preview['rows'] === []) {
            return;
        }
        $count = $importer->import($this->preview['rows']);
        Notification::make()->success()->title(__(':n employees imported', ['n' => $count]))->send();
        $this->redirect(EmployeeResource::getUrl('index'));
    }
}
