<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;

/** Finds the tenant's template (event, channel, locale) with sensible fallbacks and fills {{ placeholders }}. */
final class TemplateRenderer
{
    /** @param array<string, mixed> $data */
    public function render(string $event, string $channel, string $locale, array $data, ?string $url = null): ?RenderedMessage
    {
        $template = $this->find($event, $channel, $locale);
        if ($template === null || ! $template->enabled) {
            return null;
        }

        return new RenderedMessage(
            event: $event,
            subject: $template->subject !== null ? self::fill($template->subject, $data) : null,
            body: self::fill($template->body, $data),
            data: $data,
            providerTemplate: $template->provider_template,
            url: $url,
        );
    }

    private function find(string $event, string $channel, string $locale): ?NotificationTemplate
    {
        // Tenant overrides first: exact channel, then the shared "mail" text; the requested locale, then English.
        foreach ([$locale, 'en'] as $loc) {
            foreach ([$channel, 'mail'] as $ch) {
                $t = NotificationTemplate::query()->where(['event' => $event, 'channel' => $ch, 'locale' => $loc])->first();
                if ($t !== null) {
                    return $t;
                }
            }
        }

        $defaults = config("notification_templates.{$event}");
        if ($defaults === null) {
            return null;
        }
        [$subject, $body] = $defaults[$locale] ?? $defaults['en'];

        return new NotificationTemplate(['event' => $event, 'channel' => $channel, 'locale' => $locale, 'subject' => $subject, 'body' => $body, 'enabled' => true]);
    }

    /** @param array<string, mixed> $data */
    public static function fill(string $text, array $data): string
    {
        return trim((string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn ($m) => (string) ($data[$m[1]] ?? ''), $text));
    }
}
