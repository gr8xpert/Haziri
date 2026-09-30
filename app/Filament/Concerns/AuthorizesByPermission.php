<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Maps Filament's resource abilities onto spatie permissions (spec §8):
 * viewAny / view → static::$viewPermission, everything else → static::$managePermission.
 * Resources set both static properties.
 */
trait AuthorizesByPermission
{
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        $permission = in_array($action, ['viewAny', 'view'], true) ? static::$viewPermission : static::$managePermission;

        return auth()->user()?->can($permission) ? Response::allow() : Response::deny();
    }
}
