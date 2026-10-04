<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Punches\CreateManualPunches;
use App\Enums\EmployeeStatus;
use App\Enums\PunchStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Punch;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Bulk manual entry (spec §6.4): one date, every active employee, In/Out boxes.
 * Only changed/new times are saved (as manual punches through CreateManualPunches).
 */
class DailySheet extends Page
{
    protected string $view = 'filament.tenant.pages.daily-sheet';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Manual Entry';

    protected static ?string $slug = 'manual-entry';

    public string $date = '';

    public ?int $branchId = null;

    public string $reason = 'Manual entry';

    /** @var array<int, array{name: string, code: string, in: ?string, out: ?string, had_in: ?string, had_out: ?string, shift_start: ?string, shift_end: ?string}> */
    public array $rows = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('attendance.manual_entry');
    }

    public function mount(): void
    {
        $this->date = CarbonImmutable::now($this->tz())->format('Y-m-d');
        $this->loadRows();
    }

    public function updatedDate(): void
    {
        $this->loadRows();
    }

    public function updatedBranchId(): void
    {
        $this->loadRows();
    }

    /** @return array<int, string> */
    public function getBranchOptions(): array
    {
        return Branch::orderBy('name')->pluck('name', 'id')->all();
    }

    public function loadRows(): void
    {
        $tz = $this->tz();
        $employees = Employee::query()
            ->with('branch')
            ->where('status', '!=', EmployeeStatus::Left)
            ->where('joined_on', '<=', $this->date)
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->orderBy('code')
            ->get();

        $punches = Punch::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('local_date', $this->date)
            ->where('status', PunchStatus::Valid)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('employee_id');

        $resolver = app(ShiftResolver::class);
        $this->rows = [];
        foreach ($employees as $e) {
            $own = $punches->get($e->id, collect());
            $first = $own->first()?->punched_at?->setTimezone($tz)->format('H:i');
            $last = $own->count() > 1 ? $own->last()->punched_at->setTimezone($tz)->format('H:i') : null;
            $shift = $resolver->resolve($e, $this->date, $tz);
            $this->rows[$e->id] = [
                'name' => $e->fullName(),
                'code' => $e->code,
                'in' => $first,
                'out' => $last,
                'had_in' => $first,
                'had_out' => $last,
                'shift_start' => $shift?->startTime,
                'shift_end' => $shift?->endTime,
            ];
        }
    }

    /** Quick fill: shift start/end for every row that has nothing yet. */
    public function fillPresent(): void
    {
        foreach ($this->rows as $id => $row) {
            if ($row['in'] === null && $row['out'] === null) {
                $this->rows[$id]['in'] = $row['shift_start'];
                $this->rows[$id]['out'] = $row['shift_end'];
            }
        }
    }

    public function markRow(int $employeeId, string $mode): void
    {
        $row = $this->rows[$employeeId];
        [$in, $out] = $mode === 'present' ? [$row['shift_start'], $row['shift_end']] : [$row['had_in'], $row['had_out']];
        $this->rows[$employeeId]['in'] = $in;
        $this->rows[$employeeId]['out'] = $out;
    }

    public function save(CreateManualPunches $create): void
    {
        $saved = 0;
        $errors = [];
        foreach ($this->rows as $id => $row) {
            $newIn = $row['in'] && $row['in'] !== $row['had_in'] ? $row['in'] : null;
            $newOut = $row['out'] && $row['out'] !== $row['had_out'] ? $row['out'] : null;
            if ($newIn === null && $newOut === null) {
                continue;
            }
            try {
                $create(Employee::findOrFail($id), $this->date, $newIn, $newOut, $this->reason, auth()->user());
                $saved++;
            } catch (ValidationException $e) {
                $errors[] = "{$row['code']}: " . implode(' ', $e->validator->errors()->all());
            }
        }

        if ($errors) {
            Notification::make()->danger()->title(__('Some rows were not saved'))->body(implode("\n", $errors))->persistent()->send();
        }
        Notification::make()->success()->title(__(':n employees updated', ['n' => $saved]))->send();
        $this->loadRows();
    }

    private function tz(): string
    {
        return tenant('timezone') ?? config('hazri.default_timezone');
    }
}
