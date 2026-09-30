<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Tablet kiosk (spec §5.2, §6.5). The token in its URL is the only credential — rotate it if a tablet is lost. */
class Kiosk extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['allowed_modes' => 'array', 'require_photo' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['allowed_modes', 'require_photo', 'branch_id'])->logOnlyDirty();
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function allows(string $mode): bool
    {
        return in_array($mode, $this->allowed_modes ?? ['pin'], true);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
