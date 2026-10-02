<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Payroll rates and rules: day divisor, income tax, EOBI / social security, provident fund, gratuity. */
class PayrollSettings extends Page
{
    protected string $view = 'filament.tenant.pages.company-settings';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Payroll rules';

    protected static ?string $slug = 'payroll-rules';

    private const KEYS = [
        'payroll_day_divisor', 'tax_year_start_month', 'tax_surcharge_threshold', 'tax_surcharge_percent',
        'eobi_wage_base', 'eobi_employee_percent', 'eobi_employer_percent', 'social_security_wage_base', 'social_security_percent',
        'pf_employee_percent', 'pf_employer_percent', 'gratuity_basis', 'gratuity_days_per_year', 'gratuity_min_years', 'gratuity_part_year_months',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_lite') && (bool) auth()->user()?->can('payroll.manage');
    }

    public function mount(TenantSettings $settings): void
    {
        $all = $settings->all();
        $values = array_intersect_key($all, array_flip(self::KEYS));
        $values['payroll_day_divisor'] = (string) ($all['payroll_day_divisor'] ?? 'calendar');
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $on = fn (string $feature) => fn () => app(TenantSettings::class)->featureEnabled($feature);
        $rs = fn (string $k, string $label) => TextInput::make($k)->label($label)->numeric()->integer()->minValue(0)->prefix('Rs')->required();
        $pct = fn (string $k, string $label) => TextInput::make($k)->label($label)->numeric()->minValue(0)->maxValue(100)->suffix('%')->required();

        return $schema->statePath('data')->components([
            Section::make(__('Salary'))->columns(3)->schema([
                Select::make('payroll_day_divisor')->label('Per-day rate = monthly salary ÷')->required()
                    ->options(['calendar' => 'Days in the pay month', '30' => '30', '26' => '26 (working days)']),
            ]),
            Section::make(__('Income tax'))->columns(3)->visible($on('payroll_tax'))
                ->description(__('Slabs are under Payroll → Tax slabs.'))->schema([
                    Select::make('tax_year_start_month')->label('Tax year starts in')->required()
                        ->options(collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all()),
                    $rs('tax_surcharge_threshold', 'Surcharge on annual income above'),
                    $pct('tax_surcharge_percent', 'Surcharge (% of the tax)'),
                ]),
            Section::make(__('EOBI & social security'))->columns(3)->visible($on('payroll_social'))
                ->description(__('Contributions are worked out on the wage base (normally the minimum wage), only for employees marked as registered.'))->schema([
                    $rs('eobi_wage_base', 'EOBI wage base'),
                    $pct('eobi_employee_percent', 'EOBI — employee'),
                    $pct('eobi_employer_percent', 'EOBI — company'),
                    $rs('social_security_wage_base', 'Social security (PESSI / SESSI) wage base'),
                    $pct('social_security_percent', 'Social security — company'),
                ]),
            Section::make(__('Provident fund'))->columns(3)->visible($on('payroll_pf'))
                ->description(__('Percent of basic salary, for employees marked as PF members.'))->schema([
                    $pct('pf_employee_percent', 'Employee share'),
                    $pct('pf_employer_percent', 'Company share'),
                ]),
            Section::make(__('Gratuity'))->columns(4)->visible($on('payroll_gratuity'))->schema([
                Select::make('gratuity_basis')->label('Based on')->options(['basic' => 'Last basic salary', 'gross' => 'Last gross salary'])->required(),
                TextInput::make('gratuity_days_per_year')->label("Days' pay per year")->numeric()->integer()->minValue(0)->required(),
                TextInput::make('gratuity_min_years')->label('Minimum years of service')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('gratuity_part_year_months')->label('Part year counts from (months)')->numeric()->integer()->minValue(1)->maxValue(12)->required(),
            ]),
        ]);
    }

    public function save(TenantSettings $settings): void
    {
        foreach (array_intersect_key($this->form->getState(), array_flip(self::KEYS)) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_numeric($value) && $key !== 'payroll_day_divisor' ? $value + 0 : $value]);
        }
        $settings->flush();
        Notification::make()->success()->title(__('Payroll rules saved'))->body(__('They apply the next time payroll is calculated.'))->send();
    }
}
