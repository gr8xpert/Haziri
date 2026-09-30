<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Enums\RequestStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Models\AttendanceMarker;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** "Who's off this week" (spec §9 phase 2): approved leave, OD/WFH and holidays per day. */
class LeaveCalendar extends Page
{
    protected string $view = 'filament.tenant.pages.leave-calendar';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Leave;

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Who\'s off';

    public string $weekStart = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('leave.request');
    }

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::now(tenant('timezone'))->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d');
    }

    public function shift(int $weeks): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($weeks)->format('Y-m-d');
    }

    /** @return array<string, list<array{name: string, what: string, tone: string}>> date => entries */
    public function getDays(): array
    {
        $from = CarbonImmutable::parse($this->weekStart);
        $to = $from->addDays(6);
        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $days[$d->format('Y-m-d')] = [];
        }

        $leaves = LeaveRequest::query()->with(['employee', 'leaveType'])->where('status', RequestStatus::Approved)
            ->where('from_date', '<=', $to->format('Y-m-d'))->where('to_date', '>=', $from->format('Y-m-d'))->get();
        foreach ($leaves as $l) {
            foreach (CarbonPeriod::create(max($l->from_date->format('Y-m-d'), $from->format('Y-m-d')), min($l->to_date->format('Y-m-d'), $to->format('Y-m-d'))) as $d) {
                $days[$d->format('Y-m-d')][] = ['name' => $l->employee?->fullName() ?? '?',
                    'what' => ($l->leaveType?->code ?? 'LV') . ($l->day_part->isHalf() ? ' ½' : ''), 'tone' => 'warning'];
            }
        }
        foreach (AttendanceMarker::query()->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get() as $m) {
            $days[$m->date->format('Y-m-d')][] = ['name' => Employee::find($m->employee_id)?->fullName() ?? '?',
                'what' => $m->status->code(), 'tone' => 'info'];
        }
        foreach (Holiday::query()->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get() as $h) {
            $days[$h->date->format('Y-m-d')][] = ['name' => $h->name, 'what' => 'H', 'tone' => 'success'];
        }

        return $days;
    }
}
