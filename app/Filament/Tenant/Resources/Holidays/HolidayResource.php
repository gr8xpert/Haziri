<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Holidays;

use App\Enums\EmployeeStatus;
use App\Enums\HolidayType;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayOptIn;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HolidayResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Holiday::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 30;

    protected static string $viewPermission = 'holidays.manage';

    protected static string $managePermission = 'holidays.manage';

    /** Provisional (moon-sighting) holidays in the next 30 days that still need confirming. */
    public static function getNavigationBadge(): ?string
    {
        $n = Holiday::query()->where('needs_confirmation', true)
            ->whereBetween('date', [now(tenant('timezone'))->format('Y-m-d'), now(tenant('timezone'))->addDays(30)->format('Y-m-d')])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Provisional holidays to confirm');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->required(),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('type')->options(HolidayType::class)->default(HolidayType::Company)->required()
                ->helperText('Optional: a day off only for the employees who take it (choose them with "Who takes it", or they opt in from the app).'),
            Select::make('branch_id')->relationship('branch', 'name')->placeholder('All branches'),
            Toggle::make('is_paid')->label('Paid')->default(true)->helperText('Off: still a day off, but not paid.'),
            Toggle::make('needs_confirmation')->helperText('Provisional date (moon sighting)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date('D, d M Y')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge()
                    ->description(fn (Holiday $r) => $r->type === HolidayType::Optional ? __(':n taking it', ['n' => $r->opt_ins_count]) : null),
                TextColumn::make('branch.name')->placeholder('All'),
                IconColumn::make('is_paid')->label('Paid')->boolean(),
                IconColumn::make('needs_confirmation')->boolean()->label('Provisional')
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)->trueColor('warning')->falseIcon(null),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(fn () => collect(range(now()->year - 1, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y])->all())
                    ->default((string) now()->year)
                    ->query(fn (Builder $q, array $data) => $data['value'] ? $q->whereYear('date', $data['value']) : $q),
                TernaryFilter::make('needs_confirmation')->label('Provisional'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('optIns'))
            ->recordActions([
                Action::make('optIns')->label('Who takes it')->icon(Heroicon::OutlinedUserGroup)->color('gray')
                    ->visible(fn (Holiday $r) => $r->type === HolidayType::Optional)
                    ->fillForm(fn (Holiday $r) => ['employee_ids' => $r->optIns()->pluck('employee_id')->all()])
                    ->schema([
                        Select::make('employee_ids')->label('Employees taking this day off')->multiple()->searchable()
                            ->options(fn (Holiday $record) => Employee::query()->where('status', '!=', EmployeeStatus::Left)
                                ->when($record->branch_id, fn ($q, $b) => $q->where('branch_id', $b))->orderBy('code')->get()
                                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
                    ])
                    ->action(function (Holiday $record, array $data) {
                        $wanted = array_map('intval', $data['employee_ids'] ?? []);
                        // One by one, so each change is audited and recalculates that employee's day.
                        $record->optIns()->whereNotIn('employee_id', $wanted)->get()->each->delete();
                        foreach (array_diff($wanted, $record->optIns()->pluck('employee_id')->all()) as $id) {
                            HolidayOptIn::create(['holiday_id' => $record->id, 'employee_id' => $id, 'created_by' => auth()->id()]);
                        }
                        Notification::make()->success()->title(__(':n employees take :name', ['n' => count($wanted), 'name' => $record->name]))->send();
                    }),
                Action::make('confirm')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                    ->visible(fn (Holiday $r) => $r->needs_confirmation)
                    ->action(fn (Holiday $r) => $r->update(['needs_confirmation' => false])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHolidays::route('/')];
    }
}
