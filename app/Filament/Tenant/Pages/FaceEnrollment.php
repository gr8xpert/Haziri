<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Enums\EmployeeStatus;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\FaceTemplate;
use App\Services\SelfService\FaceMatcher;
use App\Services\Settings\TenantSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrol employees for kiosk face recognition (feature kiosk_face): the webcam takes a few samples, the browser turns
 * them into face descriptors and only those numbers are stored — no photos.
 */
class FaceEnrollment extends Page
{
    protected string $view = 'filament.tenant.pages.face-enrollment';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::People;

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Face enrolment';

    protected static ?string $slug = 'face-enrolment';

    public ?int $employeeId = null;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('kiosk_face') && (bool) auth()->user()?->can('employees.manage');
    }

    /** @return array<int, string> */
    public function getEmployeeOptions(): array
    {
        return Employee::query()->where('status', '!=', EmployeeStatus::Left)->orderBy('code')->get()
            ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])->all();
    }

    /** @return list<array{id: int, label: string, samples: int, enrolled: string}> */
    public function getEnrolled(): array
    {
        return FaceTemplate::query()->with('employee')->selectRaw('employee_id, count(*) as samples, max(created_at) as enrolled')
            ->groupBy('employee_id')->get()
            ->filter(fn ($row) => $row->employee !== null)
            ->map(fn ($row) => ['id' => $row->employee_id, 'label' => "{$row->employee->code} — {$row->employee->fullName()}",
                'samples' => (int) $row->samples, 'enrolled' => (string) $row->enrolled])
            ->sortBy('label')->values()->all();
    }

    /**
     * @param  list<list<float>>  $samples  face descriptors from the browser
     * @param  list<float>  $scores  detection score of each sample
     */
    public function saveSamples(array $samples, array $scores = []): void
    {
        abort_unless(static::canAccess(), 403);
        $employee = Employee::query()->find($this->employeeId);
        if ($employee === null) {
            throw ValidationException::withMessages(['employeeId' => __('Choose an employee first.')]);
        }
        $clean = array_values(array_filter(array_map(fn ($s) => FaceMatcher::clean($s), array_slice($samples, 0, 5))));
        if (count($clean) < 2) {
            Notification::make()->danger()->title(__('Not enough good face samples. Try again in better light.'))->send();

            return;
        }
        DB::transaction(function () use ($employee, $clean, $scores) {
            FaceTemplate::query()->where('employee_id', $employee->id)->delete();
            foreach ($clean as $i => $descriptor) {
                FaceTemplate::create(['employee_id' => $employee->id, 'descriptor' => $descriptor, 'created_by' => auth()->id(),
                    'quality' => isset($scores[$i]) && is_numeric($scores[$i]) ? (float) $scores[$i] : null]);
            }
        });
        activity()->causedBy(auth()->user())->performedOn($employee)->event('face_enrolled')->log("Face enrolled for {$employee->code}");
        Notification::make()->success()->title(__(':name can now punch with their face', ['name' => $employee->first_name]))->send();
    }

    public function forget(int $employeeId): void
    {
        abort_unless(static::canAccess(), 403);
        $employee = Employee::query()->findOrFail($employeeId);
        FaceTemplate::query()->where('employee_id', $employee->id)->delete();
        activity()->causedBy(auth()->user())->performedOn($employee)->event('face_deleted')->log("Face data deleted for {$employee->code}");
        Notification::make()->success()->title(__('Face data deleted'))->send();
    }
}
