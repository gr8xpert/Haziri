<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Anomalies\Pages;

use App\Filament\Tenant\Resources\Anomalies\AnomalyResource;
use App\Services\Ai\AnomalyDetector;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAnomalies extends ListRecords
{
    protected static string $resource = AnomalyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')->label(__('Scan now'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(function (AnomalyDetector $detector) {
                    $n = $detector->run(now(tenant('timezone'))->toImmutable());
                    Notification::make()->success()->title(__(':n new finding(s)', ['n' => $n]))->send();
                }),
        ];
    }
}
