<?php

declare(strict_types=1);

use App\Contracts\AiModel;
use App\Enums\DevicePunchSourceType;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Filament\Tenant\Pages\AskHazriPage;
use App\Filament\Tenant\Resources\Anomalies\AnomalyResource;
use App\Filament\Tenant\Resources\Anomalies\Pages\ListAnomalies;
use App\Jobs\GenerateMonthlyNarrative;
use App\Models\AiQuery;
use App\Models\Anomaly;
use App\Models\AttendancePeriod;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AnomalyDetector;
use App\Services\Ai\AskHazri;
use App\Services\Ai\AttachmentReader;
use App\Services\Ai\SetupAssistant;
use App\Services\Ai\SqlGuard;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeAiModel;

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'feature.ai_reports'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->ai = new FakeAiModel;
    app()->instance(AiModel::class, $this->ai);
    $this->owner = User::where('email', 'owner@pest.test')->first();
});

afterEach(fn () => DeviceRegistry::whereIn('serial_number', ['AN1', 'AN2'])->delete());

it('accepts only single SELECTs over the curated views', function (string $sql, bool $ok) {
    expect(SqlGuard::check($sql)['ok'])->toBe($ok);
})->with([
    ['SELECT name, COUNT(*) AS lates FROM v_attendance_days WHERE is_late = 1 GROUP BY name', true],
    ['WITH x AS (SELECT * FROM v_leave) SELECT * FROM x', true],
    ['SELECT * FROM employees', false],
    ['SELECT * FROM v_employees; DROP TABLE users', false],
    ['SELECT * FROM v_employees -- comment', false],
    ['DELETE FROM v_employees', false],
    ['SELECT * FROM v_employees UNION SELECT * FROM users', false],
    ['SELECT * FROM hazri_central.super_admins', false],
    ['SELECT sleep(10) FROM v_employees', false],
    ['SELECT * FROM v_employees INTO OUTFILE "/tmp/x"', false],
    ['SELECT * FROM v_employees LIMIT 999999', false],
    ['SELECT * FROM v_employees, users', false],                          // comma join (review finding)
    ['SELECT u.* FROM v_employees e, users u WHERE e.code = u.name', false],
    ['SELECT * FROM v_employees e, v_leave l WHERE e.code = l.code', true],
    ['SELECT * FROM (SELECT * FROM v_leave) x, personal_access u', false],
]);

it('runs generated SQL as a user that can only read the curated views', function () {
    $config = array_merge(config('database.connections.tenant'), ['username' => 'hazri_ai', 'password' => 'hazri_ai']);
    config(['database.connections.ro_probe' => $config]);

    expect(DB::connection('ro_probe')->select('SELECT COUNT(*) AS n FROM v_employees'))->toHaveCount(1)
        ->and(fn () => DB::connection('ro_probe')->select('SELECT * FROM users'))
        ->toThrow(QueryException::class);
});

it('adds a row limit when the model forgets one', function () {
    expect(SqlGuard::check('SELECT code FROM v_employees')['sql'])->toEndWith('LIMIT 5000');
});

it('answers a question with a validated read-only query and logs it', function () {
    $this->ai->reply(['sql' => 'SELECT * FROM users', 'note' => ''])          // rejected → one repair attempt
        ->reply(['sql' => 'SELECT code, name FROM v_employees ORDER BY code', 'note' => '']);

    $answer = app(AskHazri::class)->ask('List all employees', $this->owner->id);

    expect($answer['ok'])->toBeTrue()
        ->and($answer['sql'])->toContain('LIMIT 5000')
        ->and($answer['rows'])->toBeArray() // own read-only connection: can't see this test's uncommitted rows
        ->and(AiQuery::where('feature', 'ask')->count())->toBe(2)
        ->and(AiQuery::where('status', 'rejected')->count())->toBe(1)
        ->and($this->ai->calls[1]['content'][0]['text'])->toContain('rejected');
});

