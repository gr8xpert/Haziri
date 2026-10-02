<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\AttendanceExemptions;

use App\Enums\ExemptionType;
use App\Enums\ShiftOverrideScope;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\AttendanceExemption;
use App\Models\Branch;
use App\Models\Department;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Company-wide exemptions for one date: excuse lates (traffic, rain) or the whole day (strike). */
class AttendanceExemptionResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = AttendanceExemption::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = 'exemption';

    protected static string $viewPermission = 'holidays.manage';

    protected static string $managePermission = 'holidays.manage';

    public static function form(Schema $schema): Schema
    {
        $isCompany = fn (Get $get) => in_array($get('scope'), [ShiftOverrideScope::Tenant, 'tenant', null], true);

        return $schema->columns(2)->components([
            DatePicker::make('date')->required()->maxDate(now()->addMonth()),
            Select::make('type')->options(ExemptionType::class)->default(ExemptionType::Late)->required(),
            TextInput::make('reason')->required()->maxLength(255)->columnSpanFull()->placeholder('Transport strike / heavy rain'),
            Select::make('scope')->label('Applies to')->required()->live()->default(ShiftOverrideScope::Tenant)
                ->options([
                    ShiftOverrideScope::Tenant->value => 'Whole company',
                    ShiftOverrideScope::Branch->value => 'One branch',
                    ShiftOverrideScope::Department->value => 'One department',
                ]),
            Select::make('scope_id')->label('Which one')
                ->options(fn (Get $get) => match ($get('scope') instanceof ShiftOverrideScope ? $get('scope') : ShiftOverrideScope::tryFrom((string) $get('scope'))) {
                    ShiftOverrideScope::Branch => Branch::pluck('name', 'id'),
                    ShiftOverrideScope::Department => Department::pluck('name', 'id'),
                    default => [],
                })
                ->hidden($isCompany)
                ->required(fn (Get $get) => ! $isCompany($get)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('D, d M Y')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('reason')->searchable()->wrap(),
                TextColumn::make('scope')->label('Applies to')->formatStateUsing(fn (AttendanceExemption $r) => match ($r->scope) {
                    ShiftOverrideScope::Branch => Branch::find($r->scope_id)?->name,
                    ShiftOverrideScope::Department => Department::find($r->scope_id)?->name,
                    default => __('Whole company'),
                }),
                TextColumn::make('created_at')->label('Created on')->date()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('type')->options(ExemptionType::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAttendanceExemptions::route('/')];
    }
}
