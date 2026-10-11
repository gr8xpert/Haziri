<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Grades;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\LeaveEntitlement;
use App\Support\CodeFromName;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Employee grades (Officer, Worker, Grade A…); leave entitlements can depend on them. */
class GradeResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Grade::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 35;

    protected static string $viewPermission = 'organisation.manage';

    protected static string $managePermission = 'organisation.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            CodeFromName::nameInput(Grade::class, 20)->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true)->helperText('Filled in from the name; you can change it.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription(fn (Grade $record) => static::deleteWarning($record)),
            ]);
    }

    /** Leave rules for the grade are deleted with it (cascade), so say how many before the user confirms. */
    public static function deleteWarning(Grade $grade): string
    {
        $rules = LeaveEntitlement::query()->where('grade_id', $grade->id)->count();
        $staff = Employee::query()->where('grade_id', $grade->id)->count();

        return trim(($rules > 0
            ? trans_choice('{1} Its :count leave entitlement on your leave types is deleted too.|[2,*] Its :count leave entitlements on your leave types are deleted too.', $rules)
                . ' ' . __('Employees in this grade then get the normal quota of each leave type.')
            : '') . ' ' . ($staff > 0 ? trans_choice('{1} :count employee loses this grade.|[2,*] :count employees lose this grade.', $staff) : '')
            . ' ' . __('This cannot be undone.'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGrades::route('/')];
    }
}
