<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\Employees\Pages\ImportEmployees;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\EmployeeImporter;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
| Employee import from the client's own sheet: columns are matched by name, Pakistani formats are read,
| unknown departments can be created, and HR's column choices are remembered.
*/
beforeEach(fn () => Storage::fake('local'));

function clientSheet(string $csv): string
{
    $relative = 'imports/test-' . uniqid() . '.csv';
    Storage::disk('local')->put($relative, $csv);

    return $relative;
}

it('reads a client sheet with its own headings and Pakistani formats', function () {
    $file = clientSheet("Emp No,Name,DOJ,CNIC,Dept,Gender,Machine ID,OT\nE-7,Muhammad Ali Khan,15/01/2024,35202-1234567-1,Production,M,77,Y\n");
    $importer = app(EmployeeImporter::class);

    $strict = $importer->preview(Storage::disk('local')->path($file));
    expect($strict['mapping'])->toBe([
        'emp no' => 'code', 'name' => 'full_name', 'doj' => 'joined_on', 'cnic' => 'cnic', 'dept' => 'department_code',
        'gender' => 'gender', 'machine id' => 'device_pin', 'ot' => 'overtime_eligible',
    ])->and($strict['errors'][2][0])->toContain("Unknown department 'Production'");

    $preview = $importer->preview(Storage::disk('local')->path($file), null, createMissing: true);
    expect($preview['errors'])->toBe([])->and($preview['new_departments'])->toBe(['Production']);

    $importer->import($preview['rows']);
    $e = Employee::where('code', 'E-7')->with('department')->firstOrFail();
    expect($e->first_name)->toBe('Muhammad Ali')->and($e->last_name)->toBe('Khan')
        ->and($e->joined_on->format('Y-m-d'))->toBe('2024-01-15')->and($e->cnic)->toBe('3520212345671')
        ->and($e->gender)->toBe('male')->and($e->device_pin)->toBe('77')->and($e->overtime_eligible)->toBeTrue()
        ->and($e->department?->name)->toBe('Production');
});

it('explains a date it cannot read instead of guessing', function () {
    $file = clientSheet("code,first_name,joined_on\nX1,Sara,31/02/2024\n");

    $preview = app(EmployeeImporter::class)->preview(Storage::disk('local')->path($file));

    expect($preview['errors'][2][0])->toContain("Could not read the joining date '31/02/2024'");
});

it('remembers the columns HR matched by hand', function () {
    $importer = app(EmployeeImporter::class);
    expect($importer->guessMapping(['Ref', 'Staff Name'])['ref'])->toBeNull();

    $importer->rememberMapping(['Ref'], ['ref' => 'code']);

    expect(app(EmployeeImporter::class)->guessMapping(['Ref', 'Staff Name']))->toBe(['ref' => 'code', 'staff name' => 'full_name']);
});

it('lets HR fix a column on the import page and shows what is still missing', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    $upload = UploadedFile::fake()->createWithContent('staff.csv', "Ref,Staff Name,Joining\nR1,Bilal Ahmed,01/03/2025\n");

    $page = Livewire::test(ImportEmployees::class)->set('data.file', $upload)->call('check')->set('createMissing', true);
    expect($page->get('mapping'))->toBe(['', 'full_name', ''])
        ->and($page->instance()->getMissingRequired())->toBe(['Employee code', 'Joining date']);

    $page->set('mapping.0', 'code')->set('mapping.2', 'joined_on');
    expect($page->instance()->getMissingRequired())->toBe([])
        ->and($page->get('preview')['rows'][0])->toMatchArray(['code' => 'R1', 'first_name' => 'Bilal', 'joined_on' => '2025-03-01']);

    $page->call('import');
    expect(Employee::where('code', 'R1')->exists())->toBeTrue()
        ->and(app(EmployeeImporter::class)->guessMapping(['Ref'])['ref'])->toBe('code');
});

it('will not let the browser point the import at another file', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());

    Livewire::test(ImportEmployees::class)->set('file', '../../.env');
})->throws(CannotUpdateLockedPropertyException::class);

it('writes branch or department names starting with = as text, not formulas', function () {
    Department::create(['name' => '=HYPERLINK("http://evil.test","x")', 'code' => 'EVIL']);
    $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';

    app(EmployeeImporter::class)->writeTemplate($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $codes = (string) $zip->getFromName('xl/worksheets/sheet3.xml');
    $zip->close();
    expect($codes)->not->toContain('<f>')->toContain('HYPERLINK');
});

it('writes a template with drop-downs, instructions and the company codes', function () {
    Department::create(['name' => 'Accounts', 'code' => 'ACC']);
    $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';

    app(EmployeeImporter::class)->writeTemplate($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet1 = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $strings = (string) $zip->getFromName('xl/sharedStrings.xml') . (string) $zip->getFromName('xl/worksheets/sheet3.xml');
    $zip->close();

    // Drop-downs go before <legacyDrawing>, or Excel calls the file damaged.
    expect($sheet1)->toContain('<dataValidations count="4">')->toContain('"male,female,other"')
        ->and(strpos($sheet1, '<dataValidations'))->toBeLessThan(strpos($sheet1, '<legacyDrawing') ?: PHP_INT_MAX)
        ->and($workbook)->toContain('name="Instructions"')->toContain('name="Codes"')
        ->and($strings)->toContain('Accounts');
});
