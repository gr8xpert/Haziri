<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Actions\Corrections\SubmitCorrection;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Actions\Payroll\PayrollActions;
use App\Actions\Rosters\ApproveShiftSwap;
use App\Actions\Rosters\RequestShiftSwap;
use App\Actions\SelfService\SelfPunch;
use App\Enums\CorrectionType;
use App\Enums\HolidayType;
use App\Enums\LeaveDayPart;
use App\Enums\PunchDirection;
use App\Enums\RequestStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Announcement;
use App\Models\AnnouncementAck;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayOptIn;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\ShiftSwapRequest;
use App\Reports\StatusCodes;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Reports\MonthlyAttendanceExport;
use App\Services\SelfService\QrBadge;
use App\Services\Settings\TenantSettings;
use App\Support\Brand;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Employee self-service PWA (spec §9 phase 3, §13.2). */
final class MeController
{
    public function home(Request $request, TenantSettings $settings): View
    {
        $employee = $request->user()->employee;

        return view('me.app', [
            'employee' => $employee,
            'company' => tenant('company_name'),
            'canPunch' => $settings->featureEnabled('mobile_punch') && (bool) $employee?->mobile_punch_allowed,
            'faceCheck' => $settings->featureEnabled('kiosk_face') && (bool) $settings->get('face_check_photo_punches'),
            'rtl' => app()->getLocale() === 'ur',
            'leaveTypes' => LeaveType::where('is_active', true)->get(['id', 'name', 'code', 'allow_half_day']),
        ]);
    }

    public function summary(Request $request, LeaveBalanceService $balances): JsonResponse
    {
        $employee = $request->user()->employee;
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : CarbonImmutable::now($tz)->format('Y-m');
        $start = CarbonImmutable::parse("{$month}-01");

        $days = AttendanceDay::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')])->get()
            ->keyBy(fn (AttendanceDay $d) => $d->date->format('Y-m-d'));
        $calendar = [];
        foreach (CarbonPeriod::create($start, $start->endOfMonth()->startOfDay()) as $d) {
            $row = $days->get($d->format('Y-m-d'));
            $calendar[] = [
                'date' => $d->format('Y-m-d'),
                'code' => StatusCodes::for($row),
                'in' => $row?->first_in?->setTimezone($tz)->format('H:i'),
                'out' => $row?->last_out?->setTimezone($tz)->format('H:i'),
                'late' => $row?->late_min ?: null,
            ];
        }
        $today = $days->get(CarbonImmutable::now($tz)->format('Y-m-d'));

        $year = app(LeaveYear::class)->current();
        $leaveBalances = LeaveType::where('is_active', true)->get()->map(fn (LeaveType $t) => [
            'code' => $t->code, 'name' => $t->name, 'remaining' => $balances->balance($employee, $t, $year)->remaining(),
        ]);

        return response()->json([
            'employee' => ['name' => $employee->fullName(), 'code' => $employee->code],
            'today' => $today ? [
                'status' => $today->status?->value, 'in' => $today->first_in?->setTimezone($tz)->format('H:i'),
                'out' => $today->last_out?->setTimezone($tz)->format('H:i'), 'late_min' => $today->late_min,
            ] : null,
            'next_direction' => SelfPunch::nextDirection($employee->id)->value,
            'month' => $month,
            'calendar' => $calendar,
            'payslip' => $this->finalPayslip($employee, $month) !== null,
            'balances' => $leaveBalances,
            'requests' => LeaveRequest::query()->with('leaveType')->where('employee_id', $employee->id)->latest()->limit(10)->get()
                ->map(fn (LeaveRequest $l) => ['type' => $l->leaveType?->code, 'from' => $l->from_date->format('Y-m-d'),
                    'to' => $l->to_date->format('Y-m-d'), 'days' => $l->days, 'status' => $l->status->value]),
            'announcements' => Announcement::visibleTo($employee)->limit(5)->get()
                ->map(fn (Announcement $a) => ['id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'requires_ack' => $a->requires_ack,
                    'acked' => $a->acks->contains('employee_id', $employee->id)]),
            'optional_holidays' => $this->optionalHolidays($employee),
            'swaps' => $this->swaps($employee),
        ]);
    }

