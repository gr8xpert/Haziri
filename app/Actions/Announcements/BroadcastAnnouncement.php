<?php

declare(strict_types=1);

namespace App\Actions\Announcements;

use App\Enums\EmployeeStatus;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Settings\TenantSettings;

/**
 * Broadcast an announcement to its audience (spec §11) once it is published: right away when it is saved without a
 * future publish time, otherwise by hazri:notify:scheduled after publish_at. Sent at most once (notified_at).
 */
final class BroadcastAnnouncement
{
    public function __construct(private readonly Notifier $notifier) {}

    /** @return int notifications queued (0 when not due, not wanted, expired or already sent) */
    public function __invoke(Announcement $announcement): int
    {
        if (! $announcement->notify || $announcement->notified_at !== null) {
            return 0;
        }
        if ($announcement->publish_at?->isFuture() || ($announcement->expires_at !== null && ! $announcement->expires_at->isFuture())) {
            return 0;
        }

        $recipients = Employee::query()->where('status', '!=', EmployeeStatus::Left)->get()
            ->filter(fn (Employee $e) => $announcement->isFor($e))
            ->map(fn (Employee $e) => Recipient::fromEmployee($e));
        $announcement->forceFill(['notified_at' => now()])->saveQuietly();

        return $this->notifier->send('broadcast', $recipients, ['title' => $announcement->title, 'body' => $announcement->body],
            url('/me'), "announcement:{$announcement->id}", $announcement->channels ?: null);
    }

    /**
     * Channels an announcement can go out on: in-app and email always; WhatsApp / SMS once a provider is set up.
     *
     * @return array<string, string> channel => label
     */
    public static function availableChannels(): array
    {
        $settings = app(TenantSettings::class);
        $configured = fn (string $channel) => (string) ($settings->get("{$channel}_provider") ?? config("hazri.notifications.{$channel}_provider", 'log')) !== 'log';

        return array_filter([
            'database' => __('In the app'),
            'mail' => __('Email'),
            'whatsapp' => $settings->featureEnabled('whatsapp') && $configured('whatsapp') ? __('WhatsApp') : null,
            'sms' => $configured('sms') ? __('SMS') : null,
        ]);
    }

    /**
     * How many employees in the audience each channel can reach (app = has an active login).
     *
     * @param  array{branch_ids?: list<int|string>, department_ids?: list<int|string>}  $audience
     * @return array{employees: int, app: int, email: int, phone: int}
     */
    public static function reach(array $audience): array
    {
        $probe = new Announcement(['audience' => $audience]);
        $employees = Employee::query()->where('status', '!=', EmployeeStatus::Left)->get()->filter(fn (Employee $e) => $probe->isFor($e));
        $withLogin = User::query()->where('is_active', true)->whereIn('employee_id', $employees->pluck('id'))->get()->keyBy('employee_id');

        return [
            'employees' => $employees->count(),
            'app' => $withLogin->count(),
            'email' => $employees->filter(fn (Employee $e) => filled($e->email) || filled($withLogin->get($e->id)?->email))->count(),
            'phone' => $employees->filter(fn (Employee $e) => filled($e->phone))->count(),
        ];
    }

    /** Broadcast every announcement whose publish time has come. */
    public function due(): int
    {
        return Announcement::query()->where('notify', true)->whereNull('notified_at')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->get()->sum(fn (Announcement $a) => $this($a));
    }
}
