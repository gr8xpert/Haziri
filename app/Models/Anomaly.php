<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomaly extends Model
{
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['detected_on' => 'date:Y-m-d', 'evidence' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
