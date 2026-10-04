<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Punch;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/** "Show me all punches for PIN 47 this week" (spec §9 phase 4): punch-photo gallery via signed URLs. */
class PunchPhotos extends Page
{
    protected string $view = 'filament.tenant.pages.punch-photos';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Attendance;

    protected static ?int $navigationSort = 26;

    protected static ?string $title = 'Punch photos';

    public string $who = '';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('attendance.view_all');
    }

    public function mount(): void
    {
        $now = CarbonImmutable::now(tenant('timezone'));
        $this->from = $now->startOfWeek()->format('Y-m-d');
        $this->to = $now->format('Y-m-d');
    }

    /** @return Collection<int, array{url: string, name: string, time: string, source: string, flag: bool}> */
    public function getPhotos(): Collection
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $who = trim($this->who);

        return Punch::query()->with('employee')->whereNotNull('photo_path')
            ->whereBetween('local_date', [$this->from ?: '1970-01-01', $this->to ?: '2999-12-31'])
            ->when($who !== '', fn ($q) => $q->where(fn ($w) => $w->where('device_pin', $who)
                ->orWhereHas('employee', fn ($e) => $e->where('code', $who)->orWhere('first_name', 'like', "%{$who}%"))))
            ->latest('punched_at')->limit(120)->get()
            ->map(fn (Punch $p) => [
                'url' => URL::temporarySignedRoute('tenant.punch-photo', now()->addMinutes(30), ['punch' => $p->id]),
                'name' => $p->employee?->fullName() ?? ('PIN ' . $p->device_pin),
                'time' => $p->punched_at->setTimezone($tz)->format('D d M, H:i'),
                'source' => $p->source->getLabel(),
                'flag' => (bool) ($p->meta['geofence_fail'] ?? false),
            ]);
    }
}
