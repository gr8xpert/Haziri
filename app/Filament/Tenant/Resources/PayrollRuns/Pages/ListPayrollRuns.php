<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PayrollRuns\Pages;

use App\Actions\Payroll\PayrollActions;
use App\Enums\PeriodStatus;
use App\Filament\Tenant\Resources\PayrollRuns\PayrollRunResource;
use App\Models\AttendancePeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label(__('Run payroll'))->icon(Heroicon::OutlinedTableCells)
                ->schema([
                    Select::make('period_id')->label(__('Locked month'))->required()
                        ->options(fn () => AttendancePeriod::where('status', PeriodStatus::Locked)->orderByDesc('year')->orderByDesc('month')->get()
                            ->mapWithKeys(fn (AttendancePeriod $p) => [$p->id => $p->label()])),
                ])
                ->action(fn (array $data, PayrollActions $a) => PayrollRunResource::run(
                    fn () => $a->generate(AttendancePeriod::findOrFail($data['period_id']), auth()->user()), 'Payroll calculated (draft)')),
        ];
    }
}
