<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Visitors;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\Visitor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Visitors log with photo, badge and today's list (spec §9 phase 3). */
class VisitorResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = Visitor::class;

    protected static string $feature = 'visitors';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 60;

    protected static string $viewPermission = 'visitors.manage';

    protected static string $managePermission = 'visitors.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('phone')->tel()->maxLength(20),
            TextInput::make('cnic')->label('CNIC')->regex('/^\d{13}$/')->helperText('13 digits, optional'),
            TextInput::make('company')->maxLength(100),
            Select::make('host_employee_id')->label('Visiting')->searchable()
                ->options(fn () => Employee::orderBy('first_name')->get()->mapWithKeys(fn (Employee $e) => [$e->id => $e->fullName()])),
            TextInput::make('purpose')->maxLength(255),
            Select::make('branch_id')->relationship('branch', 'name'),
            FileUpload::make('photo_path')->image()->label('Photo')->disk('local')->directory('visitor-photos')->maxSize(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');

        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('badge_no')->label('Badge'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('company'),
                TextColumn::make('host.first_name')->label('Visiting')->formatStateUsing(fn (Visitor $r) => $r->host?->fullName()),
                TextColumn::make('purpose')->limit(25),
                TextColumn::make('checked_in_at')->dateTime('d M H:i', $tz)->label('In'),
                TextColumn::make('checked_out_at')->dateTime('H:i', $tz)->label('Out')->placeholder('inside'),
            ])
            ->filters([
                Filter::make('today')->default()->query(fn (Builder $query) => $query->where('checked_in_at', '>=', now($tz)->startOfDay()->utc())),
                Filter::make('inside')->label('Still inside')->query(fn (Builder $query) => $query->whereNull('checked_out_at')),
            ])
            ->recordActions([
                Action::make('checkout')->label('Check out')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (Visitor $r) => $r->checked_out_at === null)
                    ->action(fn (Visitor $r) => $r->update(['checked_out_at' => now()])),
                Action::make('badge')->label('Print badge')->icon(Heroicon::OutlinedIdentification)
                    ->url(fn (Visitor $r) => route('tenant.visitor-badge', $r), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVisitors::route('/')];
    }
}
