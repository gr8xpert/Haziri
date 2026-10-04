<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PendingPunches;

use App\Actions\SelfService\DecidePendingPunch;
use App\Enums\PunchStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Punch;
use App\Services\SelfService\FaceMatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/** Punches waiting for HR (spec §6.6): phone punches outside the geofence / office internet, and failed face checks. */
class PendingPunchResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Punch::class;

    protected static ?string $slug = 'punch-approvals';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Punch approvals';

    protected static ?string $modelLabel = 'punch';

    protected static string $viewPermission = 'attendance.manual_edit';

    protected static string $managePermission = 'attendance.manual_edit';

    public static function getNavigationBadge(): ?string
    {
        $n = Punch::where('status', PunchStatus::Pending)->count();

        return $n ? (string) $n : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('employee')->where('status', PunchStatus::Pending);
    }

    public static function table(Table $table): Table
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');

        return $table
            ->defaultSort('punched_at', 'desc')
            ->columns([
                ImageColumn::make('photo_path')->label('Photo')->circular()
                    ->getStateUsing(fn (Punch $r) => $r->photo_path ? URL::temporarySignedRoute('tenant.punch-photo', now()->addMinutes(30), ['punch' => $r->id]) : null),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (Punch $r) => $r->employee?->fullName()),
                TextColumn::make('punched_at')->dateTime('d M H:i', $tz),
                TextColumn::make('direction')->badge(),
                TextColumn::make('why')->label('Why')->badge()->color('warning')->state(fn (Punch $r) => self::reasons($r)),
                TextColumn::make('meta.geofence.distance_m')->label('Distance')->suffix(' m'),
                TextColumn::make('accuracy_m')->label('GPS ±')->suffix(' m'),
                TextColumn::make('lat')->label('Map')->formatStateUsing(fn () => 'open')
                    ->url(fn (Punch $r) => "https://maps.google.com/?q={$r->lat},{$r->lng}", shouldOpenInNewTab: true),
            ])
            ->recordActions([
                self::decide('approve', true),
                self::decide('reject', false),
            ]);
    }

    /** @return list<string> why the punch is waiting, in plain words */
    public static function reasons(Punch $punch): array
    {
        $meta = $punch->meta ?? [];
        $faceCheck = $meta['face_check']['result'] ?? null;

        return array_values(array_filter([
            ! empty($meta['geofence_fail']) ? __('Outside the office area') : null,
            ($meta['geofence']['office_network'] ?? null) === false ? __('Not on the office internet') : null,
            $faceCheck !== null && $faceCheck !== 'match' ? __(FaceMatcher::CHECK_LABELS[$faceCheck] ?? $faceCheck) : null,
        ]));
    }

    private static function decide(string $name, bool $approve): Action
    {
        return Action::make($name)->icon($approve ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
            ->color($approve ? 'success' : 'danger')
            ->schema([Textarea::make('note')])
            ->action(function (Punch $record, array $data, DecidePendingPunch $decide) use ($approve) {
                $decide($record, auth()->user(), $approve, $data['note'] ?? null);
                Notification::make()->success()->title($approve ? __('Punch approved') : __('Punch rejected'))->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPendingPunches::route('/')];
    }
}
