<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Employee;
use App\Models\User;
use App\Services\Reports\MonthlyAttendanceExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A big monthly attendance export (one PDF, or a ZIP of one PDF per employee) made in the background, so hundreds
 * of employees don't run into the web request time limit. The user gets a notification with a download link.
 */
final class BuildMonthlyAttendanceExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    /** @param list<int> $employeeIds */
    public function __construct(
        public readonly array $employeeIds,
        public readonly int $year,
        public readonly int $month,
        public readonly bool $separateFiles,
        public readonly int $userId,
        public readonly string $fileName,
    ) {}

    public function handle(MonthlyAttendanceExport $export): void
    {
        @set_time_limit(0);
        $user = User::find($this->userId);
        $employees = Employee::withTrashed()->whereIn('id', $this->employeeIds)->orderBy('code')->get();
        // Kept in the requesting user's own folder: only they can download it (see the tenant.export-download route).
        $stored = "exports/{$this->userId}/" . Str::uuid() . '-' . $this->fileName;

        if ($this->separateFiles) {
            $zip = $export->zip($employees, $this->year, $this->month, $user?->name);
            Storage::disk('local')->put($stored, (string) file_get_contents($zip));
            @unlink($zip);
        } else {
            Storage::disk('local')->put($stored, $export->combinedPdf($employees, $this->year, $this->month, $user?->name));
        }
        MonthlyAttendanceExport::prune();

        if ($user !== null) {
            Notification::make()->success()->title(__('Your attendance report is ready'))
                ->body(__(':n employees · :file', ['n' => $employees->count(), 'file' => $this->fileName]))
                ->actions([Action::make('download')->label(__('Download'))->button()
                    ->url(URL::temporarySignedRoute('tenant.export-download', now()->addDays(MonthlyAttendanceExport::KEEP_DAYS), ['file' => basename($stored)]))])
                ->sendToDatabase($user);
        }
    }
}
