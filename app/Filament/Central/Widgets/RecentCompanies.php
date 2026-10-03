<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Enums\TenantStatus;
use App\Filament\Central\Resources\Tenants\TenantResource;
use App\Models\Central\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The newest companies on the platform. */
class RecentCompanies extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recently added companies';

    public function table(Table $table): Table
    {
        return $table
            ->query(Tenant::query()->with('plan')->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('company_name'),
                TextColumn::make('plan.name')->placeholder('No plan'),
                TextColumn::make('status')->badge()->color(fn (TenantStatus $state) => match ($state) {
                    TenantStatus::Active => 'success', TenantStatus::Trial => 'info', TenantStatus::Suspended => 'danger',
                }),
                TextColumn::make('trial_ends_at')->label('Trial ends')->date()->placeholder('—'),
                TextColumn::make('created_at')->label('Added')->since(),
            ])
            ->recordUrl(fn (Tenant $record) => TenantResource::getUrl('edit', ['record' => $record]));
    }
}
