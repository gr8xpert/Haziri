<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\Ai\SetupAssistant;
use App\Services\Settings\TenantSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Spec §12.6: paste the attendance policy, review the proposed settings, apply the ones you tick. */
class SetupAssistantPage extends Page
{
    protected string $view = 'filament.tenant.pages.setup-assistant';

    protected static ?string $slug = 'setup-assistant';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Setup assistant';

    public string $policy = '';

    /** @var list<array<string, mixed>> */
    public array $diff = [];

    /** @var array<int, bool> */
    public array $accept = [];

    public ?string $error = null;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('ai_reports') && (bool) auth()->user()?->can('settings.manage');
    }

    public function propose(SetupAssistant $assistant): void
    {
        $this->validate(['policy' => ['required', 'string', 'min:40', 'max:20000']], [
            'policy.required' => __('Paste your policy text first.'),
            'policy.min' => __('The policy is too short. Paste at least :min characters.'),
        ]);
        $r = $assistant->propose($this->policy, auth()->id());
        $this->error = $r['ok'] ? null : $r['error'];
        $this->diff = $r['diff'] ?? [];
        $this->accept = array_fill(0, count($this->diff), true);
    }

    public function apply(TenantSettings $settings): void
    {
        $shift = Shift::query()->where('is_default', true)->first();
        $applied = 0;
        foreach ($this->diff as $i => $change) {
            if (empty($this->accept[$i])) {
                continue;
            }
            if ($change['target'] === 'setting' && in_array($change['key'], SetupAssistant::SETTING_KEYS, true)) {
                Setting::query()->updateOrCreate(['key' => $change['key']], ['value' => $change['proposed']]);
                $applied++;
            } elseif ($change['target'] === 'shift' && $shift && in_array($change['key'], SetupAssistant::SHIFT_KEYS, true)) {
                $shift->update([$change['key'] => $change['proposed']]);
                $applied++;
            }
        }
        $settings->flush();
        $this->diff = [];
        Notification::make()->success()->title(__(':n change(s) applied', ['n' => $applied]))->send();
    }
}
