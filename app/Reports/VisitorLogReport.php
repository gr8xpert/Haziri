<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Visitor;
use Carbon\CarbonImmutable;

/** R16 — Visitor log (daily / monthly). */
final class VisitorLogReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Visitor log');
    }

    public function filterSchema(): array
    {
        return array_slice(parent::filterSchema(), 0, 2);
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return ['badge' => 'Badge', 'name' => 'Name', 'phone' => 'Phone', 'company' => 'Company', 'host' => 'Visiting',
            'purpose' => 'Purpose', 'in' => 'In', 'out' => 'Out', 'minutes' => 'Minutes'];
    }

    public function rows(array $filters): array
    {
        $tz = $this->tz();
        $from = CarbonImmutable::parse($filters['from'], $tz)->utc();
        $to = CarbonImmutable::parse($filters['to'], $tz)->endOfDay()->utc();

        return Visitor::query()->with('host')->whereBetween('checked_in_at', [$from, $to])->orderBy('checked_in_at')->get()
            ->map(fn (Visitor $v) => [
                'badge' => $v->badge_no, 'name' => $v->name, 'phone' => $v->phone, 'company' => $v->company,
                'host' => $v->host?->fullName(), 'purpose' => $v->purpose,
                'in' => $v->checked_in_at->setTimezone($tz)->format('d M H:i'),
                'out' => $v->checked_out_at?->setTimezone($tz)->format('H:i'),
                'minutes' => $v->checked_out_at ? (int) $v->checked_in_at->diffInMinutes($v->checked_out_at) : null,
            ])->all();
    }
}
