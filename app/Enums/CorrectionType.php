<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CorrectionType: string implements HasLabel
{
    case AddPunch = 'add_punch';
    case RemovePunch = 'remove_punch';
    case ChangeStatus = 'change_status';
    case MarkOd = 'mark_od';
    case MarkWfh = 'mark_wfh';

    /** One line for the request form: what the request does and when to use it. */
    public function hint(): string
    {
        return match ($this) {
            self::AddPunch => __('Adds a missing punch. Use when the employee came or left but the punch was not recorded.'),
            self::RemovePunch => __('Deletes a wrong punch, e.g. a double tap. Choose the employee and date first to see their punches.'),
            self::ChangeStatus => __("Overrides the day's result (e.g. Absent to Present), whatever the punches say."),
            self::MarkOd => __('Marks the day as official duty (bank, client visit, field work): paid, no absence.'),
            self::MarkWfh => __('Marks the day as work from home: paid, no absence.'),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AddPunch => __('Add punch'),
            self::RemovePunch => __('Remove punch'),
            self::ChangeStatus => __('Change day status'),
            self::MarkOd => __('Official duty'),
            self::MarkWfh => __('Work from home'),
        };
    }
}
