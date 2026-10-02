<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\PunchData;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Reports\PayrollInputReport;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public tenant API v1 (spec §13.3). Read endpoints need ability `read`; POST /punches needs `punches:write`. */
final class PublicApiController
{
    public function employees(Request $request): JsonResponse
    {
        $page = Employee::query()->with(['branch', 'department', 'designation'])->orderBy('code')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->paginate(min(200, (int) $request->query('per_page', 100)));

        return response()->json([
            'data' => collect($page->items())->map(fn (Employee $e) => [
                'code' => $e->code, 'name' => $e->fullName(), 'device_pin' => $e->device_pin, 'email' => $e->email,
                'branch' => $e->branch?->code, 'department' => $e->department?->code, 'designation' => $e->designation?->name,
                'employment_type' => $e->employment_type?->value, 'status' => $e->status?->value,
                'joined_on' => $e->joined_on?->format('Y-m-d'), 'left_on' => $e->left_on?->format('Y-m-d'),
            ]),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee' => ['nullable', 'string', 'max:30'],
        ]);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 93, 422, 'At most 93 days per request.');
        $tz = tenant('timezone') ?? config('hazri.default_timezone');

        $rows = AttendanceDay::query()->with('employee')
            ->whereBetween('date', [$data['from'], $data['to']])
            ->when($data['employee'] ?? null, fn ($q, $code) => $q->whereHas('employee', fn ($e) => $e->where('code', $code)))
            ->orderBy('date')->orderBy('employee_id')->limit(20000)->get();

        return response()->json(['data' => $rows->map(fn (AttendanceDay $d) => [
            'employee' => $d->employee?->code, 'date' => $d->date->format('Y-m-d'), 'status' => $d->status?->value,
            'first_in' => $d->first_in?->setTimezone($tz)->toIso8601String(), 'last_out' => $d->last_out?->setTimezone($tz)->toIso8601String(),
            'worked_min' => $d->worked_min, 'late_min' => $d->late_min, 'early_out_min' => $d->early_out_min,
            'overtime_min' => $d->overtime_min, 'flags' => $d->flags ?? [],
        ])]);
    }

    public function leaveBalances(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: app(LeaveYear::class)->current());

        return response()->json(['data' => LeaveBalance::query()->with(['employee', 'leaveType'])->where('year', $year)->get()
            ->map(fn (LeaveBalance $b) => [
                'employee' => $b->employee?->code, 'type' => $b->leaveType?->code, 'year' => $b->year,
                'entitled' => $b->opening + $b->accrued + $b->adjusted, 'used' => $b->used, 'pending' => $b->pending, 'remaining' => $b->remaining(),
            ])]);
    }

    public function punch(Request $request, IngestPunchAction $ingest): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => ['required_without:pin', 'nullable', 'string', 'max:30'],
            'pin' => ['required_without:employee_code', 'nullable', 'string', 'max:20'],
            'timestamp' => ['required', 'date'],
            'direction' => ['nullable', Rule::enum(PunchDirection::class)],
            'external_id' => ['nullable', 'string', 'max:50'],
        ]);
        $employee = isset($data['employee_code']) ? Employee::query()->where('code', $data['employee_code'])->first() : null;
        if (isset($data['employee_code']) && $employee === null) {
            return response()->json(['error' => 'unknown_employee'], 422);
        }
        $at = CarbonImmutable::parse($data['timestamp'], tenant('timezone') ?? config('hazri.default_timezone'))->utc();
        abort_if($at->greaterThan(now()->addMinutes(5)), 422, 'Timestamp is in the future.');

        $result = $ingest(new PunchData(
            punchedAt: $at, source: PunchSource::Api,
            devicePin: $employee ? null : $data['pin'], employeeId: $employee?->id,
            deviceSerial: 'api:' . $request->attributes->get('api_token')->id,
            direction: PunchDirection::tryFrom((string) ($data['direction'] ?? '')) ?? PunchDirection::Unknown,
            externalId: $data['external_id'] ?? null,
        ));

        return response()->json(['outcome' => $result->outcome->value, 'id' => $result->punch?->id], $result->punch ? 201 : 200);
    }

    public function payrollInput(string $ym, PayrollInputReport $report): JsonResponse
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $ym) === 1, 404);

        return response()->json(['month' => $ym, 'status' => $report->subtitle(['month' => $ym]), 'data' => $report->rows(['month' => $ym])]);
    }
}
