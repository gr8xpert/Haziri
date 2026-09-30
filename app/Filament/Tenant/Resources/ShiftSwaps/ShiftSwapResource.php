<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ShiftSwaps;

use App\Actions\Rosters\ApproveShiftSwap;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ShiftSwapResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = ShiftSwapRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Shift swaps';

    protected static string $viewPermission = 'shifts.manage';

    protected static string $managePermission = 'shifts.manage';

    public static function form(Schema $schema): Schema
    {
        $employees = fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]);

        return $schema->columns(2)->components([
            Select::make('requester_employee_id')->label('Employee')->options($employees)->searchable()->required(),
            Select::make('partner_employee_id')->label('Swaps with')->options($employees)->searchable()->required()->different('requester_employee_id'),
            DatePicker::make('date')->required()->minDate(now()->startOfDay()),
            TextInput::make('reason'),
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
            ->filters([SelectFilter::make('status')->options(['pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected'])->default('pending')])
            ->recordActions([
                ...array_map(fn (bool $ok) => Action::make($ok ? 'approve' : 'reject')->color($ok ? 'success' : 'danger')
                    ->icon($ok ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
                    ->visible(fn (ShiftSwapRequest $r) => $r->status === 'pending')
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
