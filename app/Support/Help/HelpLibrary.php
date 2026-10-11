<?php

declare(strict_types=1);

namespace App\Support\Help;

use App\Support\Brand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The user guide: one Markdown file per topic in resources/docs. Each file starts with a small header:
 *
 *     ---
 *     title: Leave requests
 *     group: Leave
 *     order: 10
 *     summary: One sentence on what the page is for.
 *     menu: Leave → Leave requests
 *     panel: leave-requests          (the /admin/… address, so the panel's Help button opens this topic)
 *     screens: name=/admin/path | …   (screenshots retaken by scripts/help-screenshots.mjs)
 *     ---
 *
 * "## In short" is the quick version (quick reference page) and "## Common problems" holds "### problem" + answer
 * pairs (troubleshooting page). {app} becomes the product name the reader sees (white-label aware).
 */
final class HelpLibrary
{
    /** Menu order of the guide's groups. */
    public const GROUPS = [
        'Getting started', 'Company setup', 'People', 'Shifts & holidays', 'Attendance', 'Leave', 'Devices',
        'Reports', 'Payroll', 'Insights', 'System', 'For staff',
    ];

    public const QUICK = 'In short';

    public const PROBLEMS = 'Common problems';

    /** @var Collection<string, HelpPage>|null */
    private static ?Collection $pages = null;

    public function __construct(private ?string $path = null)
    {
        $this->path ??= resource_path('docs');
    }

    /** @return Collection<string, HelpPage> keyed by slug, in guide order */
    public function pages(): Collection
    {
        if (self::$pages !== null && $this->path === resource_path('docs')) {
            return self::$pages;
        }
        $groupOrder = array_flip(self::GROUPS);
        $pages = collect(glob($this->path . '/*.md') ?: [])
            ->map(fn (string $file): HelpPage => HelpPage::fromFile($file))
            ->sortBy(fn (HelpPage $p): string => sprintf('%03d-%05d-%s', $groupOrder[$p->group] ?? 999, $p->order, $p->title))
            ->keyBy('slug');

        return $this->path === resource_path('docs') ? self::$pages = $pages : $pages;
    }

    /** @return Collection<string, Collection<string, HelpPage>> */
    public function grouped(): Collection
    {
        return $this->pages()->groupBy('group', preserveKeys: true);
    }

    public function find(string $slug): ?HelpPage
    {
        return $this->pages()->get($slug);
    }

    /** The topic for a panel address such as "leave-requests/5/edit" (first matching segment wins). */
    public function forPanelPath(string $path): ?HelpPage
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn (string $s): bool => $s !== 'admin' && $s !== ''));
        if ($segments === []) {
            return $this->find('dashboard');
        }
        $byPanel = $this->pages()->flatMap(fn (HelpPage $p): array => collect($p->panel)->mapWithKeys(fn (string $k): array => [$k => $p])->all());
        for ($n = count($segments); $n > 0; $n--) {
            if ($page = $byPanel->get(implode('/', array_slice($segments, 0, $n)))) {
                return $page;
            }
        }

        return null;
    }

    /** @return list<array{p: string, h: string, u: string, g: string, x: string}> one entry per section, for the search box */
    public function searchIndex(): array
    {
        return $this->pages()->flatMap(fn (HelpPage $page): array => collect($page->sections())
            ->map(fn (array $s): array => [
                'p' => $page->title,
                'h' => $s['heading'],
                'u' => '/help/' . $page->slug . ($s['heading'] === '' ? '' : '#' . $s['anchor']),
                'g' => $page->group,
                'x' => self::brand(Str::limit(self::plain($s['body']), 600)),
            ])->all())->values()->all();
    }

    public static function render(string $markdown): string
    {
        $html = Str::markdown(self::brand($markdown), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        // Ids on headings for links and the contents box.
        $html = preg_replace_callback('#<h([234])>(.*?)</h\1>#s', fn (array $m): string => sprintf(
            '<h%1$s id="%2$s"><a href="#%2$s">%3$s</a></h%1$s>', $m[1], Str::slug(self::plain(html_entity_decode(strip_tags($m[2])))), $m[2],
        ), $html) ?? $html;
        // Screenshots live in public/help-img; Markdown names them by file name only.
        $html = preg_replace(
            '#<img src="(?!https?:|/)([^"]+)" alt="([^"]*)"\s*/?>#',
            '<a href="/help-img/$1" target="_blank" class="hz-shot"><img src="/help-img/$1" alt="$2" loading="lazy"></a>',
            $html,
        ) ?? $html;

        return $html;
    }

    /** {app} → the product name this reader sees. */
    public static function brand(string $text): string
    {
        return str_replace('{app}', Brand::name(), $text);
    }

    /** Markdown → plain text for search snippets. */
    public static function plain(string $markdown): string
    {
        $text = preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/\[([^\]]*)\]\([^)]*\)/', '/[*_`>#|]+/', '/^-{3,}$/m', '/\s+/'], ['', '$1', '', '', ' '], $markdown);

        return trim((string) $text);
    }
}
