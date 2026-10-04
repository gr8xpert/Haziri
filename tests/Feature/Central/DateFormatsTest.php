<?php

declare(strict_types=1);

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;

/*
| Dates are day-first in every form, whatever the browser's language (the native input showed mm/dd/yyyy).
*/
it('shows date pickers day-first and leaves time pickers alone', function () {
    $date = DatePicker::make('d');
    $dateTime = DateTimePicker::make('dt');
    $time = TimePicker::make('t');

    expect($date->isNative())->toBeFalse()
        ->and($date->getDisplayFormat())->toBe('d/m/Y')
        ->and($dateTime->isNative())->toBeFalse()
        ->and($dateTime->getDisplayFormat())->toBe('d/m/Y H:i')
        ->and($time->isNative())->toBeTrue();
});
