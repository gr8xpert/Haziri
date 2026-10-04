<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasLabel;

/*
| Every value list shown in the panels has a readable label, so badges, dropdowns and filters
| never print internal codes such as "add_punch" or "pending".
*/
it('gives every enum case a readable label', function () {
    $files = glob(app_path('Enums/*.php'));
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $enum = 'App\\Enums\\' . basename($file, '.php');
        expect(is_subclass_of($enum, HasLabel::class))->toBeTrue("{$enum} has no labels");

        foreach ($enum::cases() as $case) {
            $label = $case->getLabel();
            expect($label)->not->toBeEmpty()->not->toContain('_', "{$enum}::{$case->name} prints a code");
        }
    }
});
