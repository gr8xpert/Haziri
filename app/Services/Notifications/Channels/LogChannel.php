<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use Illuminate\Support\Facades\Log;

/** Development / "provider not chosen yet" driver (spec §2): writes the message to the log. */
final class LogChannel implements NotificationChannel
{
    public function __construct(private readonly string $as = 'log') {}

    public function name(): string
    {
        return $this->as;
    }

    public function canReach(Recipient $to): bool
    {
        return in_array($this->as, ['whatsapp', 'sms'], true) ? filled($to->phone) : true;
    }

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult
    {
        Log::info("[notify:{$this->as}] {$message->event} → {$to->name}", [
            'tenant' => tenant('id'), 'email' => $to->email, 'phone' => $to->phone, 'subject' => $message->subject, 'body' => $message->body,
        ]);

        return DeliveryResult::sent('log');
    }
}
