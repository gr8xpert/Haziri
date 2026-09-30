<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\UnmappedPins;

use App\Actions\Punches\MapUnmappedPinAction;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\UnmappedPin;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** "PIN 47 punched but no employee has PIN 47" — map it and its punches are re-attached (spec §6.7). */
class UnmappedPinResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = UnmappedPin::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Devices;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Unmapped PINs';

    protected static string $viewPermission = 'employees.manage';

    protected static string $managePermission = 'employees.manage';

    public static function getNavigationBadge(): ?string
    {
        $n = UnmappedPin::whereNull('resolved_employee_id')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('first_seen_at', 'desc')
            ->columns([
                TextColumn::make('device_pin')->label('PIN')->searchable(),
                TextColumn::make('device_user_name')->label('Name on device')->placeholder('—'),
                TextColumn::make('device.name')->placeholder('Manual / USB'),
                TextColumn::make('count')->label('Punches'),
                TextColumn::make('first_seen_at')->since(),
                TextColumn::make('resolvedEmployee.first_name')->label('Mapped to')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('resolved')->nullable()->attribute('resolved_employee_id')->default(false)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('resolved_employee_id'),
                        false: fn (Builder $q) => $q->whereNull('resolved_employee_id'),
                    ),
            ])
            ->recordActions([
                Action::make('map')->label('Map to employee')->icon(Heroicon::OutlinedUsers)
                    ->visible(fn (UnmappedPin $r) => $r->resolved_employee_id === null)
                    ->schema([
                        Select::make('employee_id')->label('Employee')->required()->searchable()
                            ->options(fn () => Employee::query()->whereNull('device_pin')->orderBy('code')->get()
                                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"]))
                            ->helperText('Only employees without a machine PIN are listed.'),
                    ])
                    ->action(function (UnmappedPin $record, array $data, MapUnmappedPinAction $map) {
                        try {
                            $n = $map($record, Employee::findOrFail($data['employee_id']));
                            Notification::make()->success()->title(__(':n punches re-attached', ['n' => $n]))->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUnmappedPins::route('/')];
    }
}