    /** Ask a colleague to swap shifts on a date. */
    public function shiftSwap(Request $request, RequestShiftSwap $swaps): JsonResponse
    {
        $data = $request->validate([
            'partner_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $swap = $swaps->ask($request->user()->employee, Employee::query()->findOrFail($data['partner_employee_id']), $data['date'], $data['reason'] ?? null);

        return response()->json(['id' => $swap->id, 'status' => $swap->status->getLabel()], 201);
    }

    /** The colleague accepts or declines. */
    public function shiftSwapAnswer(Request $request, ShiftSwapRequest $swap, RequestShiftSwap $swaps): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $swaps->answer($swap, $request->user()->employee, (bool) $data['accept'], $request->user());

        return response()->json(['status' => $swap->fresh()->status->getLabel()]);
    }

    /**
     * The employee's swap requests (theirs and ones asking them), plus the colleagues they can ask.
     *
     * @return array{enabled: bool, colleagues: list<array{id: int, name: string}>, items: list<array<string, mixed>>}
     */
    private function swaps(Employee $employee): array
    {
        if (! app(TenantSettings::class)->featureEnabled('rosters')) {
            return ['enabled' => false, 'colleagues' => [], 'items' => []];
        }
        $describe = app(ApproveShiftSwap::class);
        $items = ShiftSwapRequest::query()->with(['requester', 'partner'])
            ->where(fn ($q) => $q->where('requester_employee_id', $employee->id)->orWhere('partner_employee_id', $employee->id))
            ->whereDate('date', '>=', now(tenant('timezone'))->subDays(7)->format('Y-m-d'))->orderByDesc('date')->limit(10)->get()
            ->map(fn (ShiftSwapRequest $s) => [
                'id' => $s->id, 'date' => $s->date->format('D d M Y'), 'status' => $s->status->getLabel(),
                'with' => ($s->requester_employee_id === $employee->id ? $s->partner : $s->requester)?->fullName(),
                'shifts' => $describe->describe($s->requester, $s->partner, $s->date->format('Y-m-d')),
                'can_answer' => $s->partner_employee_id === $employee->id && $s->status === ShiftSwapStatus::Partner,
            ])->all();
        $colleagues = RequestShiftSwap::colleagues($employee)->orderBy('first_name')->limit(500)->get()
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => "{$e->fullName()} ({$e->code})"])->all();

