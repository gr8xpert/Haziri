<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Announcements;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Announcements with acknowledgement (spec §9 phase 3); new ones are broadcast to the audience. */
class AnnouncementResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Announcement::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::People;

    protected static ?int $navigationSort = 30;

    protected static string $viewPermission = 'announcements.manage';

    protected static string $managePermission = 'announcements.manage';

    public static function form(Schema $schema): Schema
    {
        $ids = fn () => fn (?array $state) => array_map('intval', $state ?? []);

        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(200)->columnSpanFull(),
            Textarea::make('body')->required()->rows(5)->columnSpanFull(),
            Select::make('audience.branch_ids')->label('Branches')->multiple()->options(fn () => Branch::pluck('name', 'id'))
                ->placeholder('All branches')->dehydrateStateUsing($ids()),
            Select::make('audience.department_ids')->label('Departments')->multiple()->options(fn () => Department::pluck('name', 'id'))
                ->placeholder('All departments')->dehydrateStateUsing($ids()),
            DateTimePicker::make('publish_at')->seconds(false)->helperText('Blank = now'),
            DateTimePicker::make('expires_at')->seconds(false),
            Toggle::make('requires_ack')->label('Employees must acknowledge'),
            Toggle::make('notify')->label('Send a notification')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('publish_at')->dateTime()->placeholder('immediately'),
                TextColumn::make('expires_at')->dateTime()->placeholder('never'),
                IconColumn::make('requires_ack')->boolean()->label('Ack'),
                TextColumn::make('acks_count')->counts('acks')->label('Acknowledged'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAnnouncements::route('/')];
    }
}
