<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Services\Settings\TenantSettings;

/**
 * Entry point for all notifications (spec §11): picks channels for the event (tenant setting notify.{event},
 * else the defaults in config/notification_templates.php), logs each delivery and queues it.
 */
final class Notifier
{
    public function __construct(private readonly TenantSettings $settings) {}

    /**
     * @param  iterable<Recipient>  $recipients
     * @param  array<string, mixed>  $data
     * @param  string|null  $dedupeKey  time-based events: never send the same key twice to the same recipient/channel
     */
    public function send(string $event, iterable $recipients, array $data, ?string $url = null, ?string $dedupeKey = null): int
    {
        $queued = 0;
        $channels = $this->channelsFor($event);
        $seen = [];
        foreach ($recipients as $to) {
            if (isset($seen[$to->key()])) {
                continue;
            }
            $seen[$to->key()] = true;
            foreach ($channels as $channel) {
                if ($dedupeKey !== null && NotificationLog::query()
                    ->where(['event' => $event, 'channel' => $channel, 'recipient' => $to->key(), 'dedupe_key' => $dedupeKey])->exists()) {
                    continue;
                }
                $log = NotificationLog::create([
                    'event' => $event, 'channel' => $channel, 'recipient' => $to->key(), 'user_id' => $to->userId,
                    'template' => "{$event}.{$channel}.{$to->locale}", 'payload' => $data, 'status' => 'queued', 'dedupe_key' => $dedupeKey,
                ]);
                SendNotification::dispatch($log->id, $to, $channel, $data, $url);
                $queued++;
            }
        }

        return $queued;
    }

    /** @return list<string> */
    public function channelsFor(string $event): array
    {
        $configured = $this->settings->get("notify.{$event}");
        if (is_array($configured)) {
            return array_values($configured);
        }

        return config("notification_templates.{$event}.channels", ['database']);
    }
}
