<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;

    protected $fillable = ['code', 'name', 'price_per_employee', 'price_fixed', 'feature_flags', 'limits', 'is_active'];

    protected $casts = [
        'feature_flags' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
    ];
}
