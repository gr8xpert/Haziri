<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Webhooks\Pages;

use App\Filament\Tenant\Resources\Webhooks\WebhookEndpointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageWebhookEndpoints extends ManageRecords
{
    protected static string $resource = WebhookEndpointResource::class;

    protected function getHeaderActions(): array
    {
        // The signing secret is generated server-side, never taken from the form.
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => array_merge($data, ['secret' => 'whsec_' . Str::random(40)]))];
    }
}
