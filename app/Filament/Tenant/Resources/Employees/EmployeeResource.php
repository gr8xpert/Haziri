<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\WorkMode;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Employee::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::People;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static string $viewPermission = 'employees.view';

    protected static string $managePermission = 'employees.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Personal'))->columnSpan(2)->columns(2)->schema([
                TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('first_name')->required()->maxLength(100),
                TextInput::make('last_name')->maxLength(100),
                TextInput::make('cnic')->label('CNIC')->regex('/^\d{13}$/')->helperText('13 digits, no dashes')
                    ->unique(ignoreRecord: true)->dehydrateStateUsing(fn (?string $s) => $s ? preg_replace('/\D/', '', $s) : null),
                TextInput::make('phone')->tel()->maxLength(20),
                TextInput::make('email')->email()->maxLength(255),
                Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
                DatePicker::make('dob')->label('Date of birth')->maxDate(now()),
            ]),
            Section::make(__('Photo'))->columnSpan(1)->schema([
                FileUpload::make('photo_path')->image()->avatar()->directory('employee-photos')->label(''),
            ]),
            Section::make(__('Employment'))->columnSpan(2)->columns(2)->schema([
                Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload(),
                Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                Select::make('designation_id')->relationship('designation', 'name')->searchable()->preload(),
                Select::make('manager_employee_id')->relationship('manager', 'first_name')->searchable()->label('Manager'),
                Select::make('employment_type')->options(EmploymentType::class)->default(EmploymentType::Permanent)->required(),
                Select::make('status')->options(EmployeeStatus::class)->default(EmployeeStatus::Active)->required(),
                DatePicker::make('joined_on')->required(),
                DatePicker::make('probation_ends_on'),
                DatePicker::make('contract_ends_on'),
                DatePicker::make('left_on')->afterOrEqual('joined_on'),
            ]),
            Section::make(__('Attendance'))->columnSpan(1)->schema([
                TextInput::make('device_pin')->label('Machine PIN')->maxLength(20)->unique(ignoreRecord: true)
                    ->helperText('The user ID enrolled on the fingerprint/face machine'),
                TextInput::make('card_no')->maxLength(30),
                TextInput::make('kiosk_pin')->password()->revealable()->regex('/^\d{4}$/')->label('Kiosk PIN (4 digits)')
                    ->dehydrated(fn (?string $s) => filled($s)),
                Select::make('work_mode')->options(WorkMode::class)->default(WorkMode::Office)->required(),
                Toggle::make('overtime_eligible'),
                Toggle::make('late_deduction_exempt'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('first_name')->label('Name')->searchable(['first_name', 'last_name'])->sortable()
                    ->formatStateUsing(fn (Employee $record) => $record->fullName()),
                TextColumn::make('device_pin')->label('PIN')->searchable()->placeholder('—'),
                TextColumn::make('branch.name')->sortable()->toggleable(),
                TextColumn::make('department.name')->sortable()->toggleable(),
                TextColumn::make('designation.name')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_type')->badge()->toggleable(),
                TextColumn::make('status')->badge()->color(fn (EmployeeStatus $state) => match ($state) {
                    EmployeeStatus::Active => 'success', EmployeeStatus::OnLeave => 'warning', EmployeeStatus::Left => 'gray',
                }),
                TextColumn::make('joined_on')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->relationship('branch', 'name')->label('Branch'),
                SelectFilter::make('department_id')->relationship('department', 'name')->label('Department'),
                SelectFilter::make('status')->options(EmployeeStatus::class)->default(EmployeeStatus::Active->value),
                SelectFilter::make('employment_type')->options(EmploymentType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignShift')->label('Assign shift')->icon(Heroicon::OutlinedClock)
                        ->schema([
                            Select::make('shift_id')->options(fn () => Shift::pluck('name', 'id'))->required(),
                            DatePicker::make('effective_from')->required()->default(now()),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $employee) {
                                ShiftAssignment::create(['employee_id' => $employee->id] + $data);
                            }
                            Notification::make()->success()->title(__(':n employees assigned', ['n' => $records->count()]))->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignBranch')->label('Move to branch')->icon(Heroicon::OutlinedBuildingOffice2)
                        ->schema([Select::make('branch_id')->options(fn () => Branch::pluck('name', 'id'))->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update($data))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')->label('Mark as left')->icon(Heroicon::OutlinedArrowPath)->color('danger')
                        ->requiresConfirmation()
                        ->schema([DatePicker::make('left_on')->required()->default(now())])
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'left_on' => $data['left_on'], 'status' => EmployeeStatus::Left,
                        ]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
            'import' => Pages\ImportEmployees::route('/import'),
        ];
    }
}
