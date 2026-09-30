<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Services\Notifications\Channels\DatabaseChannel;
use App\Services\Notifications\Channels\LogChannel;
use App\Services\Notifications\Channels\MailChannel;
use App\Services\Settings\TenantSettings;

/**
 * Resolves a channel name to its driver. WhatsApp / SMS use the provider configured per tenant
 * (settings whatsapp_provider / sms_provider, falling back to HAZRI env defaults); "log" until one is chosen (spec §16).
 */
final class ChannelRegistry
{
    /** @var array<string, class-string<NotificationChannel>> provider name => driver class */
    private array $providers = [];

    /** @param class-string<NotificationChannel> $driver */
    public function extend(string $channel, string $provider, string $driver): void
    {
        $this->providers["{$channel}:{$provider}"] = $driver;
    }

    public function resolve(string $channel): ?NotificationChannel
    {
        return match ($channel) {
            'mail' => new MailChannel,
            'database' => new DatabaseChannel,
            'log' => new LogChannel,
            'whatsapp', 'sms' => $this->provider($channel),
            default => null,
        };
    }

    private function provider(string $channel): ?NotificationChannel
    {
        // Singleton: read the *current* tenant's settings on every call.
        $settings = app(TenantSettings::class);
        if ($channel === 'whatsapp' && ! $settings->featureEnabled('whatsapp')) {
            return null;
        }
        $provider = (string) ($settings->get("{$channel}_provider") ?? config("hazri.notifications.{$channel}_provider", 'log'));
        $driver = $this->providers["{$channel}:{$provider}"] ?? null;

        return $driver !== null ? app($driver) : new LogChannel($channel);
    }
}
