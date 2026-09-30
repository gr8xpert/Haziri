<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ApiTokens\Pages;

use App\Filament\Tenant\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')->label(__('New token'))->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    TextInput::make('name')->required()->maxLength(100)->placeholder('Payroll software'),
                    CheckboxList::make('abilities')->options(ApiToken::ABILITIES)->default(['read'])->required(),
                ])
                ->action(function (array $data) {
                    ['plain' => $plain] = ApiToken::issue($data['name'], $data['abilities'], auth()->id());
                    Notification::make()->success()->persistent()->title(__('Token created — copy it now, it is shown only once'))
                        ->body($plain)->send();
                }),
        ];
    }
}
