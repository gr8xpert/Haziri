<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\AiModel;
use App\Services\Ai\AiResult;

/** Scripted AI for tests: queue replies; records every call. Never touches the network. */
final class FakeAiModel implements AiModel
{
    /** @var list<AiResult> */
    public array $replies = [];

    /** @var list<array{system: string, content: array, schema: array}> */
    public array $calls = [];

    public function reply(array $data, int $in = 100, int $out = 50): self
    {
        $this->replies[] = new AiResult(true, $data, $in, $out);

        return $this;
    }

    public function json(string $system, array $content, array $schema, string $effort = 'low', int $maxTokens = 4000): AiResult
    {
        $this->calls[] = ['system' => $system, 'content' => $content, 'schema' => $schema];

        return array_shift($this->replies) ?? AiResult::failed('no scripted reply');
    }
}
