<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Invoice extends Model
{
    use CentralConnection;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d', 'paid_at' => 'immutable_datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
