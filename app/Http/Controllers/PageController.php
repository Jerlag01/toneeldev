<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->where('slug', Page::HOME_SLUG)->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        if ($slug === Page::HOME_SLUG) {
            return redirect()->route('home', status: 301);
        }

        // Logged-in editors may preview unpublished pages.
        $page = Page::query()
            ->where('slug', $slug)
            ->when(! auth()->user()?->hasPermission(\App\Support\Permissions::PAGES), fn ($query) => $query->published())
            ->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
