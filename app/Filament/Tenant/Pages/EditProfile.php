<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Support\DemoMode;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

/** The standard profile page; in the public demo the email and password are locked (everyone shares the logins). */
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->disabled(fn () => DemoMode::active())
            ->helperText(fn () => DemoMode::active() ? __('Demo logins can\'t be changed. The demo resets every night.') : null);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->disabled(fn () => DemoMode::active());
    }
}
