<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveBalances;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\ScopesToTeam;
use App\Filament\Tenant\NavigationGroup;
use App\Models\EmployeeLeaveRule;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeaveBalanceResource extends Resource
{
    use AuthorizesByPermission, ScopesToTeam;

    protected static ?string $model = LeaveBalance::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Leave balances';

    protected static string $viewPermission = 'leave.request';

    protected static string $managePermission = 'leave.adjust_balance';

    public static function getEloquentQuery(): Builder
    {
        return self::scopeToTeam(parent::getEloquentQuery()->with(['employee', 'leaveType']), 'leave.approve_final');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.code')->label('Code')->searchable(),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (LeaveBalance $r) => $r->employee?->fullName()),
                TextColumn::make('leaveType.code')->label('Type')->badge()
                    ->description(fn (LeaveBalance $r) => EmployeeLeaveRule::query()->where(['employee_id' => $r->employee_id, 'leave_type_id' => $r->leave_type_id])->exists()
                        ? __('Special rule') : null),
                TextColumn::make('year')->label('Leave year')->formatStateUsing(fn (int $state) => app(LeaveYear::class)->label($state)),
                TextColumn::make('opening'),
                TextColumn::make('accrued'),
                TextColumn::make('adjusted'),
                TextColumn::make('used'),
                TextColumn::make('pending'),
                TextColumn::make('remaining')->state(fn (LeaveBalance $r) => $r->remaining())->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('year')->label('Leave year')->options(fn () => app(LeaveYear::class)->options())
                    ->default(fn () => (string) app(LeaveYear::class)->current()),
                SelectFilter::make('leave_type_id')->relationship('leaveType', 'name')->label('Type'),
            ])
            ->recordActions([
                Action::make('specialRule')->label(__('Special rule'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                    ->visible(fn () => auth()->user()->can('leave.adjust_balance'))
                    ->modalDescription(__('Only for this employee and leave type. Blank = the normal rule (grade / category, then the leave type).'))
                    ->fillForm(fn (LeaveBalance $r) => EmployeeLeaveRule::query()
                        ->where(['employee_id' => $r->employee_id, 'leave_type_id' => $r->leave_type_id])->first()?->only(['days_per_year', 'carry_forward_max', 'max_consecutive_days', 'reason']) ?? [])
                    ->schema([
                        TextInput::make('days_per_year')->label(__('Days per year'))->numeric()->minValue(0)->suffix('days'),
                        TextInput::make('carry_forward_max')->label(__('Carry forward max'))->numeric()->minValue(0)->suffix('days'),
                        TextInput::make('max_consecutive_days')->label(__('Max consecutive days'))->numeric()->integer()->minValue(1)->suffix('days'),
                        TextInput::make('reason')->required()->placeholder(__('Agreed in the appointment letter')),
                    ])
                    ->action(function (LeaveBalance $record, array $data, LeaveBalanceService $balances) {
                        $keys = ['employee_id' => $record->employee_id, 'leave_type_id' => $record->leave_type_id];
                        $values = array_map(fn ($v) => blank($v) ? null : $v, array_intersect_key($data, array_flip(['days_per_year', 'carry_forward_max', 'max_consecutive_days'])));
                        if (array_filter($values, fn ($v) => $v !== null) === []) {
                            EmployeeLeaveRule::query()->where($keys)->first()?->delete();
                        } else {
                            EmployeeLeaveRule::query()->updateOrCreate($keys, $values + ['reason' => $data['reason'], 'created_by' => auth()->id()]);
                        }
                        // A new yearly quota changes what has been earned this year.
                        $record->update(['accrued' => $balances->accrued($record->employee, $record->leaveType, $record->year)]);
                        Notification::make()->success()->title(__('Special rule saved'))->send();
                    }),
                Action::make('adjust')->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn () => auth()->user()->can('leave.adjust_balance'))
                    ->schema([
                        TextInput::make('days')->numeric()->required()->helperText('Positive to add, negative to deduct'),
                        TextInput::make('reason')->required()->placeholder('Encashment / bonus leave / correction'),
                    ])
                    ->action(function (LeaveBalance $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            LeaveAdjustment::create([
                                'employee_id' => $record->employee_id, 'leave_type_id' => $record->leave_type_id, 'year' => $record->year,
                                'days' => (float) $data['days'], 'reason' => $data['reason'], 'created_by' => auth()->id(),
                                'effective_on' => now(tenant('timezone'))->format('Y-m-d'),
                            ]);
                            $record->increment('adjusted', (float) $data['days']);
                        });
                        Notification::make()->success()->title(__('Balance adjusted'))->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLeaveBalances::route('/')];
    }
}
