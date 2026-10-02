<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One enrolled face sample (descriptor) of an employee. Biometric data: deleted with the employee or on request. */
class FaceTemplate extends Model
{
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected $hidden = ['descriptor'];

    protected function casts(): array
    {
        return ['descriptor' => 'array', 'quality' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
