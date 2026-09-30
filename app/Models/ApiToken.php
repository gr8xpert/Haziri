<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Public API personal access token (spec §13.3). Only the SHA-256 hash is stored. */
class ApiToken extends Model
{
    use LogsActivity;

    public const ABILITIES = ['read' => 'Read employees, attendance, leave, payroll input', 'punches:write' => 'Send punches'];

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'abilities', 'revoked_at'])->logOnlyDirty();
    }

    /** @return array{token: ApiToken, plain: string} */
    public static function issue(string $name, array $abilities, ?int $by): array
    {
        $plain = 'hzp_' . Str::random(48);
        $token = static::create(['name' => $name, 'abilities' => array_values(array_intersect($abilities, array_keys(self::ABILITIES))),
            'token_hash' => hash('sha256', $plain), 'created_by' => $by]);

        return ['token' => $token, 'plain' => $plain];
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }
}
