<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Announcements\Pages;

use App\Actions\Announcements\BroadcastAnnouncement;
use App\Filament\Tenant\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
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
                // Sends now unless it is scheduled for later; hazri:notify:scheduled sends those when they publish.
                ->after(fn (Announcement $record, BroadcastAnnouncement $broadcast) => $broadcast($record)),
        ];
    }
}
