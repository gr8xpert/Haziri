<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Periods;

use App\Actions\Periods\PeriodActions;
use App\Enums\PeriodStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Filament\Tenant\Pages\Reports\MonthSummary;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Month lock (spec §5.7): open → review → locked, unlock with reason. */
class PeriodResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = AttendancePeriod::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Months (lock)';

    protected static ?string $modelLabel = 'month';

    protected static string $viewPermission = 'periods.lock';

    protected static string $managePermission = 'periods.lock';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('year')->orderByDesc('month'))
            ->columns([
                TextColumn::make('month')->label('Month')->formatStateUsing(fn (AttendancePeriod $r) => $r->label()),
                TextColumn::make('status')->badge()->color(fn (PeriodStatus $state) => match ($state) {
                    PeriodStatus::Open => 'gray', PeriodStatus::Review => 'warning', PeriodStatus::Locked => 'success',
                }),
                TextColumn::make('locked_at')->dateTime()->placeholder('—'),
                TextColumn::make('summary.totals.deduction_days')->label('Deduction days')->placeholder('—'),
                TextColumn::make('summary.totals.late_count')->label('Lates')->placeholder('—'),
            ])
            ->recordUrl(fn (AttendancePeriod $r) => self::viewUrl($r))
            ->recordActions([
                Action::make('view')->label('View')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (AttendancePeriod $r) => self::viewUrl($r)),
                self::step('review', Heroicon::OutlinedPencilSquare, 'warning', PeriodStatus::Open,
                    fn (PeriodActions $a, AttendancePeriod $p) => $a->review($p)),
                self::step('lock', Heroicon::OutlinedShieldCheck, 'success', null,
                    fn (PeriodActions $a, AttendancePeriod $p) => $a->lock($p, auth()->user()))
                    ->visible(fn (AttendancePeriod $r) => $r->status !== PeriodStatus::Locked)
                    ->requiresConfirmation()->modalDescription(__('Locked months cannot be edited or recomputed. The summary is snapshotted.')),
                Action::make('recompute')->label(__('Recompute this month'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (AttendancePeriod $r) => $r->status !== PeriodStatus::Locked && auth()->user()->can('attendance.recompute'))
                    ->requiresConfirmation()
                    ->modalDescription(__('Works out every day of this month again for everyone, with the current rules. It runs in the background.'))
                    ->action(fn (AttendancePeriod $record) => self::recompute($record)),
                Action::make('narrative')->label('Summary')->icon(Heroicon::OutlinedDocumentChartBar)->color('gray')
                    ->visible(fn (AttendancePeriod $r) => $r->narrative !== null)
                    ->fillForm(fn (AttendancePeriod $r) => ['en' => $r->narrative['en'] ?? '', 'ur' => $r->narrative['ur'] ?? ''])
                    ->schema([
                        Textarea::make('en')->label('English')->rows(8),
                        Textarea::make('ur')->label('اردو')->rows(8)->extraInputAttributes(['dir' => 'rtl']),
                    ])
                    ->action(fn (AttendancePeriod $record, array $data) => $record->update(['narrative' => array_merge($record->narrative ?? [], $data, [
                        'edited_by' => auth()->id(), 'edited_at' => now()->toIso8601String(),
                    ])])),
                Action::make('unlock')->icon(Heroicon::OutlinedArrowPath)->color('danger')
                    ->visible(fn (AttendancePeriod $r) => $r->status === PeriodStatus::Locked && auth()->user()->can('periods.unlock'))
                    ->modalDescription(fn (AttendancePeriod $r) => self::hasFinalPayroll($r)
                        ? __('Payroll for this month is already final. Unlocking does not change the final payslips: put any difference into the next month under Bonuses & Adjustments.')
                        : null)
                    ->schema(fn (AttendancePeriod $r) => [
                        Textarea::make('reason')->required(),
                        ...(self::hasFinalPayroll($r) ? [Checkbox::make('final_payroll_understood')->accepted()
                            ->label(__('I understand the final payroll for this month will not change'))] : []),
                    ])
                    ->action(fn (AttendancePeriod $record, array $data, PeriodActions $a) => self::run(fn () => $a->unlock($record, auth()->user(), $data['reason']))),
            ]);
    }

    /** An open month (not only the current one) is worked out again up to today, e.g. after a rule change. */
    private static function recompute(AttendancePeriod $period): void
    {
        [$from, $to] = $period->dates();
        $to = min($to, CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d'));
        if ($from > $to) {
            Notification::make()->warning()->title(__('This month has not started yet.'))->send();

            return;
        }
        RecomputeAttendanceRange::dispatchChunked(Employee::pluck('id')->all(), $from, $to);
        Notification::make()->success()->title(__('Recompute queued'))->send();
    }

    private static function hasFinalPayroll(AttendancePeriod $period): bool
    {
        return PayrollRun::query()->where('period_id', $period->id)->where('status', 'final')->exists();
    }

    /** Every employee's numbers for the month (Month summary report), with a link to each one's day-by-day sheet. */
    private static function viewUrl(AttendancePeriod $period): string
    {
        return MonthSummary::getUrl(['filters' => ['month' => sprintf('%04d-%02d', $period->year, $period->month)]]);
    }

    private static function step(string $name, Heroicon $icon, string $color, ?PeriodStatus $from, \Closure $run): Action
    {
        return Action::make($name)->icon($icon)->color($color)
            ->visible(fn (AttendancePeriod $r) => $from === null || $r->status === $from)
            ->action(fn (AttendancePeriod $record, PeriodActions $a) => self::run(fn () => $run($a, $record)));
    }

    private static function run(callable $fn): void
    {
        try {
            $fn();
            Notification::make()->success()->title(__('Done'))->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPeriods::route('/')];
    }
}
