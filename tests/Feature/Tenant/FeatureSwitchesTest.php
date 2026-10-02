<?php

declare(strict_types=1);

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;

it('applies connected switches when the super-admin changes a client and mirrors them into the client', function () {
    $original = SetTenantFeatureFlags::current($this->tenant);
    $set = app(SetTenantFeatureFlags::class);

    try {
        $set($this->tenant, ['payroll_lite' => true, 'payroll_tax' => true, 'kiosk' => true, 'kiosk_face' => true]);
        $result = $set($this->tenant, ['payroll_lite' => false, 'kiosk' => false]);

        $settings = app(TenantSettings::class);
        $settings->flush();
        expect($result['turned_off'])->toEqualCanonicalizing(['payroll_tax', 'kiosk_face'])
            ->and(SetTenantFeatureFlags::current($this->tenant))->toMatchArray(['payroll_tax' => false, 'kiosk_face' => false])
            ->and($settings->featureEnabled('payroll_tax'))->toBeFalse()
            ->and($settings->featureEnabled('kiosk_face'))->toBeFalse();
    } finally {
        $set($this->tenant, $original); // central settings are not rolled back with the tenant transaction
    }
});

it('falls back to the catalog default for a switch a client has never had set', function () {
    $settings = app(TenantSettings::class);
    Setting::query()->where('key', 'feature.rosters')->delete();
    $settings->flush();

    expect($settings->featureEnabled('rosters'))->toBeTrue()      // existed before it had a switch
        ->and($settings->featureEnabled('payroll_pf'))->toBeFalse();
});
