<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Anomalies;

use App\Enums\PunchStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Anomaly;
use App\Models\Punch;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/** Anomaly flags with dismiss / investigate (spec §12.3). */
class AnomalyResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Anomaly::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 70;

    protected static string $viewPermission = 'attendance.view_all';

    protected static string $managePermission = 'attendance.manual_edit';

    /** @var array<string, string> AnomalyDetector types */
    private const TYPES = [
        'buddy_punching' => 'Buddy punching', 'ghost_employee' => 'Ghost employee', 'pattern_shift' => 'Arrival time changed',
        'impossible_sequence' => 'Two branches too close in time', 'impossible_travel' => 'Impossible travel',
    ];

    /** @var array<string, string> */
    private const STATUSES = ['open' => 'Open', 'investigating' => 'Investigating', 'dismissed' => 'Dismissed'];

    public static function getNavigationBadge(): ?string
    {
        $n = Anomaly::where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('detected_on', 'desc')
            ->columns([
                TextColumn::make('detected_on')->date(),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (Anomaly $r) => $r->employee?->fullName()),
                TextColumn::make('explanation')->wrap()->limit(160),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning', 'investigating' => 'info', default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(self::STATUSES)->default('open')])
            ->recordActions([
                Action::make('investigate')->icon(Heroicon::OutlinedIdentification)->color('info')
                    ->visible(fn (Anomaly $r) => $r->status === 'open')
                    ->modalHeading(fn (Anomaly $r) => self::TYPES[$r->type] ?? $r->type)
                    ->modalContent(fn (Anomaly $r) => view('filament.tenant.anomaly-evidence', self::evidence($r)))
                    ->modalSubmitActionLabel(__('Mark as investigating'))
                    ->action(fn (Anomaly $r) => $r->update(['status' => 'investigating', 'reviewed_by' => auth()->id()])),
                Action::make('evidence')->icon(Heroicon::OutlinedDocumentMagnifyingGlass)->color('gray')
                    ->visible(fn (Anomaly $r) => $r->status !== 'open')
                    ->modalHeading(fn (Anomaly $r) => self::TYPES[$r->type] ?? $r->type)
                    ->modalContent(fn (Anomaly $r) => view('filament.tenant.anomaly-evidence', self::evidence($r)))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
                Action::make('dismiss')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->visible(fn (Anomaly $r) => $r->status !== 'dismissed')
                    ->action(fn (Anomaly $r) => $r->update(['status' => 'dismissed', 'reviewed_by' => auth()->id()])),
            ]);
    }

    /**
     * What the rule saw, for HR to judge: the facts and the punches behind them (time, machine or place, source).
     *
     * @return array{explanation: ?string, facts: array<string, string>, punches: list<array{date: string, time: string, employee: string, where: string, source: string}>}
     */
    public static function evidence(Anomaly $anomaly): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $e = $anomaly->evidence ?? [];
        $hm = fn (int $min) => sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        $facts = match ($anomaly->type) {
            'buddy_punching' => [__('Employees') => (string) ($e['names'] ?? ''), __('Days') => count($e['days'] ?? []) . ' — ' . implode(', ', $e['days'] ?? [])],
            'ghost_employee' => [__('No punch since') => (string) ($e['since'] ?? '')],
            'pattern_shift' => [__('Average arrival before') => $hm((int) ($e['before_avg'] ?? 0)), __('Average arrival this week') => $hm((int) ($e['recent_avg'] ?? 0)),
                __('Change') => abs((int) ($e['change_min'] ?? 0)) . ' ' . __('min')],
            'impossible_sequence' => [__('Machines') => implode(' → ', $e['devices'] ?? [])],
            'impossible_travel' => [__('Distance') => ($e['km'] ?? '?') . ' km', __('Time between punches') => ($e['minutes'] ?? '?') . ' ' . __('min')],
            default => [],
        };

        $punches = match ($anomaly->type) {
            'buddy_punching' => Punch::query()->whereIn('employee_id', $e['employees'] ?? [])->whereIn('local_date', $e['days'] ?? [])
                ->where('status', PunchStatus::Valid)->whereNotNull('device_id')->orderBy('punched_at')->limit(60)->get(),
            'impossible_sequence', 'impossible_travel' => Punch::query()->whereIn('id', $e['punches'] ?? [])->orderBy('punched_at')->get(),
            'pattern_shift' => Punch::query()->where('employee_id', $anomaly->employee_id)->where('status', PunchStatus::Valid)
                ->where('local_date', '>=', $anomaly->detected_on->copy()->subDays(7)->format('Y-m-d'))->orderBy('punched_at')->limit(30)->get(),
            default => new EloquentCollection,
        };
        $punches->load(['employee', 'device']);

        return [
            'explanation' => $anomaly->explanation,
            'facts' => array_filter($facts, fn ($v) => $v !== ''),
            'punches' => $punches->map(fn (Punch $p) => [
                'date' => $p->punched_at->setTimezone($tz)->format('D d M'),
                'time' => $p->punched_at->setTimezone($tz)->format('H:i:s'),
                'employee' => $p->employee?->fullName() ?? '?',
                'where' => $p->device?->name ?? ($p->lat !== null ? sprintf('%.4f, %.4f', $p->lat, $p->lng) : '—'),
                'source' => $p->source->getLabel(),
            ])->all(),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAnomalies::route('/')];
    }
}
