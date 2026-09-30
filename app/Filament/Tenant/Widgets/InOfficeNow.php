<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets;

use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Live "in office now": punched in today, no punch-out yet. */
class InOfficeNow extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('attendance.view_all');
    }

    public function table(Table $table): Table
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $today = CarbonImmutable::now($tz)->format('Y-m-d');

        return $table
            ->heading(__('In office now'))
            ->query(fn (): Builder => AttendanceDay::query()->with('employee.department')
                ->whereDate('date', $today)->whereNotNull('first_in')->whereJsonContains('flags', 'missing_out'))
            ->defaultSort('first_in')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('employee.code')->label(__('Code')),
                TextColumn::make('employee.first_name')->label(__('Name'))->formatStateUsing(fn (AttendanceDay $r) => $r->employee?->fullName()),
                TextColumn::make('employee.department.name')->label(__('Department')),
                TextColumn::make('first_in')->label(__('In since'))->dateTime('H:i', $tz),
                TextColumn::make('late_min')->label(__('Late'))->suffix(' min')->formatStateUsing(fn ($state) => $state ?: '')->color('warning'),
            ]);
    }
}
