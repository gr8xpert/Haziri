<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Tenants\LinkCompanyLogin;
use App\Enums\Role;
use App\Http\Controllers\SwitchCompanyController;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Lists the other companies this login can switch to (user menu → Switch company), and links another company
 * after the person types the password of their login there.
 */
class SwitchCompany extends Page
{
    protected string $view = 'filament.tenant.pages.switch-company';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Switch company';

    public string $address = '';

    public string $password = '';

    public static function canAccess(): bool
    {
        return auth()->user() !== null;
    }

    /** User menu: shown once there is a company to switch to, and always to owners and admins so they can link one. */
    public static function showInUserMenu(): bool
    {
        $user = auth()->user();

        return $user !== null && (SwitchCompanyController::optionsFor($user->email) !== []
            || $user->hasAnyRole([Role::Owner->value, Role::Admin->value]));
    }

    public function link(LinkCompanyLogin $link): void
    {
        $this->validate(['address' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        $target = $link(auth()->user(), $this->address, $this->password);
        $this->reset('address', 'password');
        Notification::make()->success()->title(__(':company added. You can switch to it now.', ['company' => $target->company_name]))->send();
    }

    /** @return array<string, string> */
    public function getCompanies(): array
    {
        return SwitchCompanyController::optionsFor(auth()->user()?->email);
    }
}
