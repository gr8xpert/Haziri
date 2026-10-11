<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final readonly class DeliveryResult
{
    /** @param  bool  $loggedOnly  written to the server log only: no provider is set up, so nothing left the app */
    public function __construct(public bool $ok, public ?string $providerRef = null, public ?string $error = null, public bool $loggedOnly = false) {}

    public static function sent(?string $ref = null): self
    {
        return new self(true, $ref);
    }

    public static function loggedOnly(): self
    {
        return new self(true, 'log', null, true);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
