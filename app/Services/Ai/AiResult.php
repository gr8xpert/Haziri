<?php

declare(strict_types=1);

namespace App\Services\Ai;

final readonly class AiResult
{
    /**
     * @param  array<string, mixed>|null  $data  decoded JSON on success
     */
    public function __construct(
        public bool $ok,
        public ?array $data,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $error = null,
    ) {}

    public static function failed(string $error, int $in = 0, int $out = 0): self
    {
        return new self(false, null, $in, $out, $error);
    }
}
