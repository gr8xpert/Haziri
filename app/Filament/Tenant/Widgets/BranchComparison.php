<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Filament\Tenant\Pages\Dashboard;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Settings\TenantSettings;
use App\Support\BranchAccess;
use Carbon\CarbonImmutable;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Today, branch by branch: staff, present, late, not in yet, on leave, attendance %. */
class BranchComparison extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /** @var array<int, array<string, int>>|null */
    private ?array $stats = null;

    public static function canView(): bool
    {
        return app(TenantSettings::class)->featureEnabled('multi_branch')
            && (bool) auth()->user()?->can('attendance.view_all')
            && count(Dashboard::branchOptions()) > 1;
    }

    public function table(Table $table): Table
    {
        $stat = fn (string $key) => fn (Branch $record) => $this->stats()[$record->id][$key] ?? 0;
        $ids = BranchAccess::ids();

        return $table
            ->heading(__('Branches today'))
            ->query(fn (): Builder => Branch::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids)))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('Branch'))->weight(FontWeight::Medium),
                TextColumn::make('staff')->label(__('Staff'))->state($stat('staff')),
                TextColumn::make('present')->label(__('Present'))->state($stat('present'))->color('success'),
                TextColumn::make('late')->label(__('Late'))->state($stat('late'))->color(fn ($state) => $state ? 'warning' : 'gray'),
                TextColumn::make('not_in')->label(__('Not in yet / absent'))->state($stat('not_in'))->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextColumn::make('on_leave')->label(__('On leave'))->state($stat('on_leave')),
                TextColumn::make('rate')->label(__('Attendance'))->state(fn (Branch $record) => $this->stats()[$record->id]['rate'] ?? 0)->suffix('%')
                    ->badge()->color(fn ($state) => $state >= 90 ? 'success' : ($state >= 75 ? 'warning' : 'danger')),
            ]);
    }

    /** @return array<int, array<string, int>> branch id => counters, computed once per render */
    private function stats(): array
    {
        if ($this->stats !== null) {
            return $this->stats;
        }
        $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');
        $staff = Employee::query()->where('status', '!=', EmployeeStatus::Left)->where('joined_on', '<=', $today)
            ->get(['id', 'branch_id'])->groupBy('branch_id');
        $days = AttendanceDay::query()->whereDate('date', $today)->get(['employee_id', 'status', 'flags'])->keyBy('employee_id');
        $present = [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh];
        $leave = [AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid, AttendanceStatus::HalfDayLeave];
        $off = [AttendanceStatus::Holiday, AttendanceStatus::WeeklyOff, AttendanceStatus::Exempted];

        $this->stats = [];
        foreach ($staff as $branchId => $people) {
            $c = ['staff' => $people->count(), 'present' => 0, 'late' => 0, 'on_leave' => 0, 'off' => 0];
            foreach ($people as $e) {
                $d = $days->get($e->id);
                $c['present'] += (int) in_array($d?->status, $present, true);
                $c['late'] += (int) in_array('late', $d->flags ?? [], true);
                $c['on_leave'] += (int) in_array($d?->status, $leave, true);
                $c['off'] += (int) in_array($d?->status, $off, true);
            }
            $expected = $c['staff'] - $c['on_leave'] - $c['off'];
            $c['not_in'] = max(0, $expected - $c['present']);
            $c['rate'] = $expected > 0 ? (int) round($c['present'] / $expected * 100) : 100;
            $this->stats[(int) $branchId] = $c;
        }

        return $this->stats;
    }
}
