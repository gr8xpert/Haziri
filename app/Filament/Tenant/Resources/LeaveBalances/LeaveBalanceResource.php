<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveBalances;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\ScopesToTeam;
use App\Filament\Tenant\NavigationGroup;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
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
                TextColumn::make('leaveType.code')->label('Type')->badge(),
                TextColumn::make('year'),
                TextColumn::make('opening'),
                TextColumn::make('accrued'),
                TextColumn::make('adjusted'),
                TextColumn::make('used'),
                TextColumn::make('pending'),
                TextColumn::make('remaining')->state(fn (LeaveBalance $r) => $r->remaining())->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('year')->options(fn () => collect(range(now()->year - 1, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->default((string) now()->year),
                SelectFilter::make('leave_type_id')->relationship('leaveType', 'name')->label('Type'),
            ])
            ->recordActions([
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
