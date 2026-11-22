<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Production;
use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml, generated so new pages are listed and the development site keeps robots away.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = Seo::indexable()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire/', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $productions = Production::query()->published()->get(['slug', 'updated_at', 'starts_on', 'ends_on']);

        $urls = Page::query()->published()->orderBy('menu_order')->get(['slug', 'updated_at', 'show_in_menu'])
            ->map(fn (Page $page): array => [
                'loc' => $page->url(),
                'lastmod' => $page->updated_at,
                'priority' => $page->isHome() ? '1.0' : ($page->show_in_menu ? '0.8' : '0.5'),
            ])
            ->push(['loc' => route('productions.index'), 'lastmod' => $productions->max('updated_at'), 'priority' => '0.8'])
            ->concat($productions->map(fn (Production $production): array => [
                    'loc' => $production->url(),
                    'lastmod' => $production->updated_at,
                    'priority' => $production->isUpcoming() ? '0.8' : '0.6',
                ]));

        return response()->view('seo.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
