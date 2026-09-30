<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Settings\TenantSettings;

/** Fan an event out to every active endpoint subscribed to it (spec §13.3). */
final class Webhooks
{
    /** @param array<string, mixed> $data */
    public static function dispatch(string $event, array $data): void
    {
        if (! tenancy()->initialized || ! app(TenantSettings::class)->featureEnabled('public_api')) {
            return;
        }
        $endpoints = WebhookEndpoint::query()->where('is_active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => in_array($event, $e->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => ['id' => (string) \Illuminate\Support\Str::uuid(), 'event' => $event, 'tenant' => tenant('id'),
                    'created_at' => now()->toIso8601String(), 'data' => $data],
            ]);
            DeliverWebhook::dispatch($delivery->id);
        }
    }

    /** Header value receivers verify: hex HMAC-SHA256 of "{timestamp}.{body}" with the endpoint secret. */
    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
