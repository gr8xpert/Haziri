<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Tenant rule parameters (spec §7.4), approval chains, notification timing. Feature flags are set centrally. */
class CompanySettings extends Page
{
    protected string $view = 'filament.tenant.pages.company-settings';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Company settings';

    /** Keys editable on this page. */
    private const KEYS = [
        'dedupe_window_sec', 'trust_device_direction', 'lates_per_absent', 'half_day_counts_as', 'absent_deduction_days',
        'unpaid_leave_deduction_days', 'overtime_enabled', 'overtime_rounding_min', 'punch_search_window_before_min',
        'punch_search_window_after_min', 'auto_absent_after_days', 'store_punch_photos', 'photo_retention_days',
        'geofence_default_radius_m', 'allow_future_manual_punch', 'employee_can_see_others', 'daily_wage_paid_holidays',
        'leave_approval_chain', 'correction_approval_chain', 'daily_summary_at', 'absent_alert_at', 'quiet_hours', 'notify_employee_late',
        'mobile_max_accuracy_m', 'mobile_require_selfie', 'face_check_photo_punches', 'pay_cycle_start_day', 'leave_year_start_month', 'late_adjustment_mode', 'late_adjustment_leave_type',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(TenantSettings $settings): void
    {
        $all = $settings->all();
        $this->form->fill(array_intersect_key($all, array_flip(self::KEYS)) + [
            'quiet_from' => $all['quiet_hours'][0] ?? '22:00',
            'quiet_to' => $all['quiet_hours'][1] ?? '08:00',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $int = fn (string $k, string $label, ?string $suffix = null) => TextInput::make($k)->label($label)->numeric()->integer()->minValue(0)->suffix($suffix);
        $chain = fn (string $k, string $label) => Select::make($k)->label($label)->options([
            'manager,hr' => 'Manager, then HR', 'hr' => 'HR only', 'manager' => 'Manager only',
        ])->formatStateUsing(fn ($state) => is_array($state) ? implode(',', $state) : $state)
            ->dehydrateStateUsing(fn ($state) => explode(',', (string) $state));

        return $schema->statePath('data')->components([
            Section::make(__('Pay month & leave year'))->columns(3)->schema([
                TextInput::make('pay_cycle_start_day')->label('Attendance month starts on day')->numeric()->integer()->minValue(1)->maxValue(28)
                    ->required()->helperText('1 = calendar month. 21 = the June month runs 21 May → 20 June.'),
                Select::make('leave_year_start_month')->label('Leave year starts in')->required()
                    ->options(collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all())
                    ->helperText('October = leave year Oct → Sep (financial year).'),
            ]),
            Section::make(__('Punches'))->columns(3)->schema([
                $int('dedupe_window_sec', 'Double-tap window', 'sec'),
                Toggle::make('trust_device_direction')->label('Trust In/Out keys on machines')->inline(false),
                $int('punch_search_window_before_min', 'Punches count from … before shift', 'min'),
                $int('punch_search_window_after_min', '… until after shift end', 'min'),
                Toggle::make('store_punch_photos')->inline(false),
                $int('photo_retention_days', 'Keep photos', 'days'),
                $int('geofence_default_radius_m', 'Default geofence', 'm'),
                $int('mobile_max_accuracy_m', 'Reject mobile GPS worse than (0 = off)', 'm'),
                Toggle::make('mobile_require_selfie')->label('Selfie required for mobile punch')->inline(false),
                Toggle::make('face_check_photo_punches')->label('Face check on PIN, QR card and phone punches')->inline(false)
                    ->helperText('The photo is compared with the face enrolled under Face enrolment. Wrong face, no face or not enrolled → the punch waits in Punch approvals. Kiosks must be locked to approved tablets. On phones it stops casual buddy punching but a tech-savvy user could fool it, so keep checking the photos.')
                    ->visible(fn () => app(TenantSettings::class)->featureEnabled('kiosk_face')),
                Toggle::make('allow_future_manual_punch')->inline(false),
                Toggle::make('employee_can_see_others')->label('Employees can see colleagues')->inline(false),
            ]),
            Section::make(__('Deductions & overtime'))->columns(3)->schema([
                $int('lates_per_absent', 'Lates that make one absent (0 = off)'),
                TextInput::make('half_day_counts_as')->numeric()->minValue(0)->maxValue(1)->label('Half day deducts'),
                TextInput::make('absent_deduction_days')->numeric()->minValue(0)->label('Absent deducts (days)'),
                TextInput::make('unpaid_leave_deduction_days')->numeric()->minValue(0)->label('Unpaid leave deducts (days)'),
                $int('auto_absent_after_days', 'Mark absent after (days)'),
                Toggle::make('daily_wage_paid_holidays')->label('Pay daily-wage staff for holidays')->inline(false),
                Select::make('late_adjustment_mode')->label('Late roll-ups & half days are deducted from')->required()
                    ->options(['salary' => 'Salary', 'leave' => 'Leave balance (then salary when it runs out)'])->live(),
                Select::make('late_adjustment_leave_type')->label('Leave type charged')
                    ->options(fn () => LeaveType::query()->where('is_active', true)->where('is_paid', true)->pluck('name', 'code')->all())
                    ->visible(fn (Get $get) => $get('late_adjustment_mode') === 'leave')
                    ->required(fn (Get $get) => $get('late_adjustment_mode') === 'leave'),
                Toggle::make('overtime_enabled')->inline(false),
                $int('overtime_rounding_min', 'Round overtime down to', 'min'),
            ]),
            Section::make(__('Approvals & notifications'))->columns(3)->schema([
                $chain('leave_approval_chain', 'Leave approval'),
                $chain('correction_approval_chain', 'Correction approval'),
                Toggle::make('notify_employee_late')->label('Tell managers about late arrivals')->inline(false),
                TimePicker::make('absent_alert_at')->seconds(false)->label('"Not in yet" alert at'),
                TimePicker::make('daily_summary_at')->seconds(false)->label('Daily summary to HR at'),
                TimePicker::make('quiet_from')->seconds(false)->label('WhatsApp/SMS quiet from'),
                TimePicker::make('quiet_to')->seconds(false)->label('… until'),
            ]),
        ]);
    }

    public function save(TenantSettings $settings): void
    {
        $state = $this->form->getState();
        $state['quiet_hours'] = [substr((string) $state['quiet_from'], 0, 5), substr((string) $state['quiet_to'], 0, 5)];
        foreach (['absent_alert_at', 'daily_summary_at'] as $k) {
            $state[$k] = substr((string) $state[$k], 0, 5);
        }
        foreach (array_intersect_key($state, array_flip(self::KEYS)) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $settings->flush();
        Notification::make()->success()->title(__('Settings saved'))
            ->body(__('Rule changes apply to new calculations. Use "Recompute this month" to re-apply them to existing days.'))->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recompute')->label(__('Recompute this month'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => auth()->user()->can('attendance.recompute'))
                ->requiresConfirmation()
                ->action(function () {
                    $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');
                    [$y, $m] = app(PayCycle::class)->periodFor($today);
                    RecomputeAttendanceRange::dispatchChunked(Employee::pluck('id')->all(), app(PayCycle::class)->dates($y, $m)[0], $today);
                    Notification::make()->success()->title(__('Recompute queued'))->send();
                }),
        ];
    }
}