it('ties the answer to its own logged question when another user asks at the same time', function () {
    $interleaved = false;
    AiQuery::created(function () use (&$interleaved) {
        if (! $interleaved) {
            $interleaved = true;
            AiQuery::create(['user_id' => null, 'feature' => 'ask', 'prompt' => 'someone else', 'status' => 'ok']);
        }
    });
    $this->ai->reply(['sql' => '', 'note' => 'Not answerable.']);

    $answer = app(AskHazri::class)->ask('List all employees', $this->owner->id);

    expect(AiQuery::find($answer['query_id']))->prompt->toBe('List all employees')->user_id->toBe($this->owner->id);
});

it('shows the Excel button only to users who may download reports', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $answer = ['ok' => true, 'sql' => 'SELECT 1', 'columns' => ['Code'], 'rows' => [['Code' => 'A1']], 'query_id' => null];
    $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@pest.test', 'password' => 'x']);
    $viewer->givePermissionTo('reports.view');

    $this->actingAs($viewer);
    Livewire::test(AskHazriPage::class)->set('answer', $answer)->assertSee('A1')->assertDontSee('Excel');

    $this->actingAs($this->owner);
    Livewire::test(AskHazriPage::class)->set('answer', $answer)->assertSee('Excel');
});

it('stays off without the feature flag or budget', function () {
    Setting::where('key', 'feature.ai_reports')->update(['value' => json_encode(false)]);
    app(TenantSettings::class)->flush();

    expect(app(AskHazri::class)->ask('anything', $this->owner->id)['ok'])->toBeFalse()->and($this->ai->calls)->toBe([]);

    Setting::where('key', 'feature.ai_reports')->update(['value' => json_encode(true)]);
    Setting::updateOrCreate(['key' => 'ai_monthly_token_budget'], ['value' => 100]);
    app(TenantSettings::class)->flush();
    AiQuery::create(['feature' => 'ask', 'prompt' => 'x', 'status' => 'ok', 'input_tokens' => 90, 'output_tokens' => 20]);

    expect(app(AskHazri::class)->ask('anything', $this->owner->id)['error'])->toContain('budget');
});

it('writes a monthly narrative from the locked snapshot', function () {
    $period = AttendancePeriod::create(['year' => 2026, 'month' => 8, 'status' => 'locked',
        'summary' => ['totals' => ['absent_days' => 3], 'employees' => [5 => ['name' => 'Ali', 'lateCount' => 4, 'absentDays' => 1,
            'paidLeaveDays' => 0, 'unpaidLeaveDays' => 0, 'overtimeMinutes' => 60]]]]);
    $this->ai->reply(['en' => 'August was steady.', 'ur' => 'اگست مستحکم رہا۔']);

    (new GenerateMonthlyNarrative($period->id))->handle(app(AiGateway::class));

    expect($period->fresh()->narrative['en'])->toBe('August was steady.')
        ->and($this->ai->calls[0]['content'][0]['text'])->toContain('Ali')->not->toContain('cnic');
});

