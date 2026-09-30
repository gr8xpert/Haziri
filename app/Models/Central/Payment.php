<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Payment extends Model
{
    use CentralConnection;

    public const METHODS = ['bank' => 'Bank transfer', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'cash' => 'Cash'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'immutable_datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
