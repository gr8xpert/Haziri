<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PayItems;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\PayItem;
use App\Services\Attendance\Period\PayCycle;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Bonuses, arrears, fines and other one-off amounts, added to the chosen month's payroll. */
class PayItemResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = PayItem::class;

    protected static string $feature = 'payroll_adjustments';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'bonus / adjustment';

    protected static ?string $pluralModelLabel = 'bonuses & adjustments';

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')->label('Employee')->required()->searchable()
                ->options(fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
            TextInput::make('pay_month')->label('Pay month')->type('month')->required()->default(fn () => self::currentMonth()),
            Select::make('type')->options(['earning' => 'Pay (bonus, arrears, allowance)', 'deduction' => 'Deduct (fine, recovery)'])->default('earning')->required(),
            TextInput::make('label')->required()->maxLength(100)->placeholder('Eid bonus'),
            TextInput::make('amount')->numeric()->integer()->minValue(1)->prefix('Rs')->required(),
            Toggle::make('taxable')->default(true)->inline(false)->helperText('Counts towards income tax (earnings only)'),
            TextInput::make('note')->columnSpanFull()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (PayItem $r) => "{$r->employee?->code} — {$r->employee?->fullName()}")->searchable(['first_name', 'code']),
                TextColumn::make('month')->label('Pay month')->formatStateUsing(fn (PayItem $r) => app(PayCycle::class)->label($r->year, $r->month)),
                TextColumn::make('type')->badge()->color(fn (string $state) => $state === 'earning' ? 'success' : 'danger'),
                TextColumn::make('label'),
                TextColumn::make('amount')->prefix('Rs ')->numeric(),
                IconColumn::make('taxable')->boolean(),
            ])
            ->filters([SelectFilter::make('type')->options(['earning' => 'Pay', 'deduction' => 'Deduct'])])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data) => $data + ['pay_month' => sprintf('%04d-%02d', $data['year'], $data['month'])])
                    ->mutateDataUsing(fn (array $data) => self::splitMonth($data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * The form's "pay_month" (Y-m) → the year / month columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitMonth(array $data): array
    {
        [$year, $month] = array_map('intval', explode('-', (string) ($data['pay_month'] ?? self::currentMonth())));
        unset($data['pay_month']);

        return $data + ['year' => $year, 'month' => $month];
    }

    public static function currentMonth(): string
    {
        [$y, $m] = app(PayCycle::class)->periodFor(now(tenant('timezone'))->format('Y-m-d'));

        return sprintf('%04d-%02d', $y, $m);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePayItems::route('/')];
    }
}
