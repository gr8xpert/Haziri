<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveRequests;

use App\Actions\Leave\CancelLeaveRequest;
use App\Actions\Leave\DecideLeaveRequest;
use App\Enums\RequestStatus;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Concerns\ScopesToTeam;
use App\Filament\Tenant\NavigationGroup;
use App\Models\LeaveRequest;
use App\Services\Ai\AttachmentReader;
use App\Services\Approvals\ApprovalChain;
use App\Services\Settings\TenantSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LeaveRequestResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature, ScopesToTeam;

    protected static string $feature = 'leave';

    protected static ?string $model = LeaveRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Leave requests';

    protected static string $viewPermission = 'leave.request';

    protected static string $managePermission = 'leave.request';

    public static function getNavigationBadge(): ?string
    {
        $n = self::scopeToTeam(LeaveRequest::query(), 'leave.approve_final')->where('status', RequestStatus::Pending)->count();

        return $n ? (string) $n : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeToTeam(parent::getEloquentQuery()->with(['employee', 'leaveType'])->withCount('workedDays'), 'leave.approve_final');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('employee.code')->label('Code')->searchable(),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (LeaveRequest $r) => $r->employee?->fullName()),
                TextColumn::make('leaveType.name')->label('Type')->badge()
                    ->description(fn (LeaveRequest $r) => $r->worked_days_count > 0
                        ? trans_choice('Punched on :n day of this leave|Punched on :n days of this leave', $r->worked_days_count, ['n' => $r->worked_days_count]) : null),
                TextColumn::make('from_date')->date('d M')->label('From')->sortable(),
                TextColumn::make('to_date')->date('d M Y')->label('To'),
                TextColumn::make('day_part')->badge()->color('gray'),
                TextColumn::make('days'),
                TextColumn::make('status')->badge()->color(fn (RequestStatus $state) => match ($state) {
                    RequestStatus::Pending => 'warning', RequestStatus::Approved => 'success',
                    RequestStatus::Rejected => 'danger', RequestStatus::Cancelled => 'gray',
                }),
                TextColumn::make('current_step')->label('Waiting for')
                    ->formatStateUsing(fn (LeaveRequest $r) => $r->status !== RequestStatus::Pending ? '' : match ($r->approver_chain[$r->current_step]['step'] ?? 'hr') {
                        'manager' => __('Manager'), default => __('HR'),
                    }),
                TextColumn::make('reason')->limit(30)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(RequestStatus::class)->default(RequestStatus::Pending->value),
                SelectFilter::make('leave_type_id')->relationship('leaveType', 'name')->label('Type'),
                Filter::make('worked')->label(__('Punched on a leave day'))->query(fn (Builder $query) => $query->has('workedDays')),
            ])
            ->recordActions([
                Action::make('readAttachment')->label('Read attachment (AI)')->icon(Heroicon::OutlinedSparkles)->color('gray')
                    ->visible(fn (LeaveRequest $r) => $r->attachment_path !== null
                        && app(TenantSettings::class)->featureEnabled('ai_reports')
                        && auth()->user()->can('leave.approve_final'))
                    ->action(function (LeaveRequest $record, AttachmentReader $reader) {
                        $r = $reader->read($record, auth()->id());
                        if (! $r['ok']) {
                            Notification::make()->danger()->title($r['error'])->send();

                            return;
                        }
                        $d = $r['data'];
                        Notification::make()->persistent()->color($d['matches_request'] ? 'success' : 'warning')
                            ->title($d['matches_request'] ? __('Certificate dates match the request') : __('Check the dates'))
                            ->body(sprintf('%s → %s
%s, %s
%s
(%s)', $d['from_date'] ?? '?', $d['to_date'] ?? '?',
                                $d['doctor'] ?? '?', $d['clinic'] ?? '?', $d['summary'] ?? '', __('AI suggestion — please verify')))
                            ->send();
                    }),
                self::decision('approve', true),
                self::decision('reject', false),
                Action::make('cancel')->icon(Heroicon::OutlinedArrowPath)->color('gray')->requiresConfirmation()
                    ->visible(fn (LeaveRequest $r) => in_array($r->status, [RequestStatus::Pending, RequestStatus::Approved], true))
                    ->schema([Textarea::make('reason')->label('Why?')])
                    ->action(function (LeaveRequest $record, array $data, CancelLeaveRequest $cancel) {
                        self::run(fn () => $cancel($record, auth()->user(), $data['reason'] ?? null), __('Leave cancelled'));
                    }),
            ]);
    }

    private static function decision(string $name, bool $approve): Action
    {
        return Action::make($name)
            ->icon($approve ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
            ->color($approve ? 'success' : 'danger')
            ->visible(fn (LeaveRequest $r) => $r->status === RequestStatus::Pending
                && app(ApprovalChain::class)->canDecide(auth()->user(), $r->approver_chain ?? [], $r->current_step, $r->employee, 'leave.approve_final', 'leave.approve_first'))
            ->schema([Textarea::make('note')->label($approve ? 'Note (optional)' : 'Reason')->required(! $approve)])
            ->action(function (LeaveRequest $record, array $data, DecideLeaveRequest $decide) use ($approve) {
                self::run(fn () => $decide($record, auth()->user(), $approve, $data['note'] ?? null), $approve ? __('Approved') : __('Rejected'));
            });
    }

    private static function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            Notification::make()->success()->title($ok)->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLeaveRequests::route('/')];
    }
}
