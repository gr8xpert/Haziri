<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Filament\Concerns\ScopesToTeam;
use App\Filament\Tenant\Pages\Dashboard;
use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Live "in office now": punched in today, no punch-out yet. Managers without view-all see only their own team. */
class InOfficeNow extends TableWidget
{
    use InteractsWithPageFilters;
    use ScopesToTeam;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->canAny(['attendance.view_all', 'attendance.view_team']);
    }

    public function table(Table $table): Table
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $today = CarbonImmutable::now($tz)->format('Y-m-d');

        return $table
            ->heading(__('In office now'))
            ->query(fn (): Builder => AttendanceDay::query()->with('employee.department')
                ->whereDate('date', $today)->whereNotNull('first_in')->whereJsonContains('flags', 'missing_out')
                ->tap(fn (Builder $q) => static::scopeToTeam($q, 'attendance.view_all'))
                ->when(Dashboard::selectedBranch($this->pageFilters), fn ($q, $branch) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branch))))
            ->defaultSort('first_in')
            ->paginated([10, 25, 50])
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice)
            ->emptyStateHeading(__('Nobody is checked in right now'))
            ->emptyStateDescription(__('Employees appear here after their first punch of the day.'))
            ->columns([
                TextColumn::make('employee.code')->label(__('Code'))->color('gray'),
                TextColumn::make('employee.first_name')->label(__('Name'))->formatStateUsing(fn (AttendanceDay $r) => $r->employee?->fullName())->weight(FontWeight::Medium),
                TextColumn::make('employee.department.name')->label(__('Department'))->color('gray'),
                TextColumn::make('first_in')->label(__('In since'))->dateTime('H:i', $tz),
                TextColumn::make('late_min')->label(__('Late'))
                    ->formatStateUsing(fn ($state) => $state ? __(':n min', ['n' => $state]) : __('On time'))
                    ->badge()->color(fn ($state) => $state ? 'warning' : 'success'),
            ]);
    }
}
