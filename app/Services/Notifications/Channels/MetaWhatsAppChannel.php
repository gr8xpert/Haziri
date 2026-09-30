<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp through Meta's Cloud API (spec §16 default): POST https://graph.facebook.com/{version}/{phone_number_id}/messages.
 * Business-initiated messages must use an approved template: when the Hazri template has a `provider_template`,
 * its {{ placeholders }} become the template's body parameters in order. Otherwise a plain text message is sent
 * (only delivered inside a 24-hour customer-service window).
 *
 * Tenant settings: whatsapp_provider = "meta", whatsapp_meta_token (encrypted), whatsapp_meta_phone_number_id,
 * whatsapp_meta_api_version (default v21.0).
 */
final class MetaWhatsAppChannel implements NotificationChannel
{
    public function __construct(private readonly TenantSettings $settings) {}

    public function name(): string
    {
        return 'whatsapp';
    }

    public function canReach(Recipient $to): bool
    {
        return filled($to->phone);
    }

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult
    {
        $token = $this->settings->secret('whatsapp_meta_token');
        $numberId = (string) $this->settings->get('whatsapp_meta_phone_number_id');
        if (! $token || $numberId === '') {
            return DeliveryResult::failed('WhatsApp is not configured (token / phone number id missing).');
        }
        $version = (string) $this->settings->get('whatsapp_meta_api_version', 'v21.0');
        $phone = \App\Services\SelfService\OtpService::normalise((string) $to->phone);

        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $phone];
        if ($message->providerTemplate) {
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $message->providerTemplate,
                'language' => ['code' => $to->locale === 'ur' ? 'ur' : 'en'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $this->orderedParameters($message)),
                ]],
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => false, 'body' => trim(($message->subject ? "*{$message->subject}*\n" : '') . $message->body)];
        }

        $response = Http::withToken($token)->acceptJson()->timeout(20)
            ->post("https://graph.facebook.com/{$version}/{$numberId}/messages", $payload);

        if ($response->successful()) {
            return DeliveryResult::sent($response->json('messages.0.id'));
        }

        return DeliveryResult::failed('Meta ' . $response->status() . ': ' . ($response->json('error.message') ?? $response->body()));
    }

    /** Values for the template's {{1}}, {{2}}… in the order the placeholders appear in the Hazri template body. */
    private function orderedParameters(RenderedMessage $message): array
    {
        $body = (string) config("notification_templates.{$message->event}.en.1", '');
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $m);

        return array_map(fn (string $key) => $message->data[$key] ?? '', array_values(array_unique($m[1])));
    }
}
