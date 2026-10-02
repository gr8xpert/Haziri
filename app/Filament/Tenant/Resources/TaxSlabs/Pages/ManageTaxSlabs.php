<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TaxSlabs\Pages;

use App\Filament\Tenant\Resources\TaxSlabs\TaxSlabResource;
use App\Models\TaxSlab;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManageTaxSlabs extends ManageRecords
{
    protected static string $resource = TaxSlabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyYear')->label(__('Copy slabs to a new tax year'))->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                ->schema([
                    TextInput::make('from')->label('Copy from')->numeric()->integer()->required()->default(fn () => TaxSlab::max('tax_year')),
                    TextInput::make('to')->label('Into')->numeric()->integer()->required()->default(fn () => (int) TaxSlab::max('tax_year') + 1),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        TaxSlab::where('tax_year', $data['to'])->delete();
                        foreach (TaxSlab::where('tax_year', $data['from'])->get() as $slab) {
                            TaxSlab::create(['tax_year' => $data['to']] + $slab->only(['from_amount', 'to_amount', 'fixed_tax', 'rate_percent']));
                        }
                    });
                    Notification::make()->success()->title(__('Copied — now update the rates from the budget'))->send();
                }),
            CreateAction::make(),
        ];
    }
}
