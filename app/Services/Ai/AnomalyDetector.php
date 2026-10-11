<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\EmployeeStatus;
use App\Enums\PunchStatus;
use App\Models\Anomaly;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\SalaryStructure;
use App\Services\SelfService\GeoFence;
use App\Support\Duration;
use Carbon\CarbonImmutable;

/**
 * Rule-based anomaly detection (spec §12.3). The rules decide; the AI only phrases the one-line explanation
 * (a template is used when AI is off or over budget). Each finding is deduped by a stable key.
 */
final class AnomalyDetector
{
    public const BUDDY_SECONDS = 5;

    public const BUDDY_MIN_DAYS = 3;

    public function __construct(private readonly AiGateway $ai) {}

    public function run(CarbonImmutable $today): int
    {
        $found = [...$this->buddyPunching($today), ...$this->ghostEmployees($today), ...$this->patternShifts($today), ...$this->impossibleSequences($today),
            ...$this->impossibleTravel($today)];
        $new = [];
        foreach ($found as $f) {
            $anomaly = Anomaly::query()->firstOrCreate(['dedupe_key' => $f['key']], [
                'type' => $f['type'], 'employee_id' => $f['employee_id'], 'detected_on' => $today->format('Y-m-d'),
                'evidence' => $f['evidence'], 'explanation' => $f['template'],
            ]);
            if ($anomaly->wasRecentlyCreated) {
                $new[] = $anomaly;
            }
        }
        $this->explain($new);

        return count($new);
    }

    /** Two PINs punching within 5 s on the same device on ≥ 3 days in the last 30 days. */
    private function buddyPunching(CarbonImmutable $today): array
    {
        $punches = Punch::query()->where('status', PunchStatus::Valid)->whereNotNull('device_id')->whereNotNull('employee_id')
            ->where('local_date', '>=', $today->subDays(30)->format('Y-m-d'))->orderBy('device_id')->orderBy('punched_at')
            ->get(['employee_id', 'device_id', 'punched_at', 'local_date']);

        $pairs = [];
        $prev = null;
        foreach ($punches as $p) {
            if ($prev && $prev->device_id === $p->device_id && $prev->employee_id !== $p->employee_id
                && $p->punched_at->getTimestamp() - $prev->punched_at->getTimestamp() <= self::BUDDY_SECONDS) {
                $ids = [min($prev->employee_id, $p->employee_id), max($prev->employee_id, $p->employee_id)];
                $pairs[implode('-', $ids)][$p->local_date->format('Y-m-d')] = true;
            }
            $prev = $p;
        }

        $out = [];
        foreach ($pairs as $key => $days) {
            if (count($days) < self::BUDDY_MIN_DAYS) {
                continue;
            }
            [$a, $b] = array_map('intval', explode('-', $key));
            $names = Employee::whereIn('id', [$a, $b])->get()->map->fullName()->implode(' and ');
            $out[] = ['type' => 'buddy_punching', 'employee_id' => $a, 'key' => "buddy:{$key}:" . $today->format('Y-m'),
                'evidence' => ['employees' => [$a, $b], 'names' => $names, 'days' => array_keys($days)],
                'template' => "{$names} punched within " . self::BUDDY_SECONDS . ' seconds of each other on the same machine on ' . count($days) . ' days.'];
        }

        return $out;
    }

    /** Active, salaried, joined > 30 days ago, and no punches at all in 30 days. */
    private function ghostEmployees(CarbonImmutable $today): array
    {
        $from = $today->subDays(30)->format('Y-m-d');
        $salaried = SalaryStructure::query()->distinct()->pluck('employee_id');
        $active = Employee::query()->where('status', EmployeeStatus::Active)->where('joined_on', '<', $from)->whereIn('id', $salaried)->get();
        $punched = Punch::query()->where('local_date', '>=', $from)->whereIn('employee_id', $active->pluck('id'))->distinct()->pluck('employee_id')->flip();

        return $active->reject(fn (Employee $e) => isset($punched[$e->id]))->map(fn (Employee $e) => [
            'type' => 'ghost_employee', 'employee_id' => $e->id, 'key' => "ghost:{$e->id}:" . $today->format('Y-m'),
            'evidence' => ['since' => $from],
            'template' => "{$e->fullName()} is active and on payroll but has no punches in the last 30 days.",
        ])->values()->all();
    }

