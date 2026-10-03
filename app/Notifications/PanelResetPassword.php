<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Brand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Admin-panel "Forgot password?" mail, in place of Filament's queued one: sent inside the request, so the client's
 * branding (ApplyTenantBranding sets app.name for white-label) is used and the sender is never the platform's name
 * for a white-label client.
 */
class PanelResetPassword extends ResetPassword
{
    public string $url;

    public function toMail($notifiable): MailMessage
    {
        $from = Brand::whiteLabel() ? Brand::name() : tenant('company_name') . ' via ' . Brand::name();

        return parent::toMail($notifiable)->from((string) config('mail.from.address'), $from);
    }

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