        return ['enabled' => true, 'colleagues' => $colleagues, 'items' => $items];
    }

    /** The employee's own monthly attendance report (same PDF HR prints), for this or an earlier month. */
    public function monthlyReport(Request $request, MonthlyAttendanceExport $export): StreamedResponse
    {
        $employee = $request->user()->employee;
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $month = (string) $request->query('month');
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) && $month <= CarbonImmutable::now($tz)->format('Y-m'), 404);
        [$year, $m] = array_map('intval', explode('-', $month));
        $bytes = $export->combinedPdf(collect([$employee]), $year, $m, $employee->fullName());

        return response()->streamDownload(fn () => print ($bytes), "attendance-{$employee->code}-{$month}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /** The employee's own payslip PDF for a pay month whose payroll is final. */
    public function payslip(Request $request, PayrollActions $payroll): StreamedResponse
    {
        $employee = $request->user()->employee;
        $month = (string) $request->query('month');
        $slip = $this->finalPayslip($employee, $month);
        abort_if($slip === null, 404);

        return response()->streamDownload(fn () => print ($payroll->payslipPdf($slip)), "payslip-{$employee->code}-{$month}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /** Payslip of a final payroll run for the pay month "Y-m" (only when the company has payroll). */
    private function finalPayslip(Employee $employee, string $month): ?Payslip
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month) || ! app(TenantSettings::class)->featureEnabled('payroll_lite')) {
            return null;
        }
        [$year, $m] = array_map('intval', explode('-', $month));

        return Payslip::query()->where('employee_id', $employee->id)
            ->whereHas('run', fn (Builder $q) => $q->where('status', 'final')->whereHas('period', fn (Builder $p) => $p->where(['year' => $year, 'month' => $m])))
            ->first();
    }

    /** Take or give back an optional holiday (until the day itself). */
    public function optionalHoliday(Request $request, Holiday $holiday): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($this->optionalHolidaysQuery($employee)->whereKey($holiday->id)->exists(), 404);
        $existing = HolidayOptIn::query()->where(['holiday_id' => $holiday->id, 'employee_id' => $employee->id])->first();
        if ($existing !== null) {
            $existing->delete();
        } else {
            HolidayOptIn::create(['holiday_id' => $holiday->id, 'employee_id' => $employee->id, 'created_by' => $request->user()->id]);
        }

        return response()->json(['taken' => $existing === null]);
    }

    /** @return list<array{id: int, name: string, date: string, taken: bool}> upcoming optional holidays (next 90 days) */
    private function optionalHolidays(Employee $employee): array
    {
        return $this->optionalHolidaysQuery($employee)->withExists(['optIns as taken' => fn ($q) => $q->where('employee_id', $employee->id)])
            ->orderBy('date')->get()
            ->map(fn (Holiday $h) => ['id' => $h->id, 'name' => $h->name, 'date' => $h->date->format('D d M Y'), 'taken' => (bool) $h->taken])->all();
    }

    private function optionalHolidaysQuery(Employee $employee): Builder
    {
        $today = now(tenant('timezone'))->format('Y-m-d');

        return Holiday::query()->where('type', HolidayType::Optional->value)
            ->whereBetween('date', [$today, now(tenant('timezone'))->addDays(90)->format('Y-m-d')])
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id));
    }

    public function punch(Request $request, SelfPunch $punch): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'photo' => ['nullable', 'string', 'max:2100000'],
            'direction' => ['nullable', Rule::enum(PunchDirection::class)],
            'face' => ['nullable', 'array', 'size:1024'],
            'face.*' => ['numeric'],
        ]);
        $r = $punch($request->user()->employee, (float) $data['lat'], (float) $data['lng'], isset($data['accuracy']) ? (int) $data['accuracy'] : null,
            $data['photo'] ?? null, isset($data['direction']) ? PunchDirection::from($data['direction']) : null, $request->ip(), $data['face'] ?? null);

        return response()->json([
            'outcome' => $r['result']->outcome->value,
            'direction' => $r['direction']->value,
            'time' => now(tenant('timezone'))->format('H:i'),
            'inside_geofence' => $r['geofence']['inside'],
            'office_network' => $r['geofence']['office_network'],
            'distance_m' => $r['geofence']['distance_m'],
            'face_check' => $r['face_check']['result'] ?? null,
        ]);
    }

    public function leave(Request $request, SubmitLeaveRequest $submit): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d'],
            'day_part' => ['nullable', Rule::enum(LeaveDayPart::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        $path = $request->file('attachment')?->store('leave-attachments', 'local');
        $r = $submit($request->user()->employee, LeaveType::findOrFail($data['leave_type_id']), $data['from_date'], $data['to_date'],
            $request->user(), LeaveDayPart::tryFrom((string) ($data['day_part'] ?? '')) ?? LeaveDayPart::Full, $data['reason'] ?? null, $path);

        return response()->json(['id' => $r->id, 'days' => $r->days, 'status' => $r->status->value], 201);
    }

    public function correction(Request $request, SubmitCorrection $submit): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['add_punch', 'mark_od', 'mark_wfh'])],
            'time' => ['required_if:type,add_punch', 'nullable', 'date_format:H:i'],
            'direction' => ['required_if:type,add_punch', 'nullable', Rule::enum(PunchDirection::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $type = CorrectionType::from($data['type']);
        $payload = $type === CorrectionType::AddPunch ? ['time' => $data['time'], 'direction' => $data['direction']] : [];
        $c = $submit($request->user()->employee, $data['date'], $type, $payload, $data['reason'], $request->user());

        return response()->json(['id' => $c->id, 'status' => RequestStatus::Pending->value], 201);
    }

    public function ack(Request $request, Announcement $announcement): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($announcement->isFor($employee), 404);
        AnnouncementAck::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'employee_id' => $employee->id], ['acked_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    public function qr(Request $request, QrBadge $badge): Response
    {
        return response($badge->svg($request->user()->employee), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => Brand::whiteLabel() ? Brand::name() : (tenant('company_name') ?? Brand::name()) . ' — ' . Brand::name(),
            'short_name' => Brand::name(),
            'start_url' => '/me',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => Brand::color() ?? '#047857',
            'icons' => [['src' => Brand::iconUrl(), 'sizes' => 'any', 'purpose' => 'any maskable']],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
