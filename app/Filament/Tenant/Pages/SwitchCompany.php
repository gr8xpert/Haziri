<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Http\Controllers\SwitchCompanyController;
use Filament\Pages\Page;

/** Lists the other companies this login can switch to (user menu → Switch company). */
class SwitchCompany extends Page
{
    protected string $view = 'filament.tenant.pages.switch-company';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Switch company';

    public static function canAccess(): bool
    {
        return SwitchCompanyController::optionsFor(auth()->user()?->email) !== [];
    }

    /** @return array<string, string> */
    public function getCompanies(): array
    {
        return SwitchCompanyController::optionsFor(auth()->user()?->email);
    }
}
