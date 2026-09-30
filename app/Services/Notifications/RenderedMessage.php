<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final readonly class RenderedMessage
{
    /**
     * @param  array<string, mixed>  $data  raw placeholder values (providers with their own templates use them)
     */
    public function __construct(
        public string $event,
        public ?string $subject,
        public string $body,
        public array $data = [],
        public ?string $providerTemplate = null,
        public ?string $url = null,
    ) {}
}
