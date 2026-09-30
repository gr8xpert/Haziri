<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;

/** In-app: Filament database notification (bell icon in the panel). */
final class DatabaseChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'database';
    }

    public function canReach(Recipient $to): bool
    {
        return $to->userId !== null;
    }

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult
    {
        $user = User::find($to->userId);
        if ($user === null) {
            return DeliveryResult::failed('user not found');
        }
        $n = FilamentNotification::make()->title($message->subject ?? $message->event)->body($message->body);
        if ($message->url) {
            $n->actions([Action::make('open')->url($message->url)]);
        }
        $n->sendToDatabase($user);

        return DeliveryResult::sent();
    }
}
