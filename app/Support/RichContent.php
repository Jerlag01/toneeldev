<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Renders editor text safely and turns a YouTube link or a link to a video file that stands alone in a paragraph
 * into a player, so editors can put videos in the middle of their text (rich editor and Markdown alike).
 */
class RichContent
{
    public static function rich(?string $html): HtmlString
    {
        return new HtmlString(blank($html) ? '' : self::embedVideos(RichContentRenderer::make($html)->toHtml()));
    }

    public static function markdown(?string $markdown): HtmlString
    {
        if (blank($markdown)) {
            return new HtmlString('');
        }

        // Raw HTML in Markdown is removed: only Markdown formatting is allowed.
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return new HtmlString(self::embedVideos($html));
    }

    public static function embedVideos(string $html): string
    {
        return (string) preg_replace_callback(
            '~<p>\s*(?:<a [^>]*href="([^"]+)"[^>]*>[^<]*</a>|(https?://[^\s<]+))\s*</p>~i',
            function (array $match): string {
                $url = html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? ''));

                if ($id = youtube_id($url)) {
                    return '<figure class="story-video story-video--inline">'.view('partials.youtube', ['id' => $id, 'caption' => null])->render().'</figure>';
                }

                if (preg_match('~\.(mp4|webm|mov)(\?.*)?$~i', $url)) {
                    return '<figure class="story-video story-video--inline"><video controls preload="metadata" playsinline><source src="'.e($url).'"></video></figure>';
                }

                return $match[0];
            },
            $html,
        );
    }
}
