<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Contracts\AiModel;

/**
 * Claude via the official Anthropic PHP SDK (spec §12: server-side only, model ID in config/hazri.php).
 * Structured outputs (json_schema) so every reply is machine-checked; refusals are reported, never parsed.
 */
final class AnthropicAiModel implements AiModel
{
    public function json(string $system, array $content, array $schema, string $effort = 'low', int $maxTokens = 4000): AiResult
    {
        $key = (string) config('hazri.ai.api_key');
        if ($key === '') {
            return AiResult::failed('ANTHROPIC_API_KEY is not configured.');
        }

        try {
            $message = (new Client(apiKey: $key))->messages->create(
                model: (string) config('hazri.ai.model'),
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
            );
        } catch (RateLimitException $e) {
            return AiResult::failed('The AI service is busy. Please try again in a minute.');
        } catch (APIStatusException $e) {
            report($e);

            return AiResult::failed('AI request failed (' . ($e->type?->value ?? 'error') . ').');
        } catch (APIConnectionException $e) {
            return AiResult::failed('Could not reach the AI service.');
        }

        $in = $message->usage->inputTokens;
        $out = $message->usage->outputTokens;
        if ($message->stopReason === 'refusal') {
            return AiResult::failed('The AI declined this request.', $in, $out);
        }
        if ($message->stopReason === 'max_tokens') {
            return AiResult::failed('The AI answer was too long.', $in, $out);
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? new AiResult(true, $data, $in, $out) : AiResult::failed('Unreadable AI answer.', $in, $out);
            }
        }

        return AiResult::failed('Empty AI answer.', $in, $out);
    }
}
