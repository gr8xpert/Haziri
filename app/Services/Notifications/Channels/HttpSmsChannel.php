<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannel;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use App\Services\SelfService\OtpService;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\Http;

/**
 * Generic HTTP SMS gateway (spec §16: Pakistani provider, abstracted). Works with most local gateways, which take
 * a GET/POST with the number, message and credentials as parameters.
 *
 * Tenant settings: sms_provider = "http", sms_http_url, sms_http_method (GET|POST), sms_http_params
 * (JSON object; values may contain {to}, {message}, {sender}, {api_key}), sms_http_api_key (encrypted),
 * sms_http_sender, sms_http_success_contains (optional text that must appear in a successful response).
 */
final class HttpSmsChannel implements NotificationChannel
{
    public function __construct(private readonly TenantSettings $settings) {}

    public function name(): string
    {
        return 'sms';
    }

    public function canReach(Recipient $to): bool
    {
        return filled($to->phone);
    }

    public function send(Recipient $to, RenderedMessage $message): DeliveryResult
    {
        $url = (string) $this->settings->get('sms_http_url');
        if ($url === '') {
            return DeliveryResult::failed('SMS gateway URL is not configured.');
        }
        if (($problem = \App\Support\Security\PublicUrl::check($url)) !== null) {
            return DeliveryResult::failed("SMS gateway URL rejected: {$problem}");
        }
        $vars = [
            '{to}' => OtpService::normalise((string) $to->phone),
            '{message}' => mb_substr(trim(($message->subject ? $message->subject . ': ' : '') . $message->body), 0, 459),
            '{sender}' => (string) $this->settings->get('sms_http_sender', 'Hazri'),
            '{api_key}' => (string) $this->settings->secret('sms_http_api_key'),
        ];
        $params = array_map(fn ($v) => strtr((string) $v, $vars), (array) $this->settings->get('sms_http_params', ['to' => '{to}', 'message' => '{message}']));

        $request = Http::timeout(20)->withoutRedirecting(); // a redirect could bounce to an internal address
        $response = strtoupper((string) $this->settings->get('sms_http_method', 'POST')) === 'GET'
            ? $request->get($url, $params)
            : $request->asForm()->post($url, $params);

        $mustContain = (string) $this->settings->get('sms_http_success_contains', '');
        if ($response->successful() && ($mustContain === '' || str_contains($response->body(), $mustContain))) {
            return DeliveryResult::sent(mb_substr($response->body(), 0, 100));
        }

        return DeliveryResult::failed('SMS gateway ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300));
    }
}
