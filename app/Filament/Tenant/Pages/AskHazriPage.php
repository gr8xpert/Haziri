<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\AiQuery;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AskHazri;
use App\Support\Excel\Spreadsheet;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Natural-language reports (spec §12.1). Answers are read-only queries over curated views. */
class AskHazriPage extends Page
{
    protected string $view = 'filament.tenant.pages.ask-hazri';

    protected static ?string $slug = 'ask';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Ask Hazri';

    public string $question = '';

    /** @var array<string, mixed>|null */
    public ?array $answer = null;

    public static function canAccess(): bool
    {
        return app(\App\Services\Settings\TenantSettings::class)->featureEnabled('ai_reports')
            && (bool) auth()->user()?->can('reports.view');
    }

    public function ask(AskHazri $ask): void
    {
        $this->validate(['question' => ['required', 'string', 'min:5', 'max:500']]);
        $this->answer = $ask->ask($this->question, auth()->id());
    }

    public function feedback(int $value): void
    {
        if ($this->answer['query_id'] ?? null) {
            AiQuery::whereKey($this->answer['query_id'])->where('user_id', auth()->id())->update(['feedback' => $value > 0 ? 1 : -1]);
        }
    }

    public function export(): ?BinaryFileResponse
    {
        if (! ($this->answer['ok'] ?? false) || ! auth()->user()->can('reports.export')) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'ask') . '.xlsx';
        Spreadsheet::write($path, $this->answer['columns'], array_map('array_values', $this->answer['rows']));

        return response()->download($path, 'hazri-answer.xlsx')->deleteFileAfterSend();
    }

    public function tokensLeft(): int
    {
        return app(AiGateway::class)->tokensLeft();
    }
}
