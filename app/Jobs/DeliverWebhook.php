<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\Webhooks;
use App\Support\Security\PublicUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * POST one signed webhook (spec §13.3). Headers: X-Hazri-Event, X-Hazri-Delivery, X-Hazri-Timestamp,
 * X-Hazri-Signature (sha256=hex), also sent as X-Webhook-*. SSRF guard on every attempt, no redirects, 10 s timeout, 5 tries with backoff.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $deliveryId) {}

    /** Out of tries (also after a timeout or connection error): mark it failed and keep the reason for the Deliveries view. */
    public function failed(?Throwable $e): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if ($delivery !== null && $delivery->status !== 'delivered') {
            $delivery->update(['status' => 'failed', 'response_body' => $delivery->response_body ?? mb_substr((string) $e?->getMessage(), 0, 1000)]);
        }
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);
        if ($delivery === null || $delivery->status === 'delivered' || ! $delivery->endpoint?->is_active) {
            return;
        }
        $delivery->increment('attempts');

        if (($problem = PublicUrl::check($delivery->endpoint->url)) !== null) {
            $delivery->update(['status' => 'failed', 'response_body' => "URL rejected: {$problem}"]);

            return; // never retried: configuration problem
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = time();
        $signed = [
            'Event' => $delivery->event,
            'Delivery' => (string) $delivery->payload['id'],
            'Timestamp' => (string) $ts,
            'Signature' => 'sha256=' . Webhooks::signature((string) $delivery->endpoint->secret, $ts, $body),
        ];
        $headers = ['Content-Type' => 'application/json', 'User-Agent' => 'Hazri-Webhooks/1'];
        foreach ($signed as $name => $value) {
            $headers["X-Hazri-{$name}"] = $value; // original names, kept for existing integrations
            $headers["X-Webhook-{$name}"] = $value; // brand-neutral names shown to white-label clients
        }
        $response = Http::timeout(10)->withoutRedirecting()->withHeaders($headers)->withBody($body, 'application/json')->post($delivery->endpoint->url);

        $delivery->update([
            'response_status' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 1000),
            'status' => $response->successful() ? 'delivered' : ($this->attempts() >= $this->tries ? 'failed' : 'pending'),
        ]);
        if (! $response->successful()) {
            throw new RuntimeException("Webhook {$delivery->id} got HTTP {$response->status()}"); // queue retries with backoff
        }
    }
}
