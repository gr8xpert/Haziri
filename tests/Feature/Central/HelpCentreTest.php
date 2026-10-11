<?php

declare(strict_types=1);

use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpPage;

beforeEach(fn () => $this->help = 'http://' . config('hazri.central_domain') . '/help');

it('lists every topic on the guide start page', function () {
    $response = $this->get($this->help)->assertOk()->assertSee('Quick reference')->assertSee('Troubleshooting');

    foreach (app(HelpLibrary::class)->pages() as $page) {
        $response->assertSee('/help/' . $page->slug, false);
    }
});

it('shows a topic with its screenshots, contents and the product name', function () {
    $this->get("{$this->help}/leave-requests")->assertOk()
        ->assertSee('Leave → Leave requests')
        ->assertSee('id="in-short"', false)
        ->assertSee('src="/help-img/leave-requests.jpg"', false)
        ->assertSee('On this page')
        ->assertDontSee('{app}');

    $this->get("{$this->help}/no-such-topic")->assertNotFound();
});

it('collects the short versions and the common problems of all topics', function () {
    $this->get("{$this->help}/quick")->assertOk()->assertSee('Leave requests')->assertSee('Full guide');
    $this->get("{$this->help}/troubleshooting")->assertOk()->assertSee('That month is locked')
        ->assertSee('id="leave-requests-that-month-is-locked"', false);
});

it('searches every section of every topic', function () {
    $index = $this->get("{$this->help}/search.json")->assertOk()->json();

    expect(collect($index)->firstWhere('h', 'Approve, reject or cancel'))
        ->toMatchArray(['p' => 'Leave requests', 'g' => 'Leave', 'u' => '/help/leave-requests#approve-reject-or-cancel']);
});

it('opens the topic for the panel screen the user is on', function () {
    $this->get("{$this->help}/for?path=admin/leave-requests")->assertRedirect('/help/leave-requests');
    $this->get("{$this->help}/for?path=admin/no-such-screen")->assertRedirect('/help');
});

it('keeps every topic complete and its links and pictures working', function () {
    $library = app(HelpLibrary::class);
    $slugs = $library->pages()->keys()->all();

    foreach ($library->pages() as $page) {
        /** @var HelpPage $page */
        $where = "resources/docs/{$page->slug}.md";
        expect($page->group)->toBeIn(HelpLibrary::GROUPS, "{$where}: unknown group")
            ->and($page->summary)->not->toBe('', "{$where}: no summary")
            ->and($page->section(HelpLibrary::QUICK))->not->toBeNull("{$where}: no \"## In short\"")
            ->and(preg_match('/haz(i)?ri/i', $page->body))->toBe(0, "{$where}: write {app}, not the product name");

        preg_match_all('#\]\(/help/([a-z0-9-]+)#', $page->body, $links);
        expect(array_diff($links[1], $slugs))->toBe([], "{$where}: links to a topic that does not exist");

        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $page->body, $images);
        foreach ($images[1] as $image) {
            expect(array_key_exists(basename($image, '.jpg'), $page->screens))->toBeTrue("{$where}: {$image} is not under screens:")
                ->and(public_path("help-img/{$image}"))->toBeFile("{$where}: {$image} has not been taken (scripts/help-screenshots.mjs)");
        }
    }
});
