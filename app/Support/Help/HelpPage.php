<?php

declare(strict_types=1);

namespace App\Support\Help;

use Illuminate\Support\Str;

/** One topic of the user guide (a Markdown file in resources/docs); see HelpLibrary for the file format. */
final class HelpPage
{
    /**
     * @param  list<string>  $panel
     * @param  array<string, string>  $screens  file name => panel address
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $group,
        public readonly int $order,
        public readonly string $summary,
        public readonly string $menu,
        public readonly array $panel,
        public readonly array $screens,
        public readonly string $body,
    ) {}

    public static function fromFile(string $file): self
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        $meta = [];
        if (preg_match('/\A---\n(.*?)\n---\n/s', $raw, $m) === 1) {
            foreach (explode("\n", $m[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $meta[trim($key)] = trim($value);
                }
            }
            $raw = substr($raw, strlen($m[0]));
        }
        $list = fn (string $key): array => array_values(array_filter(array_map('trim', explode('|', $meta[$key] ?? ''))));
        $screens = [];
        foreach ($list('screens') as $screen) {
            [$name, $url] = array_pad(array_map('trim', explode('=', $screen, 2)), 2, '');
            $screens[$name] = $url;
        }

        return new self(
            slug: basename($file, '.md'),
            title: $meta['title'] ?? Str::headline(basename($file, '.md')),
            group: $meta['group'] ?? 'Getting started',
            order: (int) ($meta['order'] ?? 100),
            summary: $meta['summary'] ?? '',
            menu: $meta['menu'] ?? '',
            panel: $list('panel'),
            screens: $screens,
            body: trim($raw) . "\n",
        );
    }

    /** The same id HelpLibrary::render puts on the rendered heading. */
    public static function anchor(string $heading): string
    {
        return Str::slug(HelpLibrary::plain(HelpLibrary::brand($heading)));
    }

    public function html(): string
    {
        return HelpLibrary::render($this->body);
    }

    /** @return list<array{heading: string, anchor: string, body: string}> the text before the first "##" has heading '' */
    public function sections(): array
    {
        $parts = preg_split('/^## +(.+)$/m', $this->body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [['heading' => '', 'anchor' => '', 'body' => trim($parts[0] ?? '')]];
        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = trim($parts[$i]);
            $sections[] = ['heading' => $heading, 'anchor' => self::anchor($heading), 'body' => trim($parts[$i + 1] ?? '')];
        }

        return array_values(array_filter($sections, fn (array $s): bool => $s['heading'] !== '' || $s['body'] !== ''));
    }

    public function section(string $heading): ?string
    {
        foreach ($this->sections() as $s) {
            if (strcasecmp($s['heading'], $heading) === 0) {
                return $s['body'];
            }
        }

        return null;
    }

    /** @return list<array{heading: string, anchor: string}> the "##" headings, for the contents box */
    public function contents(): array
    {
        return array_values(array_map(
            fn (array $s): array => ['heading' => HelpLibrary::brand($s['heading']), 'anchor' => $s['anchor']],
            array_filter($this->sections(), fn (array $s): bool => $s['heading'] !== ''),
        ));
    }

    /** @return list<array{question: string, anchor: string, answer: string}> the "###" entries under "Common problems" */
    public function problems(): array
    {
        $parts = preg_split('/^### +(.+)$/m', $this->section(HelpLibrary::PROBLEMS) ?? '', -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $problems = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $question = trim($parts[$i]);
            $problems[] = ['question' => $question, 'anchor' => self::anchor($question), 'answer' => trim($parts[$i + 1] ?? '')];
        }

        return $problems;
    }
}
