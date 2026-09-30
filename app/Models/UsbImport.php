<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsbImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['log' => 'array'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
