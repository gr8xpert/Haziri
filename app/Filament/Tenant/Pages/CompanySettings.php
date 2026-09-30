<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
            Section::make(__('Punches'))->columns(3)->schema([
                $int('dedupe_window_sec', 'Double-tap window', 'sec'),
                Toggle::make('trust_device_direction')->label('Trust In/Out keys on machines')->inline(false),
                $int('punch_search_window_before_min', 'Punches count from … before shift', 'min'),
                $int('punch_search_window_after_min', '… until after shift end', 'min'),
                Toggle::make('store_punch_photos')->inline(false),
                $int('photo_retention_days', 'Keep photos', 'days'),
                $int('geofence_default_radius_m', 'Default geofence', 'm'),
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
                    RecomputeAttendanceRange::dispatchChunked(Employee::pluck('id')->all(), now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d'));
                    Notification::make()->success()->title(__('Recompute queued'))->send();
                }),
        ];
    }
}
