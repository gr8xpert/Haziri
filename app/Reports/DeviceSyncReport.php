<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Device;
use App\Models\Punch;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** R15 — Device sync: punches per day per device, last sync, days with no data (gaps). */
final class DeviceSyncReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Device sync');
    }

    public function filterSchema(): array
    {
        return array_slice(parent::filterSchema(), 0, 2);
    }

    public function columns(array $filters): array
    {
        return ['device' => 'Device', 'serial' => 'Serial', 'type' => 'Type', 'status' => 'Status', 'last_sync' => 'Last sync',
            'punches' => 'Punches', 'per_day' => 'Avg / day', 'gaps' => 'Days without data'];
    }

    public function rows(array $filters): array
    {
        $dates = collect(CarbonPeriod::create($filters['from'], $filters['to']))->map(fn ($d) => $d->format('Y-m-d'));
        $counts = Punch::query()->whereNotNull('device_id')->whereBetween('local_date', [$filters['from'], $filters['to']])
            ->select('device_id', 'local_date', DB::raw('count(*) as n'))->groupBy('device_id', 'local_date')->get()
            ->groupBy('device_id');

        return Device::query()->orderBy('name')->get()->map(function (Device $d) use ($counts, $dates) {
            $byDay = $counts->get($d->id, collect())->mapWithKeys(fn ($r) => [substr((string) $r->local_date, 0, 10) => (int) $r->n]);
            $total = $byDay->sum();

            return [
                'device' => $d->name, 'serial' => $d->serial_number, 'type' => $d->type->value, 'status' => $d->status,
                'last_sync' => $d->last_sync_at?->diffForHumans() ?? 'never', 'punches' => $total,
                'per_day' => $dates->count() ? round($total / $dates->count(), 1) : 0,
                'gaps' => $dates->reject(fn ($date) => isset($byDay[$date]))->count(),
            ];
        })->all();
    }
}
