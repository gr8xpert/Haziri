<?php

declare(strict_types=1);

namespace App\Actions\Announcements;

use App\Enums\EmployeeStatus;
use App\Models\Announcement;
use App\Models\Employee;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;

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
            url('/me'), "announcement:{$announcement->id}");
    }

    /** Broadcast every announcement whose publish time has come. */
    public function due(): int
    {
        return Announcement::query()->where('notify', true)->whereNull('notified_at')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->get()->sum(fn (Announcement $a) => $this($a));
    }
}
