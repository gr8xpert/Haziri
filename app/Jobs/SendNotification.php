<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** One delivery (spec §11): render → quiet hours → send → log. Retried ×3 with backoff. */
final class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $logId,
        public readonly Recipient $to,
        public readonly string $channel,
        public readonly array $data,
        public readonly ?string $url = null,
    ) {}

    public function handle(ChannelRegistry $channels, TemplateRenderer $renderer, TenantSettings $settings): void
    {
        $log = NotificationLog::query()->find($this->logId);
        if ($log === null || in_array($log->status, ['sent', 'logged'], true)) {
            return;
        }

        $driver = $channels->resolve($this->channel);
        $event = $log->event;
        $message = $renderer->render($event, $this->channel, $this->to->locale, $this->data, $this->url);
        if ($driver === null || $message === null || ! $driver->canReach($this->to)) {
            $log->update(['status' => 'skipped', 'error' => $driver === null ? 'channel disabled' : ($message === null ? 'template disabled' : 'recipient unreachable')]);

            return;
        }

        // WhatsApp / SMS respect quiet hours: postpone until they end.
        if (in_array($this->channel, ['whatsapp', 'sms'], true) && ($delay = $this->quietDelay($settings)) > 0) {
            $this->release($delay);

            return;
        }

        $log->increment('attempts');
        $result = $driver->send($this->to, $message);
        $log->update([
            'status' => $result->ok ? ($result->loggedOnly ? 'logged' : 'sent') : 'failed',
            'provider_ref' => $result->providerRef,
            'error' => $result->error,
        ]);
        if (! $result->ok && $this->attempts() < $this->tries) {
            throw new RuntimeException("Notification {$this->logId} failed: {$result->error}"); // let the queue retry
        }
    }

    /** Seconds until quiet hours end, or 0 if we're outside them. Setting quiet_hours = ["22:00", "08:00"]. */
    private function quietDelay(TenantSettings $settings): int
    {
        $window = $settings->get('quiet_hours');
        if (! is_array($window) || count($window) !== 2) {
            return 0;
        }
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $now = CarbonImmutable::now($tz);
        $start = CarbonImmutable::parse($now->format('Y-m-d') . ' ' . $window[0], $tz);
        $end = CarbonImmutable::parse($now->format('Y-m-d') . ' ' . $window[1], $tz);

        if ($start->lessThan($end)) {
            return $now->between($start, $end) ? (int) $now->diffInSeconds($end) : 0;
        }
        // Overnight window (22:00 → 08:00)
        if ($now->greaterThanOrEqualTo($start)) {
            return (int) $now->diffInSeconds($end->addDay());
        }

        return $now->lessThan($end) ? (int) $now->diffInSeconds($end) : 0;
    }
}
