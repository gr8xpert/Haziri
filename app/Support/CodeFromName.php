<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Short codes made from a name, so HR never has to invent one: "Casual Leave" → CL, "Lahore Head Office" → LHO,
 * "Production" → PRO. Made unique against the table (CL2, CL3…), and always editable.
 */
final class CodeFromName
{
    /** @param class-string<Model> $model */
    public static function suggest(string $name, string $model, int $max = 10, string $column = 'code'): string
    {
        $words = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', Str::ascii($name)) ?: []));
        $base = match (true) {
            $words === [] => 'X',
            count($words) === 1 => Str::substr($words[0], 0, 3),
            default => implode('', array_map(fn (string $w) => ctype_digit($w) ? $w : $w[0], $words)),
        };
        $base = Str::upper(Str::substr($base, 0, $max));

        $query = fn (string $code) => in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $model::withTrashed()->where($column, $code)
            : $model::query()->where($column, $code);
        $code = $base;
        for ($n = 2; $query($code)->exists(); $n++) {
            $code = Str::substr($base, 0, $max - strlen((string) $n)) . $n;
        }

        return $code;
    }

    /**
     * The "name" field of a form: when it is filled in and the code is still empty, the code is suggested.
     *
     * @param  class-string<Model>  $model
     */
    public static function nameInput(string $model, int $codeMax = 10, string $name = 'name', string $code = 'code'): TextInput
    {
        return TextInput::make($name)->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($model, $codeMax, $code): void {
                if (filled($state) && blank($get($code))) {
                    $set($code, self::suggest($state, $model, $codeMax));
                }
            });
    }
}
