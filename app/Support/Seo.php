<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Everything search engines and social networks read: title, description, keywords, share image,
 * indexing rules and schema.org data (all set in Instellingen → Zoekmachines, overridable per page).
 */
class Seo
{
    /**
     * Only the production site may be indexed, so the development copy never ends up in Google.
     */
    public static function indexable(): bool
    {
        return app()->isProduction() && (bool) settings()->get('seo.indexable', true);
    }

    public static function title(?string $pageTitle): string
    {
        $site = settings()->siteName();

        if (blank($pageTitle) || $pageTitle === $site) {
            return trim($site.' - '.settings()->get('tagline'), ' -');
        }

        return strtr((string) (settings()->get('seo.title_format') ?: '{pagina} | {site}'), ['{pagina}' => $pageTitle, '{site}' => $site]);
    }

    public static function description(?string $description): string
    {
        $text = trim((string) ($description ?: settings()->get('seo.description') ?: settings()->get('tagline')));

        return Str::limit(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '', 160, '…');
    }

    public static function shareImage(?string $image = null): string
    {
        return media_url($image) ?? media_url(settings()->get('seo.share_image')) ?? asset('images/story/hero.jpg');
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        $vzw = (array) settings()->get('vzw');

        return array_filter([
            '@type' => ['Organization', 'PerformingGroup'],
            '@id' => url('/').'#organisatie',
            'name' => settings()->siteName(),
            'legalName' => $vzw['name'] ?? null,
            'alternateName' => $vzw['short_name'] ?? null,
            'foundingDate' => $vzw['founded'] ?? null,
            'description' => self::description(null),
            'url' => url('/'),
            'logo' => settings()->logoUrl() ?? settings()->faviconUrl(),
            'image' => self::shareImage(),
            'email' => $vzw['email'] ?? null,
            'telephone' => $vzw['phone'] ?? null,
            'taxID' => $vzw['company_number'] ?? null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $vzw['street'] ?? null,
                'postalCode' => $vzw['postal_code'] ?? null,
                'addressLocality' => $vzw['city'] ?? null,
                'addressCountry' => 'BE',
            ]),
            'sameAs' => collect((array) settings()->get('social_links'))->pluck('url')->filter()->values()->all(),
        ]);
    }

    public static function siteGraph(): string
    {
        return self::json([
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'name' => settings()->siteName(),
                    'url' => url('/'),
                    'inLanguage' => 'nl-BE',
                    'publisher' => ['@id' => url('/').'#organisatie'],
                ],
            ],
        ]);
    }

    public static function pageTitle(Page $page): ?string
    {
        if (filled($page->seo_title)) {
            return $page->seo_title;
        }

        return $page->isHome() ? null : $page->title;
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
