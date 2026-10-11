<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Corrections;

use App\Actions\Corrections\DecideCorrection;
use App\Enums\RequestStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\ScopesToTeam;
use App\Filament\Tenant\NavigationGroup;
use App\Models\AttendanceCorrection;
use App\Services\Approvals\ApprovalChain;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CorrectionResource extends Resource
{
    use AuthorizesByPermission, ScopesToTeam;

    protected static ?string $model = AttendanceCorrection::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Corrections';

    protected static ?string $modelLabel = 'correction';

    protected static string $viewPermission = 'corrections.request';

    protected static string $managePermission = 'corrections.request';

    public static function getNavigationBadge(): ?string
    {
        $n = self::scopeToTeam(AttendanceCorrection::query(), 'corrections.approve_final')->where('status', RequestStatus::Pending)->count();

        return $n ? (string) $n : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeToTeam(parent::getEloquentQuery()->with(['employee', 'requester']), 'corrections.approve_final');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (AttendanceCorrection $r) => $r->employee?->fullName()),
                TextColumn::make('type')->badge(),
                // payload is an array, so a payload column would format once per item; build the text from the record instead.
                TextColumn::make('details')->label('Details')->state(fn (AttendanceCorrection $r) => $r->detailsText()),
                TextColumn::make('reason')->limit(30)->tooltip(fn (AttendanceCorrection $r) => $r->reason),
                TextColumn::make('requested_by')->label('Requested by')->state(fn (AttendanceCorrection $r) => $r->requestedByText()),
                TextColumn::make('status')->badge(),
                TextColumn::make('stage')->label('Approval')->state(fn (AttendanceCorrection $r) => $r->stageText())->color('gray'),
            ])
            ->filters([SelectFilter::make('status')->options(RequestStatus::class)->default(RequestStatus::Pending->value)])
            ->recordActions([self::decision('approve', true), self::decision('reject', false)]);
    }

    private static function decision(string $name, bool $approve): Action
    {
        return Action::make($name)
            ->icon($approve ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
            ->color($approve ? 'success' : 'danger')
            ->visible(fn (AttendanceCorrection $r) => $r->status === RequestStatus::Pending
                && app(ApprovalChain::class)->canDecide(auth()->user(), $r->approver_chain ?? [], $r->current_step, $r->employee, 'corrections.approve_final', 'corrections.approve_first'))
            ->schema([Textarea::make('note')->required(! $approve)])
            ->action(function (AttendanceCorrection $record, array $data, DecideCorrection $decide) use ($approve) {
                try {
                    $decided = $decide($record, auth()->user(), $approve, $data['note'] ?? null);
                    $title = match (true) {
                        ! $approve => __('Rejected'),
                        $decided->status === RequestStatus::Approved => __('Approved and applied'),
                        default => __('Approved — waiting for HR'),
                    };
                    Notification::make()->success()->title($title)->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                }
            });
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCorrections::route('/')];
    }
}
