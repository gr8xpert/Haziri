<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Salary advance recovered in equal monthly instalments. */
class Advance extends Model
{
    use LogsActivity;
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date:Y-m-d'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** No instalment recovered yet (and not settled), so the amount can still be changed or the advance deleted. */
    public function nothingRecovered(): bool
    {
        return $this->status === 'active' && (int) $this->remaining === (int) $this->amount;
    }

    public function instalment(): int
    {
        return (int) min($this->remaining, (int) ceil($this->amount / max(1, $this->instalments)));
    }
}
