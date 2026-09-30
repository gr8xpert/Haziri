<?php

declare(strict_types=1);

/*
| Pakistan public holidays (spec §9 phase 1). Fixed-date holidays repeat every year.
| Islamic holidays depend on moon sighting: dates below are the expected Gregorian dates and are seeded with
| needs_confirmation = true so HR confirms or moves them once the federal notification is out.
| Add a year block before it starts.
*/
return [
    'fixed' => [
        '02-05' => 'Kashmir Solidarity Day',
        '03-23' => 'Pakistan Day',
        '05-01' => 'Labour Day',
        '05-28' => 'Youm-e-Takbeer',
        '08-14' => 'Independence Day',
        '11-09' => 'Iqbal Day',
        '12-25' => 'Quaid-e-Azam Day',
    ],

    'islamic' => [
        2026 => [
            '2026-03-20' => 'Eid ul Fitr',
            '2026-03-21' => 'Eid ul Fitr',
            '2026-03-22' => 'Eid ul Fitr',
            '2026-05-27' => 'Eid ul Adha',
            '2026-05-28' => 'Eid ul Adha',
            '2026-05-29' => 'Eid ul Adha',
            '2026-06-24' => 'Ashura (9 Muharram)',
            '2026-06-25' => 'Ashura (10 Muharram)',
            '2026-08-26' => 'Eid Milad un Nabi',
        ],
        2027 => [
            '2027-03-10' => 'Eid ul Fitr',
            '2027-03-11' => 'Eid ul Fitr',
            '2027-03-12' => 'Eid ul Fitr',
            '2027-05-17' => 'Eid ul Adha',
            '2027-05-18' => 'Eid ul Adha',
            '2027-05-19' => 'Eid ul Adha',
            '2027-06-14' => 'Ashura (9 Muharram)',
            '2027-06-15' => 'Ashura (10 Muharram)',
            '2027-08-15' => 'Eid Milad un Nabi',
        ],
    ],
];
