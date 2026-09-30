<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;

/** Spec §11: one implementation per channel/provider (mail, in-app, log, WhatsApp, SMS). */
interface NotificationChannel
{
    public function name(): string;

    /** Can this recipient be reached on this channel at all (e.g. has a phone number)? */
    public function canReach(Recipient $to): bool;

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult;
}
