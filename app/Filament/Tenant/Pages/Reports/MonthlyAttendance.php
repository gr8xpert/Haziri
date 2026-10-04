<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Enums\EmployeeStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Jobs\BuildMonthlyAttendanceExport;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Reports\EmployeeStatementReport;
use App\Reports\ReportCatalog;
use App\Reports\ReportExporter;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Reports\MonthlyAttendanceExport;
use App\Services\Reports\MonthlyAttendancePdf;
use App\Services\Reports\MonthlyAttendanceStatement;
use App\Support\Brand;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly attendance report per employee in the layout HR prints (client sample: Al-Abbas "Monthly Attendance
 * Report"): preview on screen, PDF for one employee, or one PDF with a page per employee for a department / branch.
 */
class MonthlyAttendance extends Page
{
    protected string $view = 'filament.tenant.pages.monthly-attendance';

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'reports/monthly-attendance';

    protected static ?int $navigationSort = 22;

    protected static ?string $title = 'Employee monthly report (detailed)';

    protected static bool $shouldRegisterNavigation = false;

    public function getSubheading(): ?string
    {
        return ReportCatalog::description(static::class);
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [ReportsHub::getUrl() => __('Reports'), $this->getTitle()];
    }

    public string $month = '';

    public ?int $employeeId = null;

    public ?int $departmentId = null;

    public ?int $branchId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('reports.view') || $user?->can('reports.view_team'));
    }

    public function mount(): void
    {
        [$y, $m] = app(PayCycle::class)->periodFor(CarbonImmutable::now(tenant('timezone'))->format('Y-m-d'));
        $this->month = sprintf('%04d-%02d', $y, $m);

        // Opened from Month summary: ?month=2026-09&employee=12 (an employee outside the user's scope is ignored).
        if (preg_match('/^\d{4}-\d{2}$/', (string) request()->query('month'))) {
            $this->month = (string) request()->query('month');
        }
        $employeeId = (int) request()->query('employee');
        if ($employeeId > 0 && $this->employees()->whereKey($employeeId)->exists()) {
            $this->employeeId = $employeeId;
        }
    }

    public function updatedDepartmentId(): void
    {
        $this->employeeId = null;
    }

    public function updatedBranchId(): void
    {
        $this->employeeId = null;
    }

    /** @return array<int, string> */
    public function getEmployeeOptions(): array
    {
        return $this->employees()->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])->all();
    }

    /** @return array<int, string> */
    public function getDepartmentOptions(): array
    {
        return Department::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getBranchOptions(): array
    {
        return Branch::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, mixed>|null */
    public function getStatement(): ?array
    {
        $employee = $this->employeeId ? $this->employees()->find($this->employeeId) : null;
        if ($employee === null || ! $this->validMonth()) {
            return null;
        }
        [$y, $m] = $this->yearMonth();

        return app(MonthlyAttendanceStatement::class)->build($employee, $y, $m);
    }

    public function getPeriodLabel(): string
    {
        return $this->validMonth() ? app(PayCycle::class)->label(...$this->yearMonth()) : '';
    }

    public function getAppName(): string
    {
        return Brand::name();
    }

    /** PDF of the chosen employee. */
    public function downloadPdf(): ?StreamedResponse
    {
        $employee = $this->employeeId ? $this->employees()->find($this->employeeId) : null;
        if (! $this->mayExport() || $employee === null || ! $this->validMonth()) {
            return null;
        }

        return $this->streamPdf([$employee], "attendance-{$employee->code}-{$this->month}.pdf");
    }

    /** One PDF with a page for every employee matching the department / branch filters. */
    public function downloadAllPdf(): ?StreamedResponse
    {
        return $this->exportMany(separateFiles: false);
    }

    /** A ZIP with one PDF per employee ("E001 - Ali Khan - Sep 2026.pdf"), e.g. to send each person their own. */
    public function downloadZip(): ?StreamedResponse
    {
        return $this->exportMany(separateFiles: true);
    }

    /** Up to MonthlyAttendanceExport::SYNC_LIMIT employees are made right away; more run in the background. */
    private function exportMany(bool $separateFiles): ?StreamedResponse
    {
        if (! $this->mayExport() || ! $this->validMonth()) {
            return null;
        }
        [$start, $end] = app(PayCycle::class)->range(...$this->yearMonth());
        $employees = $this->employees()
            ->where(fn (Builder $q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $end->format('Y-m-d')))
            ->where(fn (Builder $q) => $q->whereNull('left_on')->orWhere('left_on', '>=', $start->format('Y-m-d')))
            ->get();
        if ($employees->isEmpty()) {
            Notification::make()->warning()->title(__('No employees match these filters.'))->send();

            return null;
        }
        $fileName = "attendance-{$this->month}" . ($separateFiles ? '.zip' : '.pdf');
        [$year, $month] = $this->yearMonth();
        $printedBy = auth()->user()?->name;

        if ($employees->count() > MonthlyAttendanceExport::SYNC_LIMIT) {
            BuildMonthlyAttendanceExport::dispatch($employees->pluck('id')->all(), $year, $month, $separateFiles, (int) auth()->id(), $fileName);
            Notification::make()->info()->title(__('Preparing :n reports in the background', ['n' => $employees->count()]))
                ->body(__('You will get a notification (bell icon) with the download link when it is ready.'))->send();

            return null;
        }

        @set_time_limit(300);
        $export = app(MonthlyAttendanceExport::class);
        if ($separateFiles) {
            $zip = $export->zip($employees, $year, $month, $printedBy);

            return response()->streamDownload(function () use ($zip) {
                readfile($zip);
                @unlink($zip);
            }, $fileName, ['Content-Type' => 'application/zip']);
        }
        $bytes = $export->combinedPdf($employees, $year, $month, $printedBy);

        return response()->streamDownload(fn () => print ($bytes), $fileName, ['Content-Type' => 'application/pdf']);
    }

    public function downloadXlsx(ReportExporter $exporter): mixed
    {
        if (! $this->mayExport() || ! $this->employeeId || ! $this->validMonth()) {
            return null;
        }

        return $exporter->xlsx(new EmployeeStatementReport, ['employee_id' => $this->employeeId, 'month' => $this->month]);
    }

    /** @param list<Employee> $employees */
    private function streamPdf(array $employees, string $filename): StreamedResponse
    {
        @set_time_limit(300);
        $statements = app(MonthlyAttendanceStatement::class)->buildMany(collect($employees), ...$this->yearMonth());
        $bytes = app(MonthlyAttendancePdf::class)->render($statements, auth()->user()?->name);

        return response()->streamDownload(fn () => print ($bytes), $filename, ['Content-Type' => 'application/pdf']);
    }

    /** Employees the user may see (branch managers: their branches; managers: their team), narrowed by the filters. */
    private function employees(): Builder
    {
        $user = auth()->user();

        return Employee::query()
            ->when($this->departmentId, fn ($q, $v) => $q->where('department_id', $v))
            ->when($this->branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($user && ! $user->can('attendance.view_all'), fn ($q) => $q->where('manager_employee_id', $user->employee_id ?? 0))
            ->where(fn (Builder $q) => $q->where('status', '!=', EmployeeStatus::Left)->orWhere('left_on', '>=', now()->subMonths(3)->format('Y-m-d')))
            ->orderBy('code');
    }

    private function mayExport(): bool
    {
        return (bool) auth()->user()?->can('reports.export');
    }

    private function validMonth(): bool
    {
        return preg_match('/^\d{4}-\d{2}$/', $this->month) === 1;
    }

    /** @return array{0: int, 1: int} */
    private function yearMonth(): array
    {
        return array_map('intval', explode('-', $this->month));
    }
}
