<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\NotificationTemplates\Pages;

use App\Filament\Tenant\Resources\NotificationTemplates\NotificationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNotificationTemplates extends ManageRecords
{
    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Customise a text'))];
    }
}
