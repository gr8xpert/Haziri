<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorrectionType;
use App\Enums\RequestStatus;
use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AttendanceCorrection extends Model
{
    use LogsActivity;
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => CorrectionType::class,
            'status' => RequestStatus::class,
            'payload' => 'array',
            'approver_chain' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
