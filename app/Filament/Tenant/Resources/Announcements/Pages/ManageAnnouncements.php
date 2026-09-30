<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Announcements\Pages;

use App\Enums\EmployeeStatus;
use App\Filament\Tenant\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Employee;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data) => array_merge($data, ['created_by' => auth()->id()]))
                ->after(function (Announcement $record, Notifier $notifier) {
                    if (! $record->notify) {
                        return;
                    }
                    // Broadcast (spec §11) to everyone in the audience.
                    $recipients = Employee::query()->where('status', '!=', EmployeeStatus::Left)->get()
                        ->filter(fn (Employee $e) => $record->isFor($e))
                        ->map(fn (Employee $e) => Recipient::fromEmployee($e));
                    $notifier->send('broadcast', $recipients, ['title' => $record->title, 'body' => $record->body], url('/me'), "announcement:{$record->id}");
                }),
        ];
    }
}
