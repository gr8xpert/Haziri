<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Help\HelpLibrary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The user guide at /help, on the main site and on every company's own address (see HelpLibrary). */
class HelpController extends Controller
{
    public function __construct(private HelpLibrary $library) {}

    public function index(): View
    {
        return view('help.index', ['groups' => $this->library->grouped()]);
    }

    public function quick(): View
    {
        return view('help.quick', ['groups' => $this->library->grouped()]);
    }

    public function troubleshooting(): View
    {
        return view('help.troubleshooting', ['groups' => $this->library->grouped()]);
    }

    public function show(string $slug): View
    {
        $page = $this->library->find($slug) ?? abort(404);
        $pages = $this->library->pages()->values();
        $at = $pages->search(fn ($p): bool => $p->slug === $slug);

        return view('help.page', [
            'page' => $page,
            'groups' => $this->library->grouped(),
            'previous' => $at > 0 ? $pages[$at - 1] : null,
            'next' => $pages[$at + 1] ?? null,
        ]);
    }

    /** The panel's Help button: the topic for the screen the user is on, else the guide's start. */
    public function forScreen(Request $request): RedirectResponse
    {
        $page = $this->library->forPanelPath((string) $request->query('path', ''));

        return redirect($page ? '/help/' . $page->slug : '/help');
    }

    public function search(): JsonResponse
    {
        return response()->json($this->library->searchIndex())->header('Cache-Control', 'public, max-age=300');
    }
}
