<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Punches;

use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Punch;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PunchResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Punch::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Punch log';

    protected static string $viewPermission = 'attendance.view_all';

    protected static string $managePermission = 'attendance.manual_entry';

    public static function table(Table $table): Table
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');

        return $table
            ->defaultSort('punched_at', 'desc')
            ->columns([
                TextColumn::make('punched_at')->label('Time')->dateTime('d M Y H:i:s', $tz)->sortable(),
                TextColumn::make('employee.first_name')->label('Employee')
                    ->formatStateUsing(fn (Punch $r) => $r->employee?->fullName())->placeholder('— unmapped —')
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('employee', fn ($e) => $e
                        ->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('code', $s))),
                TextColumn::make('device_pin')->label('PIN')->searchable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('direction')->badge()->color('gray'),
                TextColumn::make('status')->badge()->color(fn (PunchStatus $state) => match ($state) {
                    PunchStatus::Valid => 'success', PunchStatus::Duplicate => 'gray',
                    PunchStatus::Unmapped => 'warning', PunchStatus::Rejected => 'danger',
                }),
                IconColumn::make('is_manual')->boolean()->label('Manual'),
                TextColumn::make('reason')->limit(30)->toggleable(),
                TextColumn::make('enteredBy.name')->label('Entered by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date')->schema([DatePicker::make('from')->default(now()->subDays(7)), DatePicker::make('to')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('local_date', '>=', $d))
                        ->when($data['to'] ?? null, fn ($q, $d) => $q->where('local_date', '<=', $d))),
                SelectFilter::make('employee_id')->relationship('employee', 'first_name')->searchable()->label('Employee'),
                SelectFilter::make('source')->options(PunchSource::class),
                SelectFilter::make('status')->options(PunchStatus::class),
            ])
            ->recordActions([
                Action::make('delete')->icon(Heroicon::OutlinedArrowPath)->color('danger')->label('Remove')
                    ->visible(fn (Punch $r) => self::canRemove($r))
                    ->requiresConfirmation()
                    ->schema([TextInput::make('reason')->required()->label('Why?')])
                    ->action(function (Punch $r, array $data) {
                        $r->update(['meta' => array_merge($r->meta ?? [], ['removed_reason' => $data['reason'], 'removed_by' => auth()->id()])]);
                        $r->delete();
                        Notification::make()->success()->title(__('Punch removed'))->send();
                    }),
            ]);
    }

    /** Receptionists may remove only their own-day manual punches; HR (manual_edit) any (spec §6.4). */
    public static function canRemove(Punch $punch): bool
    {
        $user = auth()->user();
        if ($user->can('attendance.manual_edit')) {
            return true;
        }
        $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');

        return $punch->is_manual && $user->can('attendance.manual_entry') && $punch->local_date->format('Y-m-d') === $today;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPunches::route('/')];
    }
}
