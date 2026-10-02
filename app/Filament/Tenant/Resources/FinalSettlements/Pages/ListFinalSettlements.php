<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\FinalSettlements\Pages;

use App\Actions\Payroll\FinalSettlementActions;
use App\Filament\Tenant\Resources\FinalSettlements\FinalSettlementResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListFinalSettlements extends ListRecords
{
    protected static string $resource = FinalSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label(__('New settlement'))->icon(Heroicon::OutlinedPlus)
                ->schema([
                    Select::make('employee_id')->label('Employee')->required()->searchable()->live()
                        ->options(fn () => Employee::query()->orderByRaw('left_on is null')->orderBy('code')->get()
                            ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}" . ($e->left_on ? " (left {$e->left_on->format('d M Y')})" : '')]))
                        ->afterStateUpdated(fn ($state, Set $set) => $set('last_working_day', Employee::find($state)?->left_on?->format('Y-m-d'))),
                    DatePicker::make('last_working_day')->required()
                        ->helperText(fn (Get $get) => Employee::find($get('employee_id'))?->left_on ? null : __('This employee is not marked as left yet.')),
                ])
                ->action(fn (array $data, FinalSettlementActions $actions) => FinalSettlementResource::run(
                    fn () => $actions->calculate(Employee::findOrFail($data['employee_id']), substr((string) $data['last_working_day'], 0, 10), auth()->user()),
                    __('Settlement calculated — review it, then finalise'))),
        ];
    }
}
