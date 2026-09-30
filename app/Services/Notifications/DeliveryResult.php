<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final readonly class DeliveryResult
{
    public function __construct(public bool $ok, public ?string $providerRef = null, public ?string $error = null) {}

    public static function sent(?string $ref = null): self
    {
        return new self(true, $ref);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
