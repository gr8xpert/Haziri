<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/** Email through the app mailer (Plesk SMTP in production). */
final class MailChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'mail';
    }

    public function canReach(Recipient $to): bool
    {
        return filled($to->email);
    }

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult
    {
        $body = $message->body . ($message->url ? "\n\n" . $message->url : '');
        Mail::raw($body, function (Message $m) use ($to, $message) {
            $m->to($to->email, $to->name)->subject($message->subject ?? config('app.name'));
            $settings = app(\App\Services\Settings\TenantSettings::class);
            $from = ($settings->featureEnabled('white_label') ? $settings->get('email_from_name') : null) ?: tenant('company_name');
            if ($from) {
                $m->from(config('mail.from.address'), $from . ' via Hazri');
            }
        });

        return DeliveryResult::sent();
    }
}
