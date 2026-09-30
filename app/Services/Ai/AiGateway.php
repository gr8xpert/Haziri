<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiModel;
use App\Models\AiQuery;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * Wraps every AI call (spec §12): feature flag `ai_reports`, per-tenant monthly token budget
 * (setting `ai_monthly_token_budget`), and an `ai_queries` log row per call.
 */
final class AiGateway
{
    public function __construct(private readonly AiModel $model, private readonly TenantSettings $settings) {}

    public function available(): bool
    {
        return $this->settings->featureEnabled('ai_reports') && $this->tokensLeft() > 0;
    }

    public function tokensLeft(): int
    {
        $budget = (int) $this->settings->get('ai_monthly_token_budget', 2_000_000);
        $used = (int) AiQuery::query()->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as t')->value('t');

        return max(0, $budget - $used);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @param  array<string, mixed>  $schema
     */
    public function ask(string $feature, string $prompt, string $system, array $content, array $schema, string $effort = 'low',
        ?int $userId = null, int $maxTokens = 4000): AiResult
    {
        if (! $this->settings->featureEnabled('ai_reports')) {
            return AiResult::failed('AI features are not enabled for your company.');
        }
        if ($this->tokensLeft() <= 0) {
            return AiResult::failed('This month\'s AI budget is used up.');
        }

        $start = microtime(true);
        $result = $this->model->json($system, $content, $schema, $effort, $maxTokens);
        AiQuery::create([
            'user_id' => $userId,
            'feature' => $feature,
            'prompt' => mb_substr($prompt, 0, 5000),
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'status' => $result->ok ? 'ok' : 'failed',
            'error' => $result->error,
        ]);

        return $result;
    }
}
