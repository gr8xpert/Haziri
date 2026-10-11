<?php

declare(strict_types=1);

use App\Contracts\AiModel;
use App\Enums\AttendanceStatus;
use App\Models\Anomaly;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\Ai\AnomalyDetector;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Tests\Support\FakeAiModel;

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'feature.ai_reports'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->ai = new FakeAiModel;
    app()->instance(AiModel::class, $this->ai);
    $this->today = CarbonImmutable::parse('2026-09-30', 'Asia/Karachi');
    $this->morning = Shift::create(['name' => 'Morning', 'code' => 'APM', 'start_time' => '09:00', 'end_time' => '17:00']);
    $this->evening = Shift::create(['name' => 'Evening', 'code' => 'APE', 'start_time' => '14:00', 'end_time' => '22:00']);
});

/** Four weeks of 09:00 arrivals on the morning shift, then this week's arrivals at $time on $shift. */
function arrivals(Employee $e, Shift $before, Shift $recent, string $time, CarbonImmutable $today): void
{
    for ($d = 30; $d >= 1; $d--) {
        $date = $today->subDays($d);
        [$shift, $at] = $d <= 7 ? [$recent, $time] : [$before, '09:00'];
        AttendanceDay::create(['employee_id' => $e->id, 'date' => $date->format('Y-m-d'), 'flags' => [], 'status' => AttendanceStatus::Present,
            'shift_id' => $shift->id, 'first_in' => $date->setTimeFromTimeString($at)->utc()]);
    }
}

it('does not call a move to another shift a change in arrival time', function () {
    $e = Employee::create(['code' => 'AP1', 'first_name' => 'Amna', 'joined_on' => '2025-01-01']);
    arrivals($e, $this->morning, $this->evening, '14:00', $this->today);
    $this->ai->reply(['items' => []]);

    app(AnomalyDetector::class)->run($this->today);

    expect(Anomaly::where('type', 'pattern_shift')->where('employee_id', $e->id)->exists())->toBeFalse();
});

it('still flags someone coming much later on the same shift', function () {
    $e = Employee::create(['code' => 'AP2', 'first_name' => 'Waqas', 'joined_on' => '2025-01-01']);
    arrivals($e, $this->morning, $this->morning, '10:30', $this->today);
    $this->ai->reply(['items' => []]);

    app(AnomalyDetector::class)->run($this->today);

    expect(Anomaly::where('type', 'pattern_shift')->where('employee_id', $e->id)->value('explanation'))
        ->toContain('moved 1 hour 30 minutes later');
});
