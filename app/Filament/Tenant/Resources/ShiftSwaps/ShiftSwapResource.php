<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ShiftSwaps;

use App\Actions\Rosters\ApproveShiftSwap;
use App\Enums\ShiftSwapStatus;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ShiftSwapResource extends Resource
{
    use RequiresFeature;

    protected static string $feature = 'rosters';

    protected static ?string $model = ShiftSwapRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Shift swaps';

    protected static string $managePermission = 'shifts.manage';

    /** Managers (first-step approvers) may open the list to decide their own team's swaps; everything else needs shifts.manage. */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        if (in_array($action, ['viewAny', 'view'], true) && auth()->user()?->can('leave.approve_first')) {
            return Response::allow();
        }

        return auth()->user()?->can(static::$managePermission) ? Response::allow() : Response::deny();
    }

    /** HR sees every swap; a manager sees the swaps asked by their direct reports. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user === null || $user->can('shifts.manage')) {
            return $query;
        }

        return $query->whereHas('requester', fn (Builder $q) => $q->where('manager_employee_id', $user->employee_id ?? 0));
    }

    public static function getNavigationBadge(): ?string
    {
        $n = self::getEloquentQuery()->where('status', ShiftSwapStatus::Pending)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        $employees = fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]);

        return $schema->columns(2)->components([
            Select::make('requester_employee_id')->label('Employee')->options($employees)->searchable()->required()->live(),
            Select::make('partner_employee_id')->label('Swaps with')->options($employees)->searchable()->required()->different('requester_employee_id')->live(),
            DatePicker::make('date')->required()->minDate(now()->startOfDay())->live()
                ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                    $a = Employee::find($get('requester_employee_id'));
                    $b = Employee::find($get('partner_employee_id'));
                    if ($a === null || $b === null) {
                        return;
                    }
                    try {
                        app(ApproveShiftSwap::class)->shiftIds($a, $b, substr((string) $value, 0, 10));
                    } catch (ValidationException $e) {
                        $fail(implode(' ', $e->validator->errors()->all()));
                    }
                }),
            TextInput::make('reason'),
            Text::make(fn (Get $get) => app(ApproveShiftSwap::class)->describe(
                Employee::find($get('requester_employee_id')), Employee::find($get('partner_employee_id')), $get('date') ? substr((string) $get('date'), 0, 10) : null,
            ) ?? __('Pick both employees and the date to see their shifts.'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date(),
                TextColumn::make('requester.first_name')->label('Employee')->formatStateUsing(fn (ShiftSwapRequest $r) => $r->requester?->fullName()),
                TextColumn::make('partner.first_name')->label('Swaps with')->formatStateUsing(fn (ShiftSwapRequest $r) => $r->partner?->fullName()),
                TextColumn::make('reason')->limit(30),
                TextColumn::make('status')->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(ShiftSwapStatus::class)->default(ShiftSwapStatus::Pending->value)])
            ->recordActions([
                ...array_map(fn (bool $ok) => Action::make($ok ? 'approve' : 'reject')->color($ok ? 'success' : 'danger')
                    ->icon($ok ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
                    ->visible(fn (ShiftSwapRequest $r) => $r->status === ShiftSwapStatus::Pending && ApproveShiftSwap::canDecide(auth()->user(), $r))
                    ->action(function (ShiftSwapRequest $record, ApproveShiftSwap $approve) use ($ok) {
                        try {
                            $approve($record, auth()->user(), $ok);
                            Notification::make()->success()->title($ok ? __('Swap approved') : __('Swap rejected'))->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                        }
                    }), [true, false]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageShiftSwaps::route('/')];
    }
}
