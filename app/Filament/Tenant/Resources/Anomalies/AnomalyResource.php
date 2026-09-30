<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Anomalies;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Anomaly;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => str_replace('_', ' ', $state)),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (Anomaly $r) => $r->employee?->fullName()),
                TextColumn::make('explanation')->wrap()->limit(160),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'open' => 'warning', 'investigating' => 'info', default => 'gray',
                }),
            ])
            ->filters([SelectFilter::make('status')->options(['open' => 'open', 'investigating' => 'investigating', 'dismissed' => 'dismissed'])->default('open')])
            ->recordActions([
                Action::make('investigate')->icon(Heroicon::OutlinedIdentification)->color('info')
                    ->visible(fn (Anomaly $r) => $r->status === 'open')
                    ->action(fn (Anomaly $r) => $r->update(['status' => 'investigating', 'reviewed_by' => auth()->id()])),
                Action::make('dismiss')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->visible(fn (Anomaly $r) => $r->status !== 'dismissed')
                    ->action(fn (Anomaly $r) => $r->update(['status' => 'dismissed', 'reviewed_by' => auth()->id()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAnomalies::route('/')];
    }
}
