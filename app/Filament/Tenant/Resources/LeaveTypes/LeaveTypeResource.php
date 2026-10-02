<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveTypes;

use App\Enums\EmploymentType;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Grade;
use App\Models\LeaveType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeaveTypeResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = LeaveType::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 90;

    protected static string $viewPermission = 'leave.manage_types';

    protected static string $managePermission = 'leave.manage_types';

    public static function form(Schema $schema): Schema
    {
        $n = fn (string $name) => TextInput::make($name)->numeric()->minValue(0);

        return $schema->columns(3)->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('code')->required()->maxLength(10)->unique(ignoreRecord: true)->helperText('Shown in the monthly grid'),
            Toggle::make('is_paid')->default(true)->inline(false),
            $n('annual_quota_days')->required()->default(0)->suffix('days'),
            Select::make('accrual')->options(['yearly_upfront' => 'Yearly, upfront', 'monthly' => 'Monthly'])->default('yearly_upfront')->required(),
            $n('carry_forward_max')->default(0)->suffix('days'),
            $n('max_consecutive_days')->integer()->suffix('days'),
            $n('requires_attachment_after_days')->integer()->suffix('days')->helperText('Blank = never'),
            $n('allow_short_leave_hours')->suffix('hours')->helperText('Blank = no short leave'),
            Toggle::make('allow_half_day')->default(true),
            Toggle::make('sandwich_rule')->helperText('Weekend/holiday between two leave days counts as leave'),
            Toggle::make('counts_weekends')->helperText('Count calendar days instead of working days'),
            CheckboxList::make('applies_to')->options(EmploymentType::class)->columns(3)->columnSpan(2)->helperText('None ticked = everyone'),
            Select::make('gender_restriction')->options(['female' => 'Female only', 'male' => 'Male only'])->placeholder('Everyone'),
            Toggle::make('is_active')->default(true),
            Toggle::make('allow_negative_balance')->label('Allow negative balance')
                ->helperText('Leave can be approved beyond the balance; the overdraft carries into the next leave year'),
            Repeater::make('entitlements')->relationship()->columnSpanFull()->columns(3)->defaultItems(0)
                ->label('Entitlement by grade / category')
                ->helperText('Overrides the yearly quota above. The most specific row wins: grade + category, then grade, then category.')
                ->addActionLabel('Add entitlement')
                ->schema([
                    Select::make('grade_id')->label('Grade')->options(fn () => Grade::pluck('name', 'id'))->placeholder('Any grade'),
                    Select::make('employment_type')->label('Category')->options(EmploymentType::class)->placeholder('Any category'),
                    TextInput::make('days_per_year')->numeric()->minValue(0)->required()->suffix('days / year'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('name'),
                IconColumn::make('is_paid')->boolean(),
                TextColumn::make('annual_quota_days')->suffix(' d')->label('Quota'),
                TextColumn::make('accrual'),
                TextColumn::make('entitlements_count')->counts('entitlements')->label('Grade rules'),
                IconColumn::make('sandwich_rule')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLeaveTypes::route('/')];
    }
}
