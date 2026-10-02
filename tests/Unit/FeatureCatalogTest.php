<?php

declare(strict_types=1);

use App\Services\Features\FeatureCatalog;

function catalog(): FeatureCatalog
{
    return new FeatureCatalog([
        'groups' => ['a' => ['label' => 'A']],
        'features' => [
            'kiosk' => ['group' => 'a', 'label' => 'Kiosk', 'requires' => [], 'default' => false],
            'kiosk_face' => ['group' => 'a', 'label' => 'Face', 'requires' => ['kiosk'], 'default' => false],
            'face_liveness' => ['group' => 'a', 'label' => 'Liveness', 'requires' => ['kiosk_face'], 'default' => false],
            'rosters' => ['group' => 'a', 'label' => 'Rosters', 'requires' => [], 'default' => true],
        ],
    ]);
}

it('turns on everything a newly enabled feature needs, transitively', function () {
    $r = catalog()->apply(['kiosk' => false, 'kiosk_face' => false, 'face_liveness' => false], ['face_liveness' => true]);

    expect($r['flags'])->toMatchArray(['kiosk' => true, 'kiosk_face' => true, 'face_liveness' => true])
        ->and($r['turned_on'])->toEqualCanonicalizing(['kiosk', 'kiosk_face'])
        ->and($r['turned_off'])->toBe([]);
});

it('turns off everything that needs a newly disabled feature, transitively', function () {
    $r = catalog()->apply(['kiosk' => true, 'kiosk_face' => true, 'face_liveness' => true], ['kiosk' => false]);

    expect($r['flags'])->toMatchArray(['kiosk' => false, 'kiosk_face' => false, 'face_liveness' => false])
        ->and($r['turned_off'])->toEqualCanonicalizing(['kiosk_face', 'face_liveness']);
});

it('keeps unrelated switches and fills unset ones with their default', function () {
    $r = catalog()->apply([], ['kiosk' => true]);

    expect($r['flags'])->toBe(['kiosk' => true, 'kiosk_face' => false, 'face_liveness' => false, 'rosters' => true])
        ->and($r['turned_on'])->toBe([]);
});

it('normalises stored switches whose requirements are off', function () {
    expect(catalog()->normalise(['kiosk' => false, 'kiosk_face' => true, 'face_liveness' => true]))
        ->toMatchArray(['kiosk_face' => false, 'face_liveness' => false]);
});

it('ships a catalog whose requirements all exist and never loop', function () {
    $config = require __DIR__ . '/../../config/features.php';
    $catalog = new FeatureCatalog($config);

    foreach ($catalog->keys() as $key) {
        expect(array_diff($catalog->requirements($key), $catalog->keys()))->toBe([])
            ->and($catalog->requirements($key))->not->toContain($key)
            ->and(array_key_exists($config['features'][$key]['group'], $catalog->groups()))->toBeTrue();
    }
    expect($catalog->dependants('payroll_lite'))->toContain('payroll_tax', 'payroll_settlement');
});
