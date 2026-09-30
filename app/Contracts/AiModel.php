<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Ai\AiResult;

/** The one place the app talks to an LLM (spec §12). Bound to AnthropicAiModel; tests bind a fake. */
interface AiModel
{
    /**
     * Ask for a JSON object matching $schema.
     *
     * @param  list<array<string, mixed>>  $content  user content blocks (text / image / document)
     * @param  array<string, mixed>  $schema  JSON schema of the expected object
     */
    public function json(string $system, array $content, array $schema, string $effort = 'low', int $maxTokens = 4000): AiResult;
}
