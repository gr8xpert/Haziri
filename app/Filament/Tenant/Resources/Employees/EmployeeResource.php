<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\WorkMode;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Branch;
use App\Models\Contractor;
use App\Models\Employee;
use App\Models\SeparationReason;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Settings\TenantSettings;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

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

    /**
     * Main fields on top (enough for an attendance-only company), the rest folded into "More details" — open on edit.
     * Fields that belong to a switched-off feature are hidden.
     */
    public static function form(Schema $schema): Schema
    {
        $on = fn (string $feature): bool => app(TenantSettings::class)->featureEnabled($feature);
        $usesPhotos = fn (): bool => $on('kiosk') || $on('mobile_punch');

        return $schema->columns(3)->components([
            Section::make(__('Main details'))
                ->description(new HtmlString('<span class="hz-required-note">' . e(__('Fields marked')) . ' <b>*</b> ' . e(__('are required.')) . '</span>'))
                ->columnSpan(fn () => $usesPhotos() ? 2 : 'full')->columns(2)->schema([
                    TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
                    TextInput::make('device_pin')->label('Machine PIN')->maxLength(20)->unique(ignoreRecord: true)
                        ->helperText('The user ID enrolled on the fingerprint/face machine'),
                    TextInput::make('first_name')->required()->maxLength(100),
                    TextInput::make('last_name')->maxLength(100),
                    Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                    Select::make('designation_id')->relationship('designation', 'name')->searchable()->preload(),
                    DatePicker::make('joined_on')->required(),
                    Select::make('shift_id')->label('Shift')->options(fn () => Shift::query()->orderBy('name')->pluck('name', 'id'))
                        ->placeholder(fn () => Shift::query()->where('is_default', true)->value('name') ?? __('Company default'))
                        ->helperText('Blank = the company default shift. Change later with "Assign shift".')
                        ->dehydrated(false)->visibleOn('create'),
                    Select::make('branch_id')->label('Location / branch')->relationship('branch', 'name')->searchable()->preload()
                        ->visible(fn () => Branch::query()->count() > 1),
                    Select::make('employment_type')->options(EmploymentType::class)->default(EmploymentType::Permanent)->required(),
                    Select::make('status')->options(EmployeeStatus::class)->default(EmployeeStatus::Active)->required(),
                ]),
            Section::make(__('Photo'))->columnSpan(1)->visible($usesPhotos)->schema([
                FileUpload::make('photo_path')->image()->avatar()->directory('employee-photos')->label(''),
            ]),
            Section::make(__('More details'))->columnSpanFull()
                ->description(__('Personal, contact, job, attendance and payroll details. All optional.'))
                ->collapsible()->collapsed(fn (string $operation) => $operation === 'create')
                ->schema([
                    Section::make(__('Personal'))->columns(3)->schema([
                        TextInput::make('father_name')->label("Father's / husband's name")->maxLength(255),
                        TextInput::make('cnic')->label('CNIC')->regex('/^\d{13}$/')->helperText('13 digits, no dashes')
                            ->unique(ignoreRecord: true)->dehydrateStateUsing(fn (?string $s) => $s ? preg_replace('/\D/', '', $s) : null),
                        Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
                        DatePicker::make('dob')->label('Date of birth')->maxDate(now()),
                        TextInput::make('city')->maxLength(100),
                        TextInput::make('payroll_id')->label('Payroll ID')->maxLength(30)->visible(fn () => $on('payroll_lite')),
                        Textarea::make('address')->rows(2)->maxLength(1000)->columnSpanFull(),
                    ]),
                    Section::make(__('Contact'))->columns(2)->schema([
                        TextInput::make('phone')->label('Cell phone')->tel()->maxLength(20),
                        TextInput::make('home_phone')->tel()->maxLength(20),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('emergency_contact')->maxLength(255)->placeholder('Name, relation, phone'),
                    ]),
                    Section::make(__('Employment'))->columns(3)->schema([
                        Select::make('grade_id')->relationship('grade', 'name')->searchable()->preload(),
                        Select::make('manager_employee_id')->relationship('manager', 'first_name')->searchable()->label('Manager'),
                        Select::make('contractor_id')->relationship('contractor', 'name')->searchable()->preload()
                            ->helperText('For contract staff supplied by a contractor')
                            ->visible(fn (?Employee $record) => $record?->contractor_id !== null || Contractor::query()->exists()),
                        DatePicker::make('probation_ends_on'),
                        DatePicker::make('contract_ends_on'),
                        DatePicker::make('left_on')->afterOrEqual('joined_on')->live(),
                        Select::make('separation_reason_id')->label('Leaving reason')->relationship('separationReason', 'name')
                            ->visible(fn (Get $get) => filled($get('left_on'))),
                        TextInput::make('office_phone')->tel()->maxLength(20),
                        TextInput::make('office_ext')->label('Office ext.')->maxLength(10),
                        TextInput::make('official_mobile')->tel()->maxLength(20),
                    ]),
                    Section::make(__('Attendance'))->columns(3)->schema([
                        TextInput::make('card_no')->maxLength(30),
                        TextInput::make('kiosk_pin')->password()->revealable()->regex('/^\d{4}$/')->label('Kiosk PIN (4 digits)')
                            ->dehydrated(fn (?string $s) => filled($s))->visible(fn () => $on('kiosk'))
                            ->helperText(fn (?Employee $record) => filled($record?->kiosk_pin) ? __('A PIN is set. Leave empty to keep it.') : null),
                        Toggle::make('remove_kiosk_pin')->label('Remove kiosk PIN')->helperText('The kiosk then asks only for the Machine PIN')
                            ->dehydrated(false)->visible(fn (?Employee $record) => $on('kiosk') && filled($record?->kiosk_pin))
                            ->saveRelationshipsUsing(fn (Employee $record, ?bool $state) => $state ? $record->forceFill(['kiosk_pin' => null])->save() : null),
                        Select::make('work_mode')->options(WorkMode::class)->default(WorkMode::Office)->required(),
                        Toggle::make('mobile_punch_allowed')->label('Can punch from phone')->default(true)
                            ->helperText('Off = must punch on the shop machine or kiosk')->visible(fn () => $on('mobile_punch')),
                        Toggle::make('overtime_eligible'),
                        Toggle::make('late_deduction_exempt')->helperText('No late roll-up or late adjustment for this employee'),
                        Toggle::make('leave_credit')->label('Earns leave')->default(true)->helperText('Off = no leave accrues (e.g. daily wagers)'),
                    ]),
                    Section::make(__('Payroll & statutory'))->columns(3)
                        ->visible(fn () => collect(['payroll_tax', 'payroll_social', 'payroll_pf'])->contains($on))
                        ->schema([
                            TextInput::make('ntn')->label('NTN')->maxLength(20),
                            Toggle::make('tax_exempt')->label('No income tax')->inline(false),
                            Toggle::make('pf_member')->label('Provident fund member')->inline(false),
                            Toggle::make('eobi_registered')->label('EOBI registered')->inline(false)->live(),
                            TextInput::make('eobi_no')->label('EOBI number')->maxLength(30),
                            Toggle::make('social_security_registered')->label('PESSI / SESSI registered')->inline(false),
                            TextInput::make('social_security_no')->label('Social security number')->maxLength(30),
                        ]),
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
                TextColumn::make('grade.name')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payroll_id')->label('Payroll ID')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_type')->badge()->toggleable(),
                TextColumn::make('status')->badge()->color(fn (EmployeeStatus $state) => match ($state) {
                    EmployeeStatus::Active => 'success', EmployeeStatus::OnLeave => 'warning', EmployeeStatus::Left => 'gray',
                }),
                TextColumn::make('joined_on')->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->relationship('branch', 'name')->label('Branch'),
                SelectFilter::make('department_id')->relationship('department', 'name')->label('Department'),
                SelectFilter::make('designation_id')->relationship('designation', 'name')->label('Designation')->searchable(),
                SelectFilter::make('grade_id')->relationship('grade', 'name')->label('Grade'),
                SelectFilter::make('contractor_id')->relationship('contractor', 'name')->label('Contractor'),
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
                            DatePicker::make('effective_to')->afterOrEqual('effective_from')->helperText('Blank = until changed'),
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
                        ->schema([
                            DatePicker::make('left_on')->required()->default(now()),
                            Select::make('separation_reason_id')->label('Reason')
                                ->options(fn () => SeparationReason::where('is_active', true)->pluck('name', 'id')),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'left_on' => $data['left_on'], 'status' => EmployeeStatus::Left,
                            'separation_reason_id' => $data['separation_reason_id'] ?? null,
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