it('flags buddy punching and ghost employees with explanations', function () {
    $dev = Device::create(['name' => 'Gate', 'serial_number' => 'AN1', 'type' => DevicePunchSourceType::Adms]);
    $a = Employee::create(['code' => 'BA', 'first_name' => 'Asad', 'joined_on' => '2025-01-01']);
    $b = Employee::create(['code' => 'BB', 'first_name' => 'Bashir', 'joined_on' => '2025-01-01']);
    $ghost = Employee::create(['code' => 'GH', 'first_name' => 'Ghost', 'joined_on' => '2025-01-01']);
    SalaryStructure::create(['employee_id' => $ghost->id, 'effective_from' => '2025-01-01', 'basic' => 40000]);
    $today = CarbonImmutable::parse('2026-09-20', 'Asia/Karachi');
    foreach ([1, 2, 3] as $i) {
        $at = $today->subDays($i)->setTime(9, 0);
        foreach ([[$a, 0], [$b, 3]] as [$e, $sec]) {
            Punch::create(['employee_id' => $e->id, 'device_id' => $dev->id, 'punched_at' => $at->addSeconds($sec)->utc(),
                'local_date' => $at->format('Y-m-d'), 'source' => PunchSource::Adms, 'status' => PunchStatus::Valid,
                'dedupe_hash' => sha1("{$e->id}{$i}")]);
        }
    }
    $this->ai->reply(['items' => []]); // explanation call; templates kept when the model returns nothing

    $n = app(AnomalyDetector::class)->run($today);

    expect($n)->toBe(2)
        ->and(Anomaly::where('type', 'buddy_punching')->first()->explanation)->toContain('Asad')
        ->and(Anomaly::where('type', 'ghost_employee')->value('employee_id'))->toBe($ghost->id)
        ->and(app(AnomalyDetector::class)->run($today))->toBe(0); // deduped

    // Investigate shows the evidence: both people's punches on the same machine, seconds apart.
    $evidence = AnomalyResource::evidence(Anomaly::where('type', 'buddy_punching')->first());
    expect($evidence['facts']['Days'])->toStartWith('3 — ')
        ->and($evidence['punches'])->toHaveCount(6)
        ->and(collect($evidence['punches'])->pluck('where')->unique()->all())->toBe(['Gate'])
        ->and(collect($evidence['punches'])->pluck('employee')->unique()->sort()->values()->all())->toBe(['Asad', 'Bashir']);

    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    Livewire::test(ListAnomalies::class)
        ->mountAction(TestAction::make('investigate')->table(Anomaly::where('type', 'buddy_punching')->first()))
        ->assertActionMounted(TestAction::make('investigate')->table(Anomaly::where('type', 'buddy_punching')->first()));
    expect(view('filament.tenant.anomaly-evidence', $evidence)->render())->toContain('Gate')->toContain('Bashir');
});

it('reads a medical certificate into a suggestion without changing the request', function () {
    Storage::fake('local');
    Storage::disk('local')->put('leave-attachments/cert.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $e = Employee::create(['code' => 'SK', 'first_name' => 'Sick', 'joined_on' => '2025-01-01']);
    $req = LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => LeaveType::where('code', 'SL')->value('id'),
        'from_date' => '2026-09-01', 'to_date' => '2026-09-03', 'days' => 3, 'status' => 'pending', 'attachment_path' => 'leave-attachments/cert.png']);
    $this->ai->reply(['from_date' => '2026-09-01', 'to_date' => '2026-09-03', 'doctor' => 'Dr. Khan', 'clinic' => 'City Clinic', 'summary' => 'Rest advised']);

    $r = app(AttachmentReader::class)->read($req, $this->owner->id);

    expect($r['ok'])->toBeTrue()->and($r['data']['matches_request'])->toBeTrue()
        ->and($this->ai->calls[0]['content'][0]['type'])->toBe('image')
        ->and($req->fresh()->status->value)->toBe('pending');
});

it('proposes settings from a policy as a reviewable diff', function () {
    $this->ai->reply(['changes' => [
        ['target' => 'setting', 'key' => 'lates_per_absent', 'value' => '4', 'reason' => 'Four lates equal one absence.'],
        ['target' => 'shift', 'key' => 'weekly_off_days', 'value' => '0,6', 'reason' => 'Saturday and Sunday are off.'],
    ]]);

    $r = app(SetupAssistant::class)->propose(str_repeat('Our policy text. ', 5), $this->owner->id);

    expect($r['diff'][0])->toMatchArray(['key' => 'lates_per_absent', 'current' => 3, 'proposed' => 4])
        ->and($r['diff'][1]['proposed'])->toBe([0, 6])
        ->and(app(TenantSettings::class)->get('lates_per_absent'))->toBe(3); // nothing applied yet
});
