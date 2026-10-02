<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Models\Branch;
use App\Services\Settings\TenantSettings;
use App\Support\BranchAccess;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Home page. With several branches (feature multi_branch) a branch switcher filters the widgets. */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('branch_id')->label(__('Branch'))->placeholder(__('All branches'))
                    ->options(fn () => self::branchOptions()),
            ])->columns(3)->visible(fn () => count(self::branchOptions()) > 1),
        ]);
    }

    /** @return array<int, string> branches the user may pick (all of them unless they are branch-limited) */
    public static function branchOptions(): array
    {
        if (! app(TenantSettings::class)->featureEnabled('multi_branch')) {
            return [];
        }
        $ids = BranchAccess::ids();

        return Branch::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->pluck('name', 'id')->all();
    }

    /** The picked branch, if it is one the user may see. */
    public static function selectedBranch(?array $filters): ?int
    {
        $id = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        return $id !== null && array_key_exists($id, self::branchOptions()) ? $id : null;
    }
}