    /**
     * Average arrival moved by more than 45 minutes vs the previous 4 weeks, on the same shift: a move to another
     * shift (roster, evening or night week) changes arrival times on purpose, so days are only compared within a shift.
     */
    private function patternShifts(CarbonImmutable $today): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $days = AttendanceDay::query()->whereNotNull('first_in')->where('date', '>=', $today->subDays(35)->format('Y-m-d'))->get()
            ->groupBy(fn (AttendanceDay $d): string => $d->employee_id . ':' . ($d->shift_id ?? 0));
        $cut = $today->subDays(7)->format('Y-m-d');
        $out = [];
        $flagged = [];
        foreach ($days as $group => $list) {
            $employeeId = (int) explode(':', (string) $group)[0];
            if (isset($flagged[$employeeId])) {
                continue;
            }
            $minutes = fn ($c) => $c->map(fn ($d) => $d->first_in->setTimezone($tz)->hour * 60 + $d->first_in->setTimezone($tz)->minute);
            $recent = $minutes($list->filter(fn ($d) => $d->date->format('Y-m-d') >= $cut));
            $before = $minutes($list->filter(fn ($d) => $d->date->format('Y-m-d') < $cut));
            if ($recent->count() < 3 || $before->count() < 8) {
                continue;
            }
            $shift = (int) round($recent->avg() - $before->avg());
            if (abs($shift) > 45) {
                $flagged[$employeeId] = true;
                $name = Employee::find($employeeId)?->fullName();
                $out[] = ['type' => 'pattern_shift', 'employee_id' => $employeeId, 'key' => "shift:{$employeeId}:" . $today->format('o-W'),
                    'evidence' => ['change_min' => $shift, 'recent_avg' => (int) $recent->avg(), 'before_avg' => (int) $before->avg()],
                    'template' => "{$name}'s average arrival moved " . Duration::human($shift) . ' ' . ($shift > 0 ? 'later' : 'earlier') . ' this week compared with the previous four weeks.'];
            }
        }

        return $out;
    }

    /** Punches at two different branches' devices within 20 minutes. */
    private function impossibleSequences(CarbonImmutable $today): array
    {
        $punches = Punch::query()->with('device')->where('status', PunchStatus::Valid)->whereNotNull('device_id')
            ->where('local_date', '>=', $today->subDays(7)->format('Y-m-d'))->orderBy('employee_id')->orderBy('punched_at')->get();
        $out = [];
        $prev = null;
        foreach ($punches as $p) {
            if ($prev && $prev->employee_id === $p->employee_id && $prev->device?->branch_id && $p->device?->branch_id
                && $prev->device->branch_id !== $p->device->branch_id
                && $p->punched_at->getTimestamp() - $prev->punched_at->getTimestamp() <= 1200) {
                $name = Employee::find($p->employee_id)?->fullName();
                $out[] = ['type' => 'impossible_sequence', 'employee_id' => $p->employee_id, 'key' => "impossible:{$prev->id}:{$p->id}",
                    'evidence' => ['punches' => [$prev->id, $p->id], 'devices' => [$prev->device->name, $p->device->name]],
                    'template' => "{$name} punched at {$prev->device->name} and {$p->device->name} (different branches) within 20 minutes."];
            }
            $prev = $p;
        }

        return $out;
    }

    /** Faster than a car could go between two consecutive punch locations (phone GPS or the device's branch). */
    private function impossibleTravel(CarbonImmutable $today): array
    {
        $punches = Punch::query()->with('device.branch')->whereIn('status', [PunchStatus::Valid, PunchStatus::Pending])
            ->where('local_date', '>=', $today->subDays(7)->format('Y-m-d'))->orderBy('employee_id')->orderBy('punched_at')->get();
        $where = function (Punch $p): ?array {
            if ($p->lat !== null && $p->lng !== null) {
                return [(float) $p->lat, (float) $p->lng];
            }
            $branch = $p->device?->branch;

            return $branch && $branch->lat !== null && $branch->lng !== null ? [(float) $branch->lat, (float) $branch->lng] : null;
        };
        $out = [];
        $prev = null;
        foreach ($punches as $p) {
            if ($prev && $prev->employee_id === $p->employee_id && ($a = $where($prev)) && ($b = $where($p))) {
                $km = GeoFence::metres($a[0], $a[1], $b[0], $b[1]) / 1000;
                $hours = max(1, $p->punched_at->getTimestamp() - $prev->punched_at->getTimestamp()) / 3600;
                if ($km >= 5 && $km / $hours > 120) {
                    $name = Employee::find($p->employee_id)?->fullName();
                    $minutes = (int) round($hours * 60);
                    $out[] = ['type' => 'impossible_travel', 'employee_id' => $p->employee_id, 'key' => "travel:{$prev->id}:{$p->id}",
                        'evidence' => ['punches' => [$prev->id, $p->id], 'km' => round($km, 1), 'minutes' => $minutes],
                        'template' => sprintf('%s punched %.0f km apart within %s — faster than possible; check for fake GPS or a shared PIN.', $name, $km, Duration::human($minutes))];
                }
            }
            $prev = $p;
        }

        return $out;
    }

    /** @param list<Anomaly> $anomalies */
    private function explain(array $anomalies): void
    {
        if ($anomalies === [] || ! $this->ai->available()) {
            return;
        }
        $items = array_map(fn (Anomaly $a) => ['id' => $a->id, 'type' => $a->type, 'facts' => $a->explanation], $anomalies);
        $result = $this->ai->ask('anomaly', 'explain ' . count($items) . ' anomalies',
            'For each attendance anomaly, write one neutral sentence for HR explaining what was seen and a sensible next step. '
            . 'Do not accuse anyone; there may be an innocent reason. Keep times as written in the facts (hours and minutes, never a large number of minutes).',
            [['type' => 'text', 'text' => json_encode($items, JSON_UNESCAPED_UNICODE)]],
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'explanation'],
                'properties' => ['id' => ['type' => 'integer'], 'explanation' => ['type' => 'string']],
            ]]]]);
        foreach ($result->ok ? $result->data['items'] : [] as $item) {
            Anomaly::whereKey($item['id'])->update(['explanation' => mb_substr($item['explanation'], 0, 500)]);
        }
    }
}
